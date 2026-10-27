<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Part> Quantities start at zero; add stock via InventoryService. */
class PartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'part_number' => 'PRT-'.fake()->unique()->numerify('#####'),
            'name' => fake()->randomElement(['Brake Pad Set', 'Oil Filter', 'Air Filter', 'Spark Plug', 'Wiper Blade', 'Headlight Bulb']).' '.fake()->bothify('##??'),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'purchase_cost' => fake()->randomFloat(2, 5, 150),
            'selling_price' => fake()->randomFloat(2, 160, 300),
            'min_stock_level' => 2,
            'unit' => 'pcs',
            'condition' => 'new',
            'stock_status' => 'out_of_stock',
        ];
    }
}
