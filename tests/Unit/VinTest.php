<?php

namespace Tests\Unit;

use App\Support\Vin;
use PHPUnit\Framework\TestCase;

class VinTest extends TestCase
{
    public function test_valid_vin_with_check_digit(): void
    {
        $r = Vin::validate('1hgcm82633a004352');
        $this->assertTrue($r['valid']);
        $this->assertTrue($r['check_digit_valid']);
        $this->assertSame('1HGCM82633A004352', $r['vin']);
    }

    public function test_wrong_check_digit_is_warning_not_error(): void
    {
        $r = Vin::validate('1HGCM82634A004352');
        $this->assertTrue($r['valid']);
        $this->assertFalse($r['check_digit_valid']);
        $this->assertNotEmpty($r['warnings']);
    }

    public function test_invalid_length_and_letters(): void
    {
        $this->assertFalse(Vin::validate('1HGCM82633A00435')['valid']);
        $this->assertFalse(Vin::validate('1HGCM82633A00435O')['valid']); // letter O
    }

    public function test_normalize_strips_separators(): void
    {
        $this->assertSame('1HGCM82633A004352', Vin::normalize(' 1hg-cm8 2633A004352 '));
    }
}
