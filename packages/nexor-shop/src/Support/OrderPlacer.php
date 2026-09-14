<?php

namespace Nexor\Shop\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderField;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Models\Promocode;

/**
 * Превращает корзину в заказ.
 *
 * Basic: поля формы и товары. Ultimate с оформлением: ещё доставка, оплата,
 * промокод и списание остатков. Всё, что считалось в корзине, пересчитывается
 * здесь заново — за время, пока человек заполнял форму, цена или остаток
 * могли измениться.
 */
class OrderPlacer
{
    /**
     * Правила полей формы, ключи — `customer.CODE`, как у `wire:model`.
     *
     * @param  Collection<int, OrderField>  $fields
     * @return array{0: array<string, array<int, string>>, 1: array<string, string>}
     */
    public static function rules(Collection $fields): array
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rules['customer.'.$field->code] = $field->rules();
            $attributes['customer.'.$field->code] = self::inSentence($field->name);
        }

        return [$rules, $attributes];
    }

    /**
     * Название поля посреди фразы: «Поле телефон…», но «Поле ФИО…».
     */
    protected static function inSentence(string $name): string
    {
        $first = mb_substr($name, 0, 1);
        $second = mb_substr($name, 1, 1);

        // Аббревиатуру не трогаем: вторая буква тоже заглавная.
        if ($second !== '' && mb_strtoupper($second) === $second && mb_strtolower($second) !== $second) {
            return $name;
        }

        return mb_strtolower($first).mb_substr($name, 1);
    }

    /**
     * @param  array<string, mixed>  $customer  Значения полей по коду
     *
     * @throws ValidationException
     */
    public function place(Cart $cart, array $customer, ?int $deliveryId = null, ?int $paymentId = null): Order
    {
        $summary = $cart->summary();

        if ($summary->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Корзина пуста.']);
        }

        if ($summary->hasProblems()) {
            throw ValidationException::withMessages(['cart' => 'В корзине есть товары, которые сейчас нельзя купить.']);
        }

        if ($summary->promocodeCode && $summary->promocodeError) {
            throw ValidationException::withMessages(['promocode' => $summary->promocodeError]);
        }

        $fields = OrderField::query()->active()->ordered()->get();
        [$rules, $attributes] = self::rules($fields);

        $checkout = Shop::checkoutEnabled();
        [$delivery, $payment] = $checkout ? $this->methods($deliveryId, $paymentId) : [null, null];

        $data = Validator::make(['customer' => $customer], $rules, [], $attributes)->validate();

        $deliveryPrice = $delivery?->priceFor($summary->total()) ?? 0.0;

        $order = DB::transaction(function () use ($summary, $fields, $data, $delivery, $payment, $deliveryPrice): Order {
            if (Shop::tracksStock()) {
                $this->takeStock($summary);
            }

            if ($summary->promocode) {
                $this->usePromocode($summary->promocode);
            }

            $order = Order::query()->create([
                'status' => 'new',
                'edition' => Shop::edition(),
                'user_id' => Auth::id(),
                'customer' => $fields->map(fn (OrderField $field) => [
                    'code' => $field->code,
                    'name' => $field->name,
                    'type' => $field->type->value,
                    'value' => $data['customer'][$field->code] ?? null,
                ])->values()->all(),
                'currency' => $summary->currency,
                'subtotal' => $summary->subtotal,
                'discount' => $summary->discount,
                'delivery_price' => $deliveryPrice,
                'total' => round($summary->total() + $deliveryPrice, 2),
                'promocode_id' => $summary->promocode?->id,
                'promocode_code' => $summary->promocode?->code,
                'delivery_method_id' => $delivery?->id,
                'delivery_name' => $delivery?->name,
                'payment_method_id' => $payment?->id,
                'payment_name' => $payment?->name,
                'ip' => Request::ip(),
            ]);

            foreach ($summary->lines as $line) {
                $order->items()->create([
                    'element_id' => $line->id,
                    'name' => $line->name,
                    'url' => $line->url,
                    'quantity' => $line->quantity,
                    'measure' => $line->measure,
                    'base_price' => $line->basePrice,
                    'price' => $line->unitPrice,
                    'original_price' => $line->originalPrice,
                    'original_currency' => $line->originalCurrency?->value,
                    'is_converted' => $line->converted,
                    'discount' => $line->discount,
                    'sum' => round($line->sum() - $line->discount, 2),
                ]);
            }

            return $order;
        });

        $cart->clear();
        $cart->save();

        OrderMailer::send($order->load('items'));
        TelegramNotifier::sendOrder($order);

        return $order;
    }

    /**
     * Способы доставки и оплаты обязательны, если они вообще заведены.
     *
     * @return array{0: DeliveryMethod|null, 1: PaymentMethod|null}
     */
    protected function methods(?int $deliveryId, ?int $paymentId): array
    {
        $errors = [];

        $delivery = $deliveryId ? DeliveryMethod::query()->active()->find($deliveryId) : null;
        $payment = $paymentId ? PaymentMethod::query()->active()->find($paymentId) : null;

        if (! $delivery && DeliveryMethod::query()->active()->exists()) {
            $errors['delivery'] = 'Выберите способ доставки.';
        }

        if (! $payment && PaymentMethod::query()->active()->exists()) {
            $errors['payment'] = 'Выберите способ оплаты.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$delivery, $payment];
    }

    /**
     * Списывает остатки у товаров с количественным учётом.
     *
     * Строки блокируются до конца транзакции: два заказа последнего товара
     * одновременно не пройдут оба.
     */
    protected function takeStock(CartSummary $summary): void
    {
        foreach ($summary->lines as $line) {
            $catalog = CatalogProduct::query()->where('element_id', $line->id)->lockForUpdate()->first();

            if (! $catalog || ! $catalog->quantity_trace) {
                continue;
            }

            $left = round((float) $catalog->quantity - $line->quantity, 3);

            if ($left < 0 && ! $catalog->can_buy_zero) {
                throw ValidationException::withMessages([
                    'cart' => '«'.$line->name.'» осталось меньше, чем в корзине. Обновите корзину.',
                ]);
            }

            $catalog->forceFill(['quantity' => max($left, 0)])->save();
        }
    }

    protected function usePromocode(Promocode $promocode): void
    {
        // Условие в самом UPDATE: последний раз промокод применит только один заказ.
        $used = Promocode::query()
            ->whereKey($promocode->id)
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');

        if ($used === 0) {
            throw ValidationException::withMessages(['promocode' => 'Промокод больше не действует.']);
        }
    }
}
