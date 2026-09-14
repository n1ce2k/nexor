<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Http\Requests\DeliveryMethodRequest;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Support\Shop;

class DeliveryMethodController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => DeliveryMethod::query()->ordered()->get(),
            'currency_symbol' => Shop::currency()->symbol(),
        ]);
    }

    public function store(DeliveryMethodRequest $request): JsonResponse
    {
        $method = DeliveryMethod::query()->create($request->validated());

        ActivityLogger::created($method, 'Способ доставки «'.$method->name.'»');

        return response()->json(['data' => $method, 'message' => 'Способ доставки «'.$method->name.'» добавлен.'], 201);
    }

    public function update(DeliveryMethodRequest $request, DeliveryMethod $deliveryMethod): JsonResponse
    {
        $deliveryMethod->update($request->validated());

        ActivityLogger::updated($deliveryMethod, 'Способ доставки «'.$deliveryMethod->name.'»');

        return response()->json(['data' => $deliveryMethod, 'message' => 'Способ доставки «'.$deliveryMethod->name.'» сохранён.']);
    }

    public function destroy(DeliveryMethod $deliveryMethod): JsonResponse
    {
        ActivityLogger::deleted($deliveryMethod, 'Способ доставки «'.$deliveryMethod->name.'»');
        $deliveryMethod->delete();

        return $this->ok('Способ доставки удалён.');
    }
}
