<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'CUS-'.fake()->unique()->numerify('#####'),
            'type' => 'individual',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1 555 ### ####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}
