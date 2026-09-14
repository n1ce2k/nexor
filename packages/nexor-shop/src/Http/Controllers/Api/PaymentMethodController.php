<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Http\Requests\PaymentMethodRequest;
use Nexor\Shop\Models\PaymentMethod;

class PaymentMethodController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::query()->ordered()->get()]);
    }

    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $method = PaymentMethod::query()->create($request->validated());

        ActivityLogger::created($method, 'Способ оплаты «'.$method->name.'»');

        return response()->json(['data' => $method, 'message' => 'Способ оплаты «'.$method->name.'» добавлен.'], 201);
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($request->validated());

        ActivityLogger::updated($paymentMethod, 'Способ оплаты «'.$paymentMethod->name.'»');

        return response()->json(['data' => $paymentMethod, 'message' => 'Способ оплаты «'.$paymentMethod->name.'» сохранён.']);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        ActivityLogger::deleted($paymentMethod, 'Способ оплаты «'.$paymentMethod->name.'»');
        $paymentMethod->delete();

        return $this->ok('Способ оплаты удалён.');
    }
}
