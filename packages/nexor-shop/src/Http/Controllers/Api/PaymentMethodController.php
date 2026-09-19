<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Enums\PaymentProvider;
use Nexor\Shop\Http\Requests\PaymentMethodRequest;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Support\Payments\PaymentException;
use Nexor\Shop\Support\Payments\Payments;

class PaymentMethodController extends ApiController
{
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::query()->ordered()->get()->map(fn (PaymentMethod $method) => $this->payload($method));

        return response()->json([
            'data' => $methods,
            'providers' => PaymentProvider::options(),
        ]);
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $method = PaymentMethod::query()->create($this->attributes($request, null));

        ActivityLogger::created($method, 'Способ оплаты «'.$method->name.'»');

        return response()->json(['data' => $this->payload($method), 'message' => 'Способ оплаты «'.$method->name.'» добавлен.'], 201);
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($this->attributes($request, $paymentMethod));

        ActivityLogger::updated($paymentMethod, 'Способ оплаты «'.$paymentMethod->name.'»');

        return response()->json(['data' => $this->payload($paymentMethod), 'message' => 'Способ оплаты «'.$paymentMethod->name.'» сохранён.']);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        ActivityLogger::deleted($paymentMethod, 'Способ оплаты «'.$paymentMethod->name.'»');
        $paymentMethod->delete();

        return $this->ok('Способ оплаты удалён.');
    }

    /**
     * Проверяет ключи запросом к провайдеру — теми, что сейчас в форме.
     */
    public function check(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $input = $request->validate([
            'shop_id' => ['nullable', 'string', 'max:64'],
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        // Проверяем несохранённое: иначе пришлось бы сохранять заведомо
        // неверные ключи, чтобы узнать, что они неверные.
        $probe = clone $paymentMethod;
        $probe->settings = Payments::fromInput($input, $paymentMethod->settings);

        try {
            Payments::client($probe)->ping();
        } catch (PaymentException $exception) {
            return $this->refuse($exception->getMessage());
        }

        return $this->ok('ЮKassa приняла ключи — можно принимать оплату.');
    }

    /**
     * Что из способа оплаты уходит в панель: секретный ключ — маской.
     *
     * @return array<string, mixed>
     */
    protected function payload(PaymentMethod $method): array
    {
        $provider = $method->provider ?? PaymentProvider::None;

        return [
            'id' => $method->id,
            'name' => $method->name,
            'description' => $method->description,
            'provider' => $provider->value,
            'provider_label' => $provider->label(),
            'is_online' => $provider->isOnline(),
            'is_ready' => Payments::ready($method),
            'settings' => Payments::forPanel($method),
            'is_active' => $method->is_active,
            'sort' => $method->sort,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(PaymentMethodRequest $request, ?PaymentMethod $method): array
    {
        $data = $request->validated();
        $provider = PaymentProvider::from($data['provider']);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'provider' => $provider,
            'settings' => $provider->isOnline()
                ? Payments::fromInput($data['settings'] ?? [], $method?->settings)
                : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort' => (int) ($data['sort'] ?? 500),
        ];
    }
}
