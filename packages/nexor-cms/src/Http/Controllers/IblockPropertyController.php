<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Requests\IblockPropertyRequest;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\ActivityLogger;

class IblockPropertyController extends Controller
{
    public function index(Iblock $iblock): View
    {
        $properties = $iblock->properties()->withCount('values')->with('enums')->get();

        return view('nexor::admin.iblocks.properties.index', compact('iblock', 'properties'));
    }

    public function create(Iblock $iblock): View
    {
        return view('nexor::admin.iblocks.properties.form', [
            'iblock' => $iblock,
            'property' => new IblockProperty([
                'type' => PropertyType::String,
                'is_active' => true,
                'sort' => ($iblock->properties()->max('sort') ?? 0) + 100,
            ]),
            'linkableIblocks' => $this->linkableIblocks(),
        ]);
    }

    public function store(IblockPropertyRequest $request, Iblock $iblock): RedirectResponse
    {
        $property = new IblockProperty($this->attributes($request));
        $property->iblock_id = $iblock->id;
        $property->save();

        $this->syncEnums($request, $property);

        ActivityLogger::created($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.properties.index', $iblock)
            ->with('success', 'Свойство «'.$property->name.'» создано.');
    }

    public function edit(Iblock $iblock, IblockProperty $property): View
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        return view('nexor::admin.iblocks.properties.form', [
            'iblock' => $iblock,
            'property' => $property->load('enums'),
            'linkableIblocks' => $this->linkableIblocks(),
        ]);
    }

    public function update(IblockPropertyRequest $request, Iblock $iblock, IblockProperty $property): RedirectResponse
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        $property->fill($this->attributes($request));
        $property->save();

        $this->syncEnums($request, $property);

        ActivityLogger::updated($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.properties.index', $iblock)
            ->with('success', 'Свойство «'.$property->name.'» сохранено.');
    }

    public function destroy(Iblock $iblock, IblockProperty $property): RedirectResponse
    {
        abort_unless($property->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($property, "Свойство «{$property->name}» инфоблока «{$iblock->name}»");
        $property->delete();

        return redirect()->route('admin.iblocks.properties.index', $iblock)
            ->with('success', 'Свойство удалено вместе со всеми значениями.');
    }

    /**
     * Persist a new order for the property list, sent by the drag handles.
     */
    public function reorder(Request $request, Iblock $iblock): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        DB::transaction(function () use ($validated, $iblock): void {
            foreach ($validated['order'] as $index => $id) {
                $iblock->properties()->whereKey($id)->update(['sort' => ($index + 1) * 100]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Validated attributes with type-irrelevant settings dropped.
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

    /**
     * @return Collection<int, Iblock>
     */
    protected function linkableIblocks(): Collection
    {
        return Iblock::query()->ordered()->get();
    }
}
