<?php

namespace App\Support;

/**
 * Integer-cent arithmetic for money so totals never drift through float rounding.
 */
final class Money
{
    public static function toCents(float|int|string|null $value): int
    {
        return (int) round(((float) ($value ?? 0)) * 100);
    }

    public static function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** Sum any iterable of amounts precisely. */
    public static function sum(iterable $values): float
    {
        $cents = 0;
        foreach ($values as $v) {
            $cents += self::toCents($v);
        }

        return self::fromCents($cents);
    }

    public static function add(float|int|string|null ...$values): float
    {
        return self::sum($values);
    }

    public static function sub(float|int|string|null $a, float|int|string|null $b): float
    {
        return self::fromCents(self::toCents($a) - self::toCents($b));
    }

    /** Multiply an amount by a quantity / rate, rounding half-up to the cent. */
    public static function mul(float|int|string|null $amount, float|int|string|null $factor): float
    {
        return round(((float) $amount) * ((float) $factor) + 0.0000001, 2);
    }

    public static function percent(float|int|string|null $amount, float|int|string|null $percent): float
    {
        return round(((float) $amount) * ((float) $percent) / 100 + 0.0000001, 2);
    }

    public static function format(float|int|string|null $value, ?string $currency = null): string
    {
        $currency ??= settings('general.currency_symbol', '$');

        $v = (float) ($value ?? 0);

        return ($v < 0 ? '-' : '').$currency.number_format(abs($v), 2);
    }
}
