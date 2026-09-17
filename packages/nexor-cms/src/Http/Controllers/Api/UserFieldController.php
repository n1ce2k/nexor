<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Requests\UserFieldRequest;
use Nexor\Cms\Http\Resources\UserFieldResource;
use Nexor\Cms\Models\UserField;
use Nexor\Cms\Support\ActivityLogger;

/**
 * Свои поля пользователей: их набор задаётся в админке.
 */
class UserFieldController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $fields = UserField::query()->withCount('values')->ordered()->get();

        return UserFieldResource::collection($fields);
    }

    /**
     * Типы, из которых можно собрать поле.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => array_map(fn (PropertyType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'group' => $type->group(),
            ], UserField::types()),
        ]);
    }

    public function show(UserField $field): UserFieldResource
    {
        return UserFieldResource::make($field->loadCount('values'));
    }

    public function store(UserFieldRequest $request): JsonResponse
    {
        $field = UserField::query()->create($this->attributes($request));

        ActivityLogger::created($field, 'Поле пользователя: '.$field->name);

        return UserFieldResource::make($field)
            ->additional(['message' => 'Поле «'.$field->name.'» создано.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UserFieldRequest $request, UserField $field): JsonResponse
    {
        $field->update($this->attributes($request));

        ActivityLogger::updated($field, 'Поле пользователя: '.$field->name);

        return UserFieldResource::make($field->fresh()->loadCount('values'))
            ->additional(['message' => 'Поле «'.$field->name.'» сохранено.'])
            ->response();
    }

    /**
     * Вместе с полем уходят и все его значения.
     */
    public function destroy(UserField $field): JsonResponse
    {
        ActivityLogger::deleted($field, 'Поле пользователя: '.$field->name);
        $field->delete();

        return $this->ok('Поле удалено.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(UserFieldRequest $request): array
    {
        $validated = $request->validated();
        $type = PropertyType::from($validated['type']);

        $allowed = match (true) {
            $type === PropertyType::Select => ['options'],
            $type->isFile() => ['max_size'],
            in_array($type, [PropertyType::String, PropertyType::Text, PropertyType::Html], true) => ['max_length'],
            default => [],
        };

        $settings = array_filter(
            Arr::only($validated['settings'] ?? [], $allowed),
            fn ($value) => $value !== null && $value !== '' && $value !== [],
        );

        return [
            ...Arr::except($validated, 'settings'),
            'settings' => $settings === [] ? null : $settings,
        ];
    }
}
