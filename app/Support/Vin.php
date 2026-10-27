<?php

namespace App\Support;

/**
 * VIN validation (ISO 3779 / 49 CFR 565). 17 characters, no I/O/Q.
 * The check digit (position 9) is mandatory for North American vehicles; for
 * other markets an invalid check digit is reported as a warning only.
 */
final class Vin
{
    private const MAP = [
        'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
        'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
        'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
    ];

    private const WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function normalize(?string $vin): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $vin));
    }

    public static function isWellFormed(string $vin): bool
    {
        return (bool) preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin);
    }

    public static function checkDigit(string $vin): ?string
    {
        if (! self::isWellFormed($vin)) {
            return null;
        }
        $sum = 0;
        foreach (str_split($vin) as $i => $c) {
            $value = ctype_digit($c) ? (int) $c : self::MAP[$c];
            $sum += $value * self::WEIGHTS[$i];
        }
        $r = $sum % 11;

        return $r === 10 ? 'X' : (string) $r;
    }

    public static function hasValidCheckDigit(string $vin): bool
    {
        return self::isWellFormed($vin) && self::checkDigit($vin) === $vin[8];
    }

    /** @return array{valid:bool, vin:string, check_digit_valid:bool, errors:array<string>, warnings:array<string>} */
    public static function validate(?string $raw): array
    {
        $vin = self::normalize($raw);
        $errors = [];
        $warnings = [];

        if (strlen($vin) !== 17) {
            $errors[] = 'A VIN must be exactly 17 characters (got '.strlen($vin).').';
        } elseif (preg_match('/[IOQ]/', $vin)) {
            $errors[] = 'VINs never contain the letters I, O or Q.';
        } elseif (! self::isWellFormed($vin)) {
            $errors[] = 'VIN contains invalid characters.';
        }

        $checkOk = ! $errors && self::hasValidCheckDigit($vin);
        if (! $errors && ! $checkOk) {
            $warnings[] = 'Check digit (position 9) does not match. Required for North American VINs — please double-check the VIN.';
        }

        return ['valid' => ! $errors, 'vin' => $vin, 'check_digit_valid' => $checkOk, 'errors' => $errors, 'warnings' => $warnings];
    }
}
