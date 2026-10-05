<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexor\Cms\Http\Requests\IblockSectionRequest;
use Nexor\Cms\Http\Resources\IblockPropertyResource;
use Nexor\Cms\Http\Resources\IblockSectionResource;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Models\IblockSectionValue;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\PropertyOptions;
use Nexor\Cms\Support\PropertyValues;
use Nexor\Cms\Support\Uploads;

class IblockSectionController extends ApiController
{
    public function index(Request $request, Iblock $iblock): AnonymousResourceCollection
    {
        abort_unless($iblock->has_sections, 404);

        $sections = $iblock->sections()
            ->withCount(['children'])
            ->when($request->filled('search'), fn ($query) => $query->where(
                'name', 'like', '%'.$request->string('search')->trim().'%',
            ))
            ->ordered()
            ->get();

        $totals = $this->elementTotals($iblock);

        // Счётчик раздела — вместе со всеми вложенными: у родителя, в котором
        // элементы лежат только по подразделам, иначе стоял бы ноль.
        $sections->each(fn (IblockSection $section) => $section->setAttribute(
            'elements_count',
            $totals[$section->id] ?? 0,
        ));

        return IblockSectionResource::collection($sections);
    }

    /**
     * Свойства разделов инфоблока — из них собирается вкладка формы раздела.
     */
    public function schema(Iblock $iblock): JsonResponse
    {
        $properties = $this->properties($iblock);

        return response()->json([
            'properties' => IblockPropertyResource::collection($properties),
            'options' => PropertyOptions::for($properties),
        ]);
    }

    public function show(Iblock $iblock, IblockSection $section): IblockSectionResource
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        return IblockSectionResource::make($section->loadCount('elements'))->additional([
            'properties' => PropertyValues::forForm($section),
            'property_descriptions' => PropertyValues::descriptionsForForm($section),
        ]);
    }

    public function store(IblockSectionRequest $request, Iblock $iblock): JsonResponse
    {
        $properties = $this->properties($iblock);
        $request->validate(PropertyValues::rules($properties));

        $section = new IblockSection($request->safe()->except(['picture', 'picture_remove']));
        $section->iblock_id = $iblock->id;
        $section->picture = Uploads::handle($request, 'picture', null, Nexor::directory('sections'));
        $section->save();

        PropertyValues::save($section, $properties, $request);

        ActivityLogger::created($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return IblockSectionResource::make($section)
            ->additional(['message' => 'Раздел «'.$section->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockSectionRequest $request, Iblock $iblock, IblockSection $section): JsonResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        $properties = $this->properties($iblock);
        $request->validate(PropertyValues::rules($properties));

        $section->fill($request->safe()->except(['picture', 'picture_remove']));
        $section->picture = Uploads::handle($request, 'picture', $section->picture, Nexor::directory('sections'));
        $section->save();

        PropertyValues::save($section, $properties, $request);

        ActivityLogger::updated($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return IblockSectionResource::make($section->fresh())
            ->additional(['message' => 'Раздел «'.$section->name.'» сохранён.'])
            ->response();
    }

    public function destroy(Iblock $iblock, IblockSection $section): JsonResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        $this->deletePropertyFiles($iblock, $section);
        Uploads::delete($section->picture);
        $section->delete();

        return $this->ok('Раздел и все вложенные разделы удалены.');
    }

    /**
     * Активные свойства разделов инфоблока.
     *
     * @return Collection<int, IblockProperty>
     */
    protected function properties(Iblock $iblock): Collection
    {
        return $iblock->sectionProperties()->active()->with('enums')->get();
    }

    /**
     * Сколько элементов в каждом разделе вместе с вложенными.
     *
     * Элемент считается один раз, даже если привязан к нескольким разделам
     * ветки: основной раздел лежит в самом элементе, дополнительные — в
     * таблице привязок.
     *
     * @return array<int, int> id раздела → число элементов
     */
    protected function elementTotals(Iblock $iblock): array
    {
        $paths = $iblock->sections()->pluck('path', 'id');

        $pairs = DB::table('iblock_elements')
            ->where('iblock_id', $iblock->id)
            ->whereNull('deleted_at')
            ->whereNotNull('section_id')
            ->get(['id as element_id', 'section_id'])
            ->concat(
                DB::table('iblock_element_section')
                    ->join('iblock_elements', 'iblock_elements.id', '=', 'iblock_element_section.element_id')
                    ->where('iblock_elements.iblock_id', $iblock->id)
                    ->whereNull('iblock_elements.deleted_at')
                    ->get(['iblock_element_section.element_id', 'iblock_element_section.section_id']),
            );

        /** @var array<int, array<int, true>> $members раздел → множество его элементов */
        $members = [];

        foreach ($pairs as $pair) {
            if (! isset($paths[$pair->section_id])) {
                continue;
            }

            // Элемент идёт в счёт своего раздела и всех его предков: их id лежат в пути раздела.
            $chain = array_filter(explode('/', (string) $paths[$pair->section_id]), 'strlen');
            $chain[] = $pair->section_id;

            foreach ($chain as $id) {
                $members[(int) $id][(int) $pair->element_id] = true;
            }
        }

        return array_map('count', $members);
    }

    /**
     * Файлы из свойств раздела и его вложенных разделов: строки значений
     * уберёт внешний ключ, а файлы в хранилище остались бы лежать.
     */
    protected function deletePropertyFiles(Iblock $iblock, IblockSection $section): void
    {
        $fileProperties = $iblock->sectionProperties()->get()
            ->filter(fn (IblockProperty $property) => $property->type->isFile())
            ->pluck('id');

        if ($fileProperties->isEmpty()) {
            return;
        }

        $sections = $section->descendants()->pluck('id')->push($section->id);

        IblockSectionValue::query()
            ->whereIn('section_id', $sections)
            ->whereIn('property_id', $fileProperties)
            ->pluck('value_string')
            ->each(fn (?string $path) => Uploads::delete($path));
    }
}
