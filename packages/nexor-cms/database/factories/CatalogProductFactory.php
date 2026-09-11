<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\IblockElement;

/**
 * @extends Factory<CatalogProduct>
 */
class CatalogProductFactory extends Factory
{
    /** @var class-string<CatalogProduct> */
    protected $model = CatalogProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'element_id' => IblockElement::factory(),
            'price' => fake()->randomFloat(2, 100, 10000),
            'discount_percent' => 0,
            'quantity' => fake()->numberBetween(0, 100),
            'measure' => 'шт',
            'ratio' => 1,
            'quantity_trace' => false,
            'can_buy_zero' => false,
        ];
    }

    public function traced(float $quantity = 0): static
    {
        return $this->state(['quantity_trace' => true, 'quantity' => $quantity]);
    }

    public function discounted(float $percent): static
    {
        return $this->state(['discount_percent' => $percent]);
    }
}
