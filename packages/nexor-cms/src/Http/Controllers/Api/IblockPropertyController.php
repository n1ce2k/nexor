<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Requests\IblockPropertyRequest;
use Nexor\Cms\Http\Resources\IblockPropertyResource;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\ActivityLogger;

class IblockPropertyController extends ApiController
{
    public function index(Iblock $iblock): AnonymousResourceCollection
    {
        return IblockPropertyResource::collection(
            $iblock->properties()->with('enums')->withCount('values')->get(),
        );
    }

    public function show(Iblock $iblock, IblockProperty $property): IblockPropertyResource
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        return IblockPropertyResource::make($property->load('enums')->loadCount('values'));
    }

    public function store(IblockPropertyRequest $request, Iblock $iblock): JsonResponse
    {
        $property = new IblockProperty($this->attributes($request));
        $property->iblock_id = $iblock->id;
        $property->save();

        $this->syncEnums($request, $property);

        ActivityLogger::created($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");

        return IblockPropertyResource::make($property->load('enums'))
            ->additional(['message' => 'Свойство «'.$property->name.'» создано.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockPropertyRequest $request, Iblock $iblock, IblockProperty $property): JsonResponse
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        $property->fill($this->attributes($request));
        $property->save();

        $this->syncEnums($request, $property);

        ActivityLogger::updated($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");

        return IblockPropertyResource::make($property->fresh('enums'))
            ->additional(['message' => 'Свойство «'.$property->name.'» сохранено.'])
            ->response();
    }

    public function destroy(Iblock $iblock, IblockProperty $property): JsonResponse
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");
        $property->delete();

        return $this->ok('Свойство удалено вместе со всеми значениями.');
    }

    /**
     * Validated attributes with settings that the chosen type does not use dropped.
     *
     * @return array<string, mixed>
     */
    protected function attributes(IblockPropertyRequest $request): array
    {
        $data = $request->safe()->except(['enums', 'settings']);

        $type = PropertyType::from($data['type']);
        $settings = array_filter(
            $request->input('settings', []),
            fn ($value, $key) => in_array($key, $type->settingKeys(), true) && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        $data['settings'] = $settings ?: null;

        return $data;
    }

    protected function syncEnums(IblockPropertyRequest $request, IblockProperty $property): void
    {
        if (! $property->type->usesEnums()) {
            $property->enums()->delete();

            return;
        }

        $rows = collect($request->input('enums', []))
            ->filter(fn (array $row) => filled($row['value'] ?? null))
            ->values();

        $property->enums()->whereNotIn('id', $rows->pluck('id')->filter()->all())->delete();

        $rows->each(function (array $row, int $index) use ($property): void {
            $attributes = [
                'value' => $row['value'],
                'code' => $row['code'] ?? null,
                'sort' => $row['sort'] ?? ($index + 1) * 100,
                'is_default' => (bool) ($row['is_default'] ?? false),
            ];

            if (filled($row['id'] ?? null)) {
                $property->enums()->whereKey($row['id'])->update($attributes);

                return;
            }

            $property->enums()->create($attributes);
        });
    }
}
