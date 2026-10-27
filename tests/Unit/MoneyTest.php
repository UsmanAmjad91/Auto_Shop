<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_sum_avoids_float_drift(): void
    {
        $this->assertSame(0.3, Money::sum([0.1, 0.2]));
        $this->assertSame(100.42, Money::add(92.98, 7.44));
    }

    public function test_percent_rounds_half_up(): void
    {
        $this->assertSame(7.44, Money::percent(92.98, 8));
        $this->assertSame(0.01, Money::percent(0.125, 10)); // 0.0125 -> 0.01
    }

    public function test_sub_and_mul(): void
    {
        $this->assertSame(-0.01, Money::sub(10.00, 10.01));
        $this->assertSame(19.98, Money::mul(9.99, 2));
    }
}
