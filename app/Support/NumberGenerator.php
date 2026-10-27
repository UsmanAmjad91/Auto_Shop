<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generates unique, sequential document numbers (INV-000123) using a locked
 * row per sequence, so concurrent requests never receive the same number.
 */
final class NumberGenerator
{
    public static function next(string $key, string $prefix, int $pad = 6): string
    {
        return DB::transaction(function () use ($key, $prefix, $pad) {
            $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();

            if (! $row) {
                DB::table('number_sequences')->insertOrIgnore([
                    'key' => $key, 'next_value' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $row = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            }

            $value = (int) $row->next_value;
            DB::table('number_sequences')->where('key', $key)->update([
                'next_value' => $value + 1, 'updated_at' => now(),
            ]);

            return $prefix.str_pad((string) $value, $pad, '0', STR_PAD_LEFT);
        });
    }
}
