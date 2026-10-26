<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Services\VinDecoderService;
use Illuminate\Http\Request;

class VinController extends Controller
{
    public function decode(Request $request, VinDecoderService $decoder)
    {
        $request->validate(['vin' => ['required', 'string', 'max:30'], 'year' => ['nullable', 'integer', 'min:1950', 'max:2100']]);
        $result = $decoder->decode($request->vin, $request->year);

        $existing = $result['validation']['valid'] ? Vehicle::withTrashed()->where('vin', $result['vin'])->first() : null;
        $result['duplicate'] = $existing ? ['id' => $existing->id, 'stock_number' => $existing->stock_number,
            'url' => $existing->trashed() ? null : route('vehicles.show', $existing)] : null;

        return response()->json($result, $result['validation']['valid'] ? 200 : 422);
    }
}
