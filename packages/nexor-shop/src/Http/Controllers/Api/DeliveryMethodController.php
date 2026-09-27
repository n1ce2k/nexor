<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Secrets;
use Nexor\Shop\Enums\DeliveryProvider;
use Nexor\Shop\Http\Requests\DeliveryMethodRequest;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Support\Delivery\Cdek;
use Nexor\Shop\Support\Delivery\Deliveries;
use Nexor\Shop\Support\Delivery\DeliveryException;
use Nexor\Shop\Support\Shop;

class DeliveryMethodController extends ApiController
{
    public function index(): JsonResponse
    {
        $methods = DeliveryMethod::query()->ordered()->get()->map(fn (DeliveryMethod $method) => $this->payload($method));

        return response()->json([
            'data' => $methods,
            'providers' => DeliveryProvider::options(),
            'currency_symbol' => Shop::currency()->symbol(),
        ]);
    }

    public function store(DeliveryMethodRequest $request): JsonResponse
    {
        $method = DeliveryMethod::query()->create($this->attributes($request, null));

        ActivityLogger::created($method, 'Способ доставки «'.$method->name.'»');

        return response()->json(['data' => $this->payload($method), 'message' => 'Способ доставки «'.$method->name.'» добавлен.'], 201);
    }

    public function update(DeliveryMethodRequest $request, DeliveryMethod $deliveryMethod): JsonResponse
    {
        $deliveryMethod->update($this->attributes($request, $deliveryMethod));

        ActivityLogger::updated($deliveryMethod, 'Способ доставки «'.$deliveryMethod->name.'»');

        return response()->json(['data' => $this->payload($deliveryMethod), 'message' => 'Способ доставки «'.$deliveryMethod->name.'» сохранён.']);
    }

    public function destroy(DeliveryMethod $deliveryMethod): JsonResponse
    {
        ActivityLogger::deleted($deliveryMethod, 'Способ доставки «'.$deliveryMethod->name.'»');
        $deliveryMethod->delete();

        return $this->ok('Способ доставки удалён.');
    }

    /**
     * Проверяет ключи СДЭК теми, что сейчас в форме.
     *
     * Способ может быть ещё не сохранён: проверять ключи до сохранения удобнее,
     * чем сохранять заведомо неверные, чтобы узнать, что они неверные.
     */
    public function check(Request $request): JsonResponse
    {
        try {
            $cdek = $this->clientFromInput($request->validate([
                'account' => ['nullable', 'string', 'max:190'],
                'secret' => ['nullable', 'string', 'max:255'],
                'test' => ['boolean'],
                'method' => ['nullable', 'integer'],
            ]));

            $cdek->ping();
        } catch (DeliveryException $exception) {
            return $this->refuse($exception->getMessage());
        }

        return $this->ok('СДЭК принял ключи — можно считать доставку.');
    }

    /**
     * Подсказки по городам для поля «Город отправления».
     *
     * Ключи берём из формы, а не из базы: город выбирают и в ещё не сохранённом
     * способе — иначе его нельзя было бы создать, ведь код города обязателен.
     */
    public function cities(Request $request): JsonResponse
    {
        $input = $request->validate([
            'q' => ['required', 'string', 'max:190'],
            'account' => ['nullable', 'string', 'max:190'],
            'secret' => ['nullable', 'string', 'max:255'],
            'test' => ['boolean'],
            'method' => ['nullable', 'integer'],
        ]);

        try {
            $cities = $this->clientFromInput($input)->cities($input['q']);
        } catch (DeliveryException $exception) {
            return $this->refuse($exception->getMessage());
        }

        return response()->json(['data' => $cities]);
    }

    /**
     * Клиент СДЭК по данным формы. Пароль маской означает сохранённый.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws DeliveryException
     */
    protected function clientFromInput(array $input): Cdek
    {
        $method = filled($input['method'] ?? null) ? DeliveryMethod::query()->find($input['method']) : null;
        $stored = $method ? Deliveries::settings($method) : ['account' => '', 'secret' => null, 'test' => false];

        $account = trim((string) ($input['account'] ?? '')) ?: $stored['account'];
        $secret = Secrets::resolve($input['secret'] ?? null, $stored['secret']);

        if ($account === '' || $secret === null) {
            throw new DeliveryException('Заполните аккаунт и секретный пароль на вкладке «Доступ».');
        }

        return new Cdek($account, $secret, (bool) ($input['test'] ?? $stored['test']), $method?->id);
    }

    /**
     * Что из способа доставки уходит в панель: секретный пароль — маской.
     *
     * @return array<string, mixed>
     */
    protected function payload(DeliveryMethod $method): array
    {
        $provider = $method->provider ?? DeliveryProvider::None;

        return [
            'id' => $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'provider' => $provider->value,
            'provider_label' => $provider->label(),
            'is_calculated' => $provider->isCalculated(),
            'is_ready' => Deliveries::ready($method),
            'settings' => Deliveries::forPanel($method),
            'price' => $method->price,
            'free_from' => $method->free_from,
            'is_active' => $method->is_active,
            'sort' => $method->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(DeliveryMethodRequest $request, ?DeliveryMethod $method): array
    {
        $data = $request->validated();
        $provider = DeliveryProvider::tryFrom((string) ($data['provider'] ?? '')) ?? DeliveryProvider::None;

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'provider' => $provider,
            'settings' => $provider->isCalculated()
                ? Deliveries::fromInput($data['settings'] ?? [], $method?->settings)
                : null,
            // У способа со службой своей цены нет — цену привезёт расчёт.
            'price' => $provider->isCalculated() ? 0 : ($data['price'] ?? 0),
            'free_from' => $data['free_from'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort' => (int) ($data['sort'] ?? 500),
        ];
    }
}
