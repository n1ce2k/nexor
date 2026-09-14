<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\OrderStatus;
use Nexor\Shop\Http\Resources\OrderResource;
use Nexor\Shop\Models\Order;

/**
 * Раздел «Заказы».
 */
class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->withCount('items')
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(fn (Builder $q) => $q->where('number', 'like', $search)->orWhere('customer', 'like', $search));
            })
            ->latest('id')
            ->paginate($this->perPage($request));

        return OrderResource::collection($orders)
            ->additional(['statuses' => OrderStatus::options(), 'checkout' => Nexor::feature('shop.checkout')])
            ->response();
    }

    public function show(Order $order): JsonResponse
    {
        return OrderResource::make($order->load('items'))
            ->additional(['statuses' => OrderStatus::options(), 'checkout' => Nexor::feature('shop.checkout')])
            ->response();
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'manager_comment' => ['nullable', 'string', 'max:5000'],
        ], [], ['status' => 'статус', 'manager_comment' => 'комментарий менеджера']);

        $order->update($data);

        ActivityLogger::updated($order, 'Заказ №'.$order->number);

        return OrderResource::make($order->load('items'))
            ->additional(['message' => 'Заказ №'.$order->number.' сохранён.'])
            ->response();
    }

    public function destroy(Order $order): JsonResponse
    {
        ActivityLogger::deleted($order, 'Заказ №'.$order->number);
        $order->delete();

        return $this->ok('Заказ удалён.');
    }
}
