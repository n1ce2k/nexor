<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Enums\OrderFieldType;
use Nexor\Shop\Http\Requests\OrderFieldRequest;
use Nexor\Shop\Models\OrderField;

/**
 * Поля формы заказа: добавить, переименовать, убрать, переставить.
 */
class OrderFieldController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OrderField::query()->ordered()->get()->map($this->describe(...)),
            'types' => OrderFieldType::options(),
        ]);
    }

    public function store(OrderFieldRequest $request): JsonResponse
    {
        $field = OrderField::query()->create($request->validated() + [
            'sort' => (int) OrderField::query()->max('sort') + 100,
        ]);

        ActivityLogger::created($field, 'Поле заказа «'.$field->name.'»');

        return response()->json(['data' => $this->describe($field), 'message' => 'Поле «'.$field->name.'» добавлено.'], 201);
    }

    public function update(OrderFieldRequest $request, OrderField $field): JsonResponse
    {
        $field->update($request->validated());

        ActivityLogger::updated($field, 'Поле заказа «'.$field->name.'»');

        return response()->json(['data' => $this->describe($field), 'message' => 'Поле «'.$field->name.'» сохранено.']);
    }

    /**
     * Порядок полей целиком: `ids` в нужной последовательности.
     */
    public function sort(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:shop_order_fields,id'],
        ])['ids'];

        foreach (array_values($ids) as $index => $id) {
            OrderField::query()->whereKey($id)->update(['sort' => ($index + 1) * 100]);
        }

        return $this->ok('Порядок полей сохранён.');
    }

    public function destroy(OrderField $field): JsonResponse
    {
        ActivityLogger::deleted($field, 'Поле заказа «'.$field->name.'»');

        // Старые заказы хранят свой снимок полей — удаление их не трогает.
        $field->delete();

        return $this->ok('Поле удалено.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(OrderField $field): array
    {
        return [
            'id' => $field->id,
            'code' => $field->code,
            'name' => $field->name,
            'type' => $field->type->value,
            'type_label' => $field->type->label(),
            'is_required' => $field->is_required,
            'is_active' => $field->is_active,
            'sort' => $field->sort,
        ];
    }
}
