<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Enums\PromocodeType;
use Nexor\Shop\Http\Requests\PromocodeRequest;
use Nexor\Shop\Models\Promocode;
use Nexor\Shop\Support\Shop;

/**
 * Промокоды корзины Ultimate.
 */
class PromocodeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $promocodes = Promocode::query()
            ->when($request->filled('search'), fn ($query) => $query->where('code', 'like', '%'.Promocode::normalize((string) $request->input('search')).'%'))
            ->latest('id')
            ->paginate($this->perPage($request));

        return response()->json([
            'data' => $promocodes->getCollection()->map($this->describe(...)),
            'meta' => [
                'current_page' => $promocodes->currentPage(),
                'last_page' => $promocodes->lastPage(),
                'total' => $promocodes->total(),
            ],
            'types' => PromocodeType::options(),
            'scopes' => PromocodeScope::options(),
            // Промокоды работают только в корзине Ultimate — предупреждаем, если выбрана Basic.
            'active_in_cart' => Shop::promocodesEnabled(),
            'currency_symbol' => Shop::currency()->symbol(),
        ]);
    }

    public function store(PromocodeRequest $request): JsonResponse
    {
        $promocode = Promocode::query()->create($request->payload());

        ActivityLogger::created($promocode, 'Промокод «'.$promocode->code.'»');

        return response()->json(['data' => $this->describe($promocode), 'message' => 'Промокод «'.$promocode->code.'» создан.'], 201);
    }

    public function update(PromocodeRequest $request, Promocode $promocode): JsonResponse
    {
        $promocode->update($request->payload());

        ActivityLogger::updated($promocode, 'Промокод «'.$promocode->code.'»');

        return response()->json(['data' => $this->describe($promocode), 'message' => 'Промокод «'.$promocode->code.'» сохранён.']);
    }

    public function destroy(Promocode $promocode): JsonResponse
    {
        ActivityLogger::deleted($promocode, 'Промокод «'.$promocode->code.'»');

        // Заказы хранят сам код строкой — удаление промокода их не меняет.
        $promocode->delete();

        return $this->ok('Промокод удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(Promocode $promocode): array
    {
        return [
            'id' => $promocode->id,
            'code' => $promocode->code,
            'description' => $promocode->description,
            'type' => $promocode->type->value,
            'type_label' => $promocode->type->label(),
            'value' => $promocode->value,
            'min_sum' => $promocode->min_sum,
            'starts_at' => $promocode->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $promocode->ends_at?->format('Y-m-d\TH:i'),
            'usage_limit' => $promocode->usage_limit,
            'used_count' => $promocode->used_count,
            'scope' => $promocode->scope->value,
            'scope_label' => $promocode->scope->label(),
            'section_ids' => array_map('intval', $promocode->section_ids ?? []),
            'element_ids' => array_map('intval', $promocode->element_ids ?? []),
            'targets' => match ($promocode->scope) {
                PromocodeScope::Sections => IblockSection::query()->whereKey($promocode->section_ids ?? [])->pluck('name')->all(),
                PromocodeScope::Elements => IblockElement::query()->whereKey($promocode->element_ids ?? [])->pluck('name')->all(),
                default => [],
            },
            'is_active' => $promocode->is_active,
            'is_exhausted' => $promocode->isExhausted(),
            'is_expired' => (bool) $promocode->ends_at?->isPast(),
        ];
    }
}
