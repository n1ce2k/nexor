<?php

namespace Nexor\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Shop\Models\DeliveryMethod;

/**
 * @extends Factory<DeliveryMethod>
 */
class DeliveryMethodFactory extends Factory
{
    /** @var class-string<DeliveryMethod> */
    protected $model = DeliveryMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Курьер',
            'description' => null,
            'price' => 300,
            'free_from' => null,
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
