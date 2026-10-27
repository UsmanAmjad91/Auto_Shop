<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'job_title' => fake()->randomElement(['Technician', 'Detailer', 'Sales Associate']),
            'hire_date' => now()->subYear(),
            'employment_status' => 'active',
            'employment_type' => 'full_time',
        ];
    }
}
