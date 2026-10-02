<?php

namespace Tests\Concerns;

use Nexor\Shop\Enums\DeliveryProvider;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Support\Delivery\Deliveries;

/**
 * Способ доставки СДЭК с тестовыми ключами — для тестов расчёта, передачи
 * заказа и слежения за посылкой.
 */
trait BuildsCdekMethods
{
    /**
     * Способ доставки со СДЭК и тестовыми ключами.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function cdekMethod(array $settings = []): DeliveryMethod
    {
        $method = DeliveryMethod::factory()->create(['name' => 'СДЭК', 'price' => 0]);

        // Списки заменяются целиком: рекурсивное слияние оставило бы старые тарифы.
        $lists = array_intersect_key($settings, ['tariffs' => null, 'address' => null, 'sender' => null]);

        $method->forceFill([
            'provider' => DeliveryProvider::Cdek,
            'settings' => Deliveries::fromInput(array_replace(array_replace_recursive([
                'account' => 'account',
                'secret' => 'secret',
                'test' => true,
                'from_code' => 44,
                'tariffs' => [['code' => 136, 'name' => 'Посылка склад-склад', 'to_door' => false]],
                'weight' => ['property' => 'WEIGHT', 'unit' => 'kg', 'default' => 2],
                'dimensions' => [
                    'length' => ['property' => 'LENGTH', 'default' => 30],
                    'width' => ['property' => 'WIDTH', 'default' => 20],
                    'height' => ['property' => 'HEIGHT', 'default' => 10],
                ],
            ], $settings), $lists), null),
        ])->save();

        return $method->refresh();
    }
}
