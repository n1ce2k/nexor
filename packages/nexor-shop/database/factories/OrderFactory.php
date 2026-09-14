<?php

namespace Nexor\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Enums\Currency;
use Nexor\Shop\Enums\CartEdition;
use Nexor\Shop\Enums\OrderStatus;
use Nexor\Shop\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @var class-string<Order> */
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => OrderStatus::New,
            'edition' => CartEdition::Basic,
            'customer' => [
                ['code' => 'NAME', 'name' => 'ФИО', 'value' => fake()->name()],
                ['code' => 'PHONE', 'name' => 'Телефон', 'value' => '+7 900 000-00-00'],
            ],
            'currency' => Currency::RUB,
            'subtotal' => 1000,
            'discount' => 0,
            'delivery_price' => 0,
            'total' => 1000,
        ];
    }
}
