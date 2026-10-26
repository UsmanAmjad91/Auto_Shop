<?php

namespace App\Services;

use App\Models\ApiIntegration;
use App\Models\IntegrationLog;
use App\Support\Vin;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Decodes VINs through NHTSA vPIC (free, no key) or a configurable alternative
 * provider. Results are real API data only — nothing is fabricated on failure.
 */
class VinDecoderService
{
    /** @return array{success:bool, vin:string, data:array, raw:array, message:?string, provider:string, validation:array} */
    public function decode(string $rawVin, ?int $year = null): array
    {
        $validation = Vin::validate($rawVin);
        $vin = $validation['vin'];

        if (! $validation['valid']) {
            return ['success' => false, 'vin' => $vin, 'data' => [], 'raw' => [], 'provider' => 'none',
                'message' => implode(' ', $validation['errors']), 'validation' => $validation];
        }

        $alt = ApiIntegration::activeFor('vin_decoder');
        $useAlt = settings('vin.provider') === 'alternative' && $alt && ($alt->config['url'] ?? null);

        $result = $useAlt ? $this->decodeAlternative($vin, $alt) : $this->decodeNhtsa($vin, $year);
        $result['validation'] = $validation;

        return $result;
    }

    protected function decodeNhtsa(string $vin, ?int $year): array
    {
        $base = rtrim((string) settings('vin.nhtsa_url', 'https://vpic.nhtsa.dot.gov/api/vehicles'), '/');
        $url = "{$base}/DecodeVinValuesExtended/{$vin}";
        $start = microtime(true);

        try {
            $response = Http::timeout((int) settings('vin.timeout', 15))->acceptJson()
                ->get($url, array_filter(['format' => 'json', 'modelyear' => $year]));
            $ms = (int) ((microtime(true) - $start) * 1000);

            if (! $response->successful()) {
                IntegrationLog::record('nhtsa_vpic', 'decode', false, 'HTTP '.$response->status(), $url, $response->status(), $ms);

                return $this->fail($vin, 'nhtsa', 'VIN service returned HTTP '.$response->status().'. Enter details manually.');
            }

            $row = $response->json('Results.0') ?? [];
            $errorCode = (string) ($row['ErrorCode'] ?? '');
            // ErrorCode "0" = clean decode; other codes may still return partial data.
            $data = $this->mapNhtsa($row);
            $ok = ! empty($data['make']) && ! empty($data['year']);

            IntegrationLog::record('nhtsa_vpic', 'decode', $ok, $row['ErrorText'] ?? null, $url, $response->status(), $ms);

            return [
                'success' => $ok,
                'vin' => $vin,
                'data' => $data,
                'raw' => array_filter($row, fn ($v) => $v !== '' && $v !== null && $v !== 'Not Applicable'),
                'provider' => 'nhtsa',
                'message' => $ok
                    ? ($errorCode !== '0' ? 'Decoded with notes: '.($row['ErrorText'] ?? '') : 'VIN decoded successfully.')
                    : ('VIN could not be decoded: '.($row['ErrorText'] ?? 'no data returned').' — enter details manually.'),
            ];
        } catch (Throwable $e) {
            IntegrationLog::record('nhtsa_vpic', 'decode', false, $e->getMessage(), $url);

            return $this->fail($vin, 'nhtsa', 'Could not reach the VIN decoding service. Enter details manually.');
        }
    }

    protected function mapNhtsa(array $r): array
    {
        $engine = collect([
            $r['DisplacementL'] ?? null ? round((float) $r['DisplacementL'], 1).'L' : null,
            ($r['EngineCylinders'] ?? null) ? ($r['EngineConfiguration'] ?? '') === 'V-Shaped' ? 'V'.$r['EngineCylinders'] : $r['EngineCylinders'].'-cyl' : null,
            $r['EngineModel'] ?? null,
            ($r['EngineHP'] ?? null) ? $r['EngineHP'].' hp' : null,
        ])->filter()->implode(' ');

        $transmission = trim(($r['TransmissionStyle'] ?? '').' '.(($r['TransmissionSpeeds'] ?? '') ? $r['TransmissionSpeeds'].'-speed' : ''));

        return array_filter([
            'make' => isset($r['Make']) ? ucwords(strtolower($r['Make'])) : null,
            'model' => $r['Model'] ?? null,
            'year' => isset($r['ModelYear']) && $r['ModelYear'] !== '' ? (int) $r['ModelYear'] : null,
            'trim' => trim(($r['Trim'] ?? '').' '.($r['Series'] ?? '')) ?: null,
            'engine' => $engine ?: null,
            'transmission' => $transmission ?: null,
            'fuel_type' => $this->mapFuel($r['FuelTypePrimary'] ?? null, $r['ElectrificationLevel'] ?? null),
            'body_type' => $this->mapBody($r['BodyClass'] ?? null, $r['VehicleType'] ?? null),
            'drive_type' => $r['DriveType'] ?? null,
            'manufacturer' => $r['Manufacturer'] ?? null,
            'plant_country' => $r['PlantCountry'] ?? null,
            'doors' => $r['Doors'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function mapFuel(?string $fuel, ?string $electrification): ?string
    {
        if (! $fuel) {
            return null;
        }
        $f = strtolower($fuel.' '.$electrification);

        return match (true) {
            str_contains($f, 'plug-in') || str_contains($f, 'phev') => 'Plug-in Hybrid',
            str_contains($f, 'hybrid') || str_contains($f, 'hev') => 'Hybrid',
            str_contains($f, 'electric') => 'Electric',
            str_contains($f, 'diesel') => 'Diesel',
            str_contains($f, 'flex') => 'Flex Fuel',
            str_contains($f, 'compressed natural') => 'CNG',
            str_contains($f, 'gasoline') => 'Gasoline',
            default => 'Other',
        };
    }

    protected function mapBody(?string $body, ?string $type): string
    {
        $b = strtolower($body.' '.$type);

        return match (true) {
            str_contains($b, 'sport utility') || str_contains($b, 'suv') || str_contains($b, 'multipurpose') => 'SUV',
            str_contains($b, 'sedan') => 'Sedan',
            str_contains($b, 'van') || str_contains($b, 'minivan') => 'Van',
            str_contains($b, 'pickup') || str_contains($b, 'truck') => 'Truck',
            str_contains($b, 'coupe') || str_contains($b, 'convertible') => 'Coupe',
            str_contains($b, 'hatchback') || str_contains($b, 'liftback') => 'Hatchback',
            default => 'Other',
        };
    }

    /**
     * Generic alternative provider: URL template with {vin} and {key} placeholders
     * and a JSON field map configured in Settings > Integrations.
     */
    protected function decodeAlternative(string $vin, ApiIntegration $integration): array
    {
        $template = $integration->config['url'];
        $url = str_replace(['{vin}', '{key}'], [$vin, urlencode((string) $integration->credential('api_key'))], $template);
        $map = $integration->config['field_map'] ?? ['make' => 'make', 'model' => 'model', 'year' => 'year', 'trim' => 'trim'];
        $start = microtime(true);

        try {
            $headers = [];
            if ($header = $integration->config['auth_header'] ?? null) {
                $headers[$header] = (string) $integration->credential('api_key');
            }
            $response = Http::timeout((int) settings('vin.timeout', 15))->withHeaders($headers)->acceptJson()->get($url);
            $ms = (int) ((microtime(true) - $start) * 1000);
            $json = $response->json() ?? [];
            $data = [];
            foreach ($map as $field => $path) {
                $value = data_get($json, $path);
                if ($value !== null && $value !== '') {
                    $data[$field] = $field === 'year' ? (int) $value : $value;
                }
            }
            $ok = $response->successful() && ! empty($data['make']);
            IntegrationLog::record($integration->provider, 'decode', $ok, $ok ? null : 'No make returned', $url, $response->status(), $ms);

            return ['success' => $ok, 'vin' => $vin, 'data' => $data, 'raw' => is_array($json) ? $json : [],
                'provider' => $integration->provider,
                'message' => $ok ? 'VIN decoded successfully.' : 'Alternative VIN provider returned no data. Enter details manually.'];
        } catch (Throwable $e) {
            IntegrationLog::record($integration->provider, 'decode', false, $e->getMessage(), $url);

            return $this->fail($vin, $integration->provider, 'Could not reach the VIN provider. Enter details manually.');
        }
    }

    protected function fail(string $vin, string $provider, string $message): array
    {
        return ['success' => false, 'vin' => $vin, 'data' => [], 'raw' => [], 'provider' => $provider, 'message' => $message];
    }
}
