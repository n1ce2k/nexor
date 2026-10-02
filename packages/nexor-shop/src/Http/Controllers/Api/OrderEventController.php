<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Shop\Models\OrderEvent;
use Nexor\Shop\Models\OrderEventRead;

/**
 * Уведомления панели: что случилось с заказами, пока человека не было.
 *
 * Каждый видит события с последнего раза: отметка ведётся на человека, а не
 * на событие, поэтому одно и то же не всплывает дважды.
 */
class OrderEventController extends ApiController
{
    /** Сколько попапов показать разом — остальное уходит в «ещё N». */
    public const SHOWN = 5;

    /** Новичку показываем не всю историю, а только свежее. */
    public const FIRST_DAYS = 3;

    public function index(Request $request): JsonResponse
    {
        $seen = OrderEventRead::query()->find($request->user()->getKey())?->last_event_id;

        $query = OrderEvent::query()
            ->when(
                $seen !== null,
                fn ($query) => $query->where('id', '>', $seen),
                fn ($query) => $query->where('created_at', '>=', now()->subDays(self::FIRST_DAYS)),
            );

        $total = (clone $query)->count();

        $events = $query->with('order:id,number')
            ->latest('id')
            ->limit(self::SHOWN)
            ->get();

        return response()->json([
            'data' => $events->map(fn (OrderEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'order_id' => $event->order_id,
                'number' => $event->order?->number,
                'previous' => $event->previous,
                'current' => $event->current,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values(),
            'more' => max(0, $total - $events->count()),
            'last_id' => (int) ($events->max('id') ?? 0),
        ]);
    }

    /**
     * Человек увидел события до этого номера включительно.
     */
    public function seen(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['id' => ['required', 'integer', 'min:1']])['id'];

        $read = OrderEventRead::query()->firstOrNew(['user_id' => $request->user()->getKey()]);

        // Назад отметка не двигается: две вкладки панели не перебивают друг друга.
        $read->last_event_id = max((int) $read->last_event_id, $id);
        $read->save();

        return response()->json(['last_id' => $read->last_event_id]);
    }
}
