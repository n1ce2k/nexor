<?php

namespace Nexor\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Shop\Models\PaymentMethod;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /** @var class-string<PaymentMethod> */
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Наличными при получении',
            'description' => null,
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
