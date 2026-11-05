<?php

namespace Database\Factories;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stock> */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'description' => fake()->paragraph(),
            'quantity' => fake()->numberBetween(10, 100),
            'unit' => 'pcs',
            'price' => fake()->randomFloat(2, 1000, 250000),
            'min_stock' => 5,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes): array => ['quantity' => 25, 'min_stock' => 5]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes): array => ['quantity' => 3, 'min_stock' => 5]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => ['quantity' => 0, 'min_stock' => 5]);
    }
}
