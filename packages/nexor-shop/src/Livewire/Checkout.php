<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Nexor\Shop\Livewire\Concerns\PlacesOrder;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\CartSummary;
use Nexor\Shop\Support\Delivery\Deliveries;
use Nexor\Shop\Support\Delivery\DeliveryException;
use Nexor\Shop\Support\Delivery\Rates;
use Nexor\Shop\Support\Delivery\Selection;
use Nexor\Shop\Support\Shop;

/**
 * Оформление заказа корзины Ultimate: контакты, доставка, оплата, итог.
 *
 * <livewire:nexor-shop::checkout />
 *
 * У способа со службой доставки (СДЭК) выбор идёт в три шага: город, тариф,
 * пункт выдачи или адрес. Цену и срок каждого тарифа считает служба, поэтому
 * они появляются только после выбора города.
 */
class Checkout extends Component
{
    use PlacesOrder;

    public ?int $deliveryId = null;

    public ?int $paymentId = null;

    /** Что покупатель набрал в поле города. */
    public string $cityQuery = '';

    #[Locked]
    public ?int $cityCode = null;

    #[Locked]
    public string $cityName = '';

    /** Выбранный тариф службы. */
    public ?int $tariffCode = null;

    /** Адрес — для тарифов «до двери». */
    public string $address = '';

    #[Locked]
    public string $pointCode = '';

    #[Locked]
    public string $pointAddress = '';

    /** @var array<int, array<string, mixed>> Подсказки по городам */
    public array $cities = [];

    public ?string $deliveryError = null;

    public function mount(): void
    {
        abort_unless(Shop::checkoutEnabled(), 404);

        $this->deliveryId = DeliveryMethod::query()->active()->ordered()->value('id');
        $this->paymentId = PaymentMethod::query()->active()->ordered()->value('id');
    }

    /**
     * Сменили способ доставки — прежний выбор тарифа и пункта не годится.
     */
    public function updatedDeliveryId(): void
    {
        $this->resetChoice();
    }

    /**
     * Подсказки по городу: их отдаёт сама служба.
     */
    public function searchCity(): void
    {
        $this->resetErrorBag('delivery');
        $this->deliveryError = null;
        $this->cities = [];

        $method = $this->deliveryMethod();

        if (! $method || ! Deliveries::ready($method)) {
            return;
        }

        try {
            $this->cities = Deliveries::client($method)->cities($this->cityQuery);
        } catch (DeliveryException $exception) {
            $this->deliveryError = $exception->getMessage();

            return;
        }

        if ($this->cities === []) {
            $this->deliveryError = 'Город не найден — попробуйте написать иначе.';
        }
    }

    public function pickCity(int $code, string $name): void
    {
        $this->cityCode = $code;
        $this->cityName = $name;
        $this->cityQuery = $name;
        $this->cities = [];

        // Пункты выдачи и цены — свои в каждом городе.
        $this->pointCode = '';
        $this->pointAddress = '';
        $this->deliveryError = null;
    }

    /**
     * Покупатель правит поле города: прежний выбор сбрасывается, а подсказки
     * подгружаются сами — отдельно нажимать «Найти» не нужно.
     */
    public function updatedCityQuery(): void
    {
        if ($this->cityQuery === $this->cityName) {
            return;
        }

        $this->cityCode = null;
        $this->cityName = '';
        $this->pointCode = '';
        $this->pointAddress = '';

        if (mb_strlen(trim($this->cityQuery)) >= 2) {
            $this->searchCity();
        } else {
            $this->cities = [];
            $this->deliveryError = null;
        }
    }

    public function pickPoint(string $code, string $address = ''): void
    {
        $this->pointCode = $code;
        $this->pointAddress = $address;
    }

    public function render(): View
    {
        $summary = Cart::current()->summary();
        $deliveries = DeliveryMethod::query()->active()->ordered()->get();
        $delivery = $deliveries->firstWhere('id', $this->deliveryId);

        $calculated = (bool) $delivery?->isCalculated();
        $settings = $calculated ? Deliveries::settings($delivery) : null;
        $rates = $calculated ? $this->rates($delivery, $summary) : [];
        $tariff = collect($rates)->firstWhere('code', $this->tariffCode);

        $deliveryPrice = match (true) {
            ! $calculated => $delivery?->priceFor($summary->total()) ?? 0.0,
            // Порог «бесплатно от» перебивает расчёт службы.
            (bool) $delivery?->isFree($summary->total()) => 0.0,
            default => (float) ($tariff['price'] ?? 0),
        };

        return view('nexor-shop::livewire.checkout', [
            'summary' => $summary,
            'fields' => $this->orderFields(),
            'deliveries' => $deliveries,
            'payments' => PaymentMethod::query()->active()->ordered()->get(),
            'deliveryPrice' => $deliveryPrice,
            'grandTotal' => round($summary->total() + $deliveryPrice, 2),
            'cartUrl' => Shop::cartUrl(),

            // Всё про службу доставки: пусто у способа со своей ценой.
            'calculated' => $calculated,
            'rates' => $rates,
            'tariff' => $tariff,
            'points' => $this->points($delivery, $tariff),
            'settings' => $settings,
            'addressField' => $settings && ! $settings['address']['own']
                ? $this->orderFields()->firstWhere('code', $settings['address']['field'])
                : null,
            'ready' => $calculated && Deliveries::ready($delivery),
        ]);
    }

    public function placeOrder(): void
    {
        abort_unless(Shop::checkoutEnabled(), 404);

        $this->submitOrder($this->deliveryId, $this->paymentId, [
            'city_code' => $this->cityCode,
            'city' => $this->cityName,
            'tariff' => $this->tariffCode,
            'address' => $this->address,
            'point' => $this->pointCode,
            'point_address' => $this->pointAddress,
        ]);
    }

    /**
     * Тарифы с ценой и сроком. Без города считать нечего.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rates(DeliveryMethod $method, CartSummary $summary): array
    {
        if ($this->cityCode === null || ! Deliveries::ready($method)) {
            return [];
        }

        $rates = Rates::forCart($method, $summary, $this->cityCode, $this->deliveryAddress($method));

        // Выбранный тариф пропал из настроек — снимаем выбор, а не молчим.
        if ($this->tariffCode !== null && ! collect($rates)->contains('code', $this->tariffCode)) {
            $this->tariffCode = null;
        }

        return $rates;
    }

    /**
     * Пункты выдачи города — только для тарифа, который везёт не до двери.
     *
     * @param  array<string, mixed>|null  $tariff
     * @return array<int, array<string, mixed>>
     */
    protected function points(?DeliveryMethod $method, ?array $tariff): array
    {
        if (! $method || ! $tariff || $tariff['to_door'] || $this->cityCode === null) {
            return [];
        }

        try {
            return Deliveries::client($method)->deliveryPoints($this->cityCode);
        } catch (DeliveryException $exception) {
            $this->deliveryError = $exception->getMessage();

            return [];
        }
    }

    /**
     * Адрес доставки: своё поле блока или поле заказа с кодом из настроек.
     */
    protected function deliveryAddress(DeliveryMethod $method): string
    {
        return Selection::address(Deliveries::settings($method), ['address' => $this->address], $this->customer);
    }

    protected function deliveryMethod(): ?DeliveryMethod
    {
        return $this->deliveryId ? DeliveryMethod::query()->active()->find($this->deliveryId) : null;
    }

    protected function resetChoice(): void
    {
        $this->tariffCode = null;
        $this->pointCode = '';
        $this->pointAddress = '';
        $this->deliveryError = null;
        $this->cities = [];
    }
}
