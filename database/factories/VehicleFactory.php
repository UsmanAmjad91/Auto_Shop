<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Support\Vin;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    /** Generates a random VIN with a valid check digit. */
    public static function validVin(): string
    {
        $chars = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
        do {
            $vin = '';
            for ($i = 0; $i < 17; $i++) {
                $vin .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $vin[8] = '0';
            $vin[8] = Vin::checkDigit($vin);
        } while (! Vin::hasValidCheckDigit($vin));

        return $vin;
    }

    public function definition(): array
    {
        return [
            'vin' => self::validVin(),
            'stock_number' => 'STK-'.fake()->unique()->numerify('#####'),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Ford', 'Chevrolet', 'Nissan', 'BMW']),
            'model' => fake()->randomElement(['Camry', 'Civic', 'F-150', 'Malibu', 'Altima', 'X5']),
            'year' => fake()->numberBetween(2012, (int) date('Y')),
            'body_type' => fake()->randomElement(['SUV', 'Sedan', 'Truck', 'Hatchback']),
            'mileage' => fake()->numberBetween(5000, 180000),
            'ownership_type' => 'company',
            'repair_status' => 'not_started',
            'inventory_status' => 'available',
            'payment_status' => 'unpaid',
            'purchase_date' => fake()->dateTimeBetween('-1 year'),
            'purchase_price' => fake()->numberBetween(4000, 30000),
            'asking_price' => fake()->numberBetween(8000, 40000),
        ];
    }
}
