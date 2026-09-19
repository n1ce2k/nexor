<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Requests\IblockElementRequest;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\PropertyValues;
use Nexor\Cms\Support\Uploads;

class IblockElementController extends Controller
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'sort', 'created_at', 'updated_at'];

    public function index(Request $request, Iblock $iblock): View
    {
        $properties = $iblock->properties()->active()->get();
        $listProperties = $properties->where('is_shown_in_list', true);
        $filterProperties = $properties->where('is_filterable', true);

        $sort = in_array($request->get('sort'), self::SORTABLE, true) ? $request->get('sort') : 'sort';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';

        $elements = $iblock->elements()
            ->with(['section', 'values.property', 'values.enum'])
            ->when($request->filled('search'), fn (Builder $query) => $query->where(
                fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('code', 'like', '%'.$request->string('search')->trim().'%'),
            ))
            ->when($request->filled('section'), fn (Builder $query) => $query->where('section_id', $request->integer('section')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->get('status') === 'active'))
            ->tap(fn (Builder $query) => $this->applyPropertyFilters($query, $filterProperties, $request))
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $sections = $iblock->has_sections ? $iblock->sections()->ordered()->get() : collect();

        return view('nexor::admin.iblocks.elements.index', compact(
            'iblock', 'elements', 'sections', 'listProperties', 'filterProperties',
        ));
    }

    public function create(Request $request, Iblock $iblock): View
    {
        return view('nexor::admin.iblocks.elements.form', [
            'iblock' => $iblock,
            'element' => new IblockElement([
                'is_active' => true,
                'sort' => 500,
                'section_id' => $request->integer('section') ?: null,
                'detail_text_type' => 'html',
                'preview_text_type' => 'text',
            ]),
            'properties' => $this->formProperties($iblock),
            'sections' => $iblock->has_sections ? $iblock->sections()->ordered()->get() : collect(),
            'values' => [],
            'descriptions' => [],
            'selectedSections' => [],
        ]);
    }

    public function store(IblockElementRequest $request, Iblock $iblock): RedirectResponse
    {
        $element = new IblockElement($this->baseAttributes($request));
        $element->iblock_id = $iblock->id;
        $element->created_by = $request->user()->id;
        $element->updated_by = $request->user()->id;
        $element->preview_picture = Uploads::handle($request, 'preview_picture', null, 'elements');
        $element->detail_picture = Uploads::handle($request, 'detail_picture', null, 'elements');
        $element->save();

        $element->sections()->sync($request->input('sections', []));
        PropertyValues::save($element, $request->properties(), $request);

        ActivityLogger::created($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.elements.index', $iblock)
            ->with('success', 'Элемент «'.$element->name.'» создан.');
    }

    public function show(Iblock $iblock, IblockElement $element): RedirectResponse
    {
        return redirect()->route('admin.iblocks.elements.edit', [$iblock, $element]);
    }

    public function edit(Iblock $iblock, IblockElement $element): View
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        return view('nexor::admin.iblocks.elements.form', [
            'iblock' => $iblock,
            'element' => $element,
            'properties' => $this->formProperties($iblock),
            'sections' => $iblock->has_sections ? $iblock->sections()->ordered()->get() : collect(),
            'values' => PropertyValues::forForm($element),
            'descriptions' => PropertyValues::descriptionsForForm($element),
            'selectedSections' => $element->sections->pluck('id')->all(),
        ]);
    }

    public function update(IblockElementRequest $request, Iblock $iblock, IblockElement $element): RedirectResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        $element->fill($this->baseAttributes($request));
        $element->updated_by = $request->user()->id;
        $element->preview_picture = Uploads::handle($request, 'preview_picture', $element->preview_picture, 'elements');
        $element->detail_picture = Uploads::handle($request, 'detail_picture', $element->detail_picture, 'elements');
        $element->save();

        $element->sections()->sync($request->input('sections', []));
        PropertyValues::save($element, $request->properties(), $request);

        ActivityLogger::updated($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.elements.index', $iblock)
            ->with('success', 'Элемент «'.$element->name.'» сохранён.');
    }

    public function destroy(Iblock $iblock, IblockElement $element): RedirectResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");
        $element->delete();

        return redirect()->route('admin.iblocks.elements.index', $iblock)
            ->with('success', 'Элемент удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseAttributes(IblockElementRequest $request): array
    {
        return $request->safe()->only([
            'name', 'code', 'section_id',
            'preview_text', 'preview_text_type', 'detail_text', 'detail_text_type',
            'is_active', 'sort', 'active_from', 'active_to',
            'meta_title', 'meta_description', 'meta_keywords',
        ]);
    }

    /**
     * Active properties with everything the form needs to render their editors.
     *
     * @return Collection<int, IblockProperty>
     */
    protected function formProperties(Iblock $iblock): Collection
    {
        $properties = $iblock->properties()->active()->with('enums')->get();

        // Element and section pickers need the options of the infoblock they link to.
        $linkedIds = $properties
            ->filter(fn (IblockProperty $p) => in_array($p->type, [PropertyType::Element, PropertyType::Section], true))
            ->map(fn (IblockProperty $p) => $p->setting('link_iblock_id'))
            ->filter()
            ->unique();

        $linkedElements = $linkedIds->isEmpty()
            ? collect()
            : IblockElement::query()->whereIn('iblock_id', $linkedIds)->ordered()->get()->groupBy('iblock_id');

        $linkedSections = $linkedIds->isEmpty()
            ? collect()
            : IblockSection::query()->whereIn('iblock_id', $linkedIds)->ordered()->get()->groupBy('iblock_id');

        return $properties->each(function (IblockProperty $property) use ($linkedElements, $linkedSections): void {
            $linkId = $property->setting('link_iblock_id');

            $property->setRelation('linkOptions', match ($property->type) {
                PropertyType::Element => $linkedElements->get($linkId, collect()),
                PropertyType::Section => $linkedSections->get($linkId, collect()),
                default => collect(),
            });
        });
    }

    /**
     * Narrow the element list by the filterable properties present in the query string.
     *
     * @param  Builder<IblockElement>  $query
     * @param  Collection<int, IblockProperty>  $properties
     */
    protected function applyPropertyFilters(Builder $query, Collection $properties, Request $request): void
    {
        foreach ($properties as $property) {
            $value = $request->input('prop.'.$property->code);

            if (! filled($value)) {
                continue;
            }

            $query->whereHas('values', function (Builder $q) use ($property, $value): void {
                $q->where('property_id', $property->id);

                $column = $property->storageColumn();

                $column === 'value_string'
                    ? $q->where($column, 'like', '%'.$value.'%')
                    : $q->where($column, $value);
            });
        }
    }
}
