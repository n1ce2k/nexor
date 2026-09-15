<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Enums\ProductType;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Http\Requests\IblockElementRequest;
use Nexor\Cms\Http\Resources\IblockElementResource;
use Nexor\Cms\Http\Resources\IblockPropertyResource;
use Nexor\Cms\Http\Resources\IblockResource;
use Nexor\Cms\Http\Resources\IblockSectionResource;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\ElementFormLayout;
use Nexor\Cms\Support\Modules\ModuleFields;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\PropertyValues;
use Nexor\Cms\Support\Uploads;

class IblockElementController extends ApiController
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'code', 'sort', 'created_at', 'updated_at'];

    public function index(Request $request, Iblock $iblock): AnonymousResourceCollection
    {
        [$column, $direction] = $this->sorting($request, self::SORTABLE, 'sort');

        $properties = $iblock->properties()->active()->get();

        $elements = $iblock->elements()
            ->with(['section', 'values.property', 'values.enum', 'catalog'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(fn (Builder $q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->when($request->filled('section'), fn (Builder $q) => $request->get('section') === 'none'
                ? $q->whereNull('section_id')
                : $q->where('section_id', $request->integer('section')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('is_active', $request->get('status') === 'active'))
            ->tap(fn (Builder $q) => $this->applyPropertyFilters($q, $properties->where('is_filterable', true), $request))
            ->orderBy($column, $direction)
            ->orderBy('name')
            ->paginate($this->perPage($request, 'elements'));

        // Display columns are computed from the iblock's properties.
        $elements->getCollection()->each(fn (IblockElement $element) => $element->setRelation(
            'iblock',
            $iblock->setRelation('properties', $properties),
        ));

        return IblockElementResource::collection($elements);
    }

    public function show(Iblock $iblock, IblockElement $element): IblockElementResource
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        return IblockElementResource::make(
            $element->load(['section', 'sections', 'creator', 'editor', 'values.property', 'values.enum', 'catalog']),
        )->additional([
            // Значения полей модулей для формы: `modules.pagebuilder.content`.
            'modules' => ModuleFields::elementValues($element->setRelation('iblock', $iblock)),
        ]);
    }

    public function store(IblockElementRequest $request, Iblock $iblock): JsonResponse
    {
        $element = new IblockElement($this->attributes($request));
        $element->iblock_id = $iblock->id;
        $element->created_by = $request->user()->id;
        $element->updated_by = $request->user()->id;
        $this->handlePictures($request, $element);
        $element->save();

        $element->sections()->sync($request->input('sections', []));
        PropertyValues::save($element, $request->properties(), $request);
        $this->saveCatalog($request, $iblock, $element);
        ModuleFields::saveElement($iblock, $element, $request->validated());

        ActivityLogger::created($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");

        return IblockElementResource::make($element->load(['section', 'sections', 'values.property', 'values.enum', 'catalog']))
            ->additional(['message' => 'Элемент «'.$element->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockElementRequest $request, Iblock $iblock, IblockElement $element): JsonResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        $element->fill($this->attributes($request));
        $element->updated_by = $request->user()->id;
        $this->handlePictures($request, $element);
        $element->save();

        $element->sections()->sync($request->input('sections', []));
        PropertyValues::save($element, $request->properties(), $request);
        $this->saveCatalog($request, $iblock, $element);
        ModuleFields::saveElement($iblock, $element, $request->validated());

        ActivityLogger::updated($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");

        return IblockElementResource::make(
            $element->fresh(['section', 'sections', 'values.property', 'values.enum', 'catalog']),
        )->additional(['message' => 'Элемент «'.$element->name.'» сохранён.'])->response();
    }

    public function destroy(Iblock $iblock, IblockElement $element): JsonResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($element, "Элемент «{$element->name}» инфоблока «{$iblock->name}»");
        $element->delete();

        return $this->ok('Элемент удалён.');
    }

    /**
     * Field definitions the Vue element form renders itself from.
     *
     * The panel never hardcodes a form: it asks for the schema and maps each
     * entry onto a registered field component.
     */
    public function schema(Iblock $iblock): JsonResponse
    {
        $properties = $iblock->properties()->active()->with('enums')->get();

        return response()->json([
            'iblock' => IblockResource::make($iblock->load('type')),
            'sections' => IblockSectionResource::collection(
                $iblock->has_sections ? $iblock->sections()->ordered()->get() : collect(),
            ),
            'properties' => IblockPropertyResource::collection($properties),
            'options' => $this->linkOptions($properties),
            'form_tabs' => ElementFormLayout::for($iblock),
            'form_fields' => array_values(ElementFormLayout::fields($iblock)),
            'measures' => CatalogProduct::MEASURES,
            'currencies' => Currency::options(),
            'product_types' => ProductType::options(),
        ]);
    }

    /**
     * Rearranges the element form of this infoblock.
     *
     * An empty `tabs` array means "back to the defaults".
     */
    public function saveLayout(Request $request, Iblock $iblock): JsonResponse
    {
        $data = $request->validate([
            'tabs' => ['present', 'array'],
            'tabs.*.key' => ['nullable', 'string', 'max:50'],
            'tabs.*.label' => ['required', 'string', 'max:100'],
            'tabs.*.fields' => ['present', 'array'],
            'tabs.*.fields.*' => ['string', 'max:120'],
        ]);

        $tabs = ElementFormLayout::store($iblock, $data['tabs']);

        ActivityLogger::updated($iblock, 'Форма элементов перенастроена');

        return response()->json([
            'tabs' => $tabs,
            'message' => 'Форма элементов сохранена.',
        ]);
    }

    /**
     * Choices for properties that point at another infoblock or at users.
     *
     * @param  Collection<int, IblockProperty>  $properties
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    protected function linkOptions(Collection $properties): array
    {
        $options = [];

        foreach ($properties as $property) {
            $options[$property->code] = match ($property->type) {
                PropertyType::Element => $this->pluckOptions(
                    IblockElement::query()->where('iblock_id', $property->setting('link_iblock_id'))->ordered(),
                ),
                PropertyType::Section => $this->pluckOptions(
                    IblockSection::query()->where('iblock_id', $property->setting('link_iblock_id'))->ordered(),
                ),
                PropertyType::User => $this->pluckOptions(
                    Nexor::newUser()->newQuery()->where('is_active', true)->orderBy('name'),
                ),
                default => null,
            };

            if ($options[$property->code] === null) {
                unset($options[$property->code]);
            }
        }

        return $options;
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function pluckOptions(Builder $query): array
    {
        return $query->limit(500)->get()->map(fn ($model) => [
            'value' => $model->getKey(),
            'label' => $model->name,
        ])->all();
    }

    /**
     * Торговые предложения товара — для вкладки «Предложения».
     */
    public function offers(Request $request, Iblock $iblock, IblockElement $element): JsonResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        $offersIblock = $iblock->offersIblock;

        abort_unless($iblock->hasOffers() && $offersIblock, 404);

        // Права на предложения — свои, даже если их копируют с каталога.
        abort_unless($request->user()->hasPermission($offersIblock->permissionCode('view')), 403);

        $offers = IblockElement::query()
            ->where('iblock_id', $offersIblock->id)
            ->whereHas('catalog', fn (Builder $query) => $query->where('parent_element_id', $element->id))
            ->with(['catalog', 'values.property', 'values.enum'])
            ->ordered()
            ->get();

        return response()->json([
            'offers_iblock' => IblockResource::make($offersIblock),
            // Свойства предложений — из них выбирают колонки таблицы и попапа.
            'properties' => IblockPropertyResource::collection(
                $offersIblock->properties()->active()->with('enums')->get(),
            ),
            'data' => IblockElementResource::collection($offers),
        ]);
    }

    /**
     * Привязывает к товару уже существующие предложения — «выбрать» в попапе.
     *
     * Предложение живёт своей жизнью в инфоблоке предложений, привязка — это
     * всего лишь его `parent_element_id`, поэтому переносить его от одного
     * товара к другому можно сколько угодно.
     */
    public function attachOffers(Request $request, Iblock $iblock, IblockElement $element): JsonResponse
    {
        abort_unless($element->iblock_id === $iblock->id, 404);

        $offersIblock = $iblock->offersIblock;

        abort_unless($iblock->hasOffers() && $offersIblock, 404);

        // Привязка меняет сами предложения — нужно право на их инфоблок.
        abort_unless($request->user()->hasPermission($offersIblock->permissionCode('update')), 403);

        $data = $request->validate([
            'offers' => ['required', 'array', 'min:1'],
            'offers.*' => [
                'integer',
                Rule::exists('iblock_elements', 'id')
                    ->where('iblock_id', $offersIblock->id)
                    ->whereNull('deleted_at'),
            ],
        ], [], ['offers' => 'предложения', 'offers.*' => 'предложение']);

        $offers = IblockElement::query()->whereKey($data['offers'])->get();

        $offers->each(fn (IblockElement $offer) => $offer->catalog()->updateOrCreate([], [
            'parent_element_id' => $element->id,
        ]));

        // Товар, у которого появились предложения, своей цены больше не имеет.
        $element->catalog()->updateOrCreate([], ['type' => ProductType::WithOffers->value]);

        ActivityLogger::updated($element, "Предложения товара «{$element->name}»");

        return $this->ok('Предложений привязано: '.$offers->count().'.');
    }

    /**
     * Цена, скидка и остатки элемента торгового каталога.
     *
     * Трогаем только если форма их прислала: клиент API, который правит одно
     * название, не должен случайно обнулить цену.
     */
    protected function saveCatalog(IblockElementRequest $request, Iblock $iblock, IblockElement $element): void
    {
        if (! $iblock->hasCommerce() || (! $request->has('catalog') && ! $request->has('parent_element_id'))) {
            return;
        }

        $data = (array) ($request->validated('catalog') ?? []);
        $current = $element->catalog;

        $values = [
            'type' => filled($data['type'] ?? null) ? $data['type'] : ($current?->type?->value ?? ProductType::Simple->value),
            'price' => array_key_exists('price', $data) ? $data['price'] : $current?->price,
            'currency' => filled($data['currency'] ?? null) ? $data['currency'] : ($current?->currency?->value ?? Currency::RUB->value),
            'discount_percent' => $data['discount_percent'] ?? $current?->discount_percent ?? 0,
            'quantity' => $data['quantity'] ?? $current?->quantity ?? 0,
            'measure' => filled($data['measure'] ?? null) ? $data['measure'] : ($current?->measure ?? 'шт'),
            'ratio' => $data['ratio'] ?? $current?->ratio ?? 1,
            'quantity_trace' => (bool) ($data['quantity_trace'] ?? $current?->quantity_trace ?? false),
            'can_buy_zero' => (bool) ($data['can_buy_zero'] ?? $current?->can_buy_zero ?? false),
            'offers_by_properties' => (bool) ($data['offers_by_properties'] ?? $current?->offers_by_properties ?? true),
        ];

        if ($iblock->product_iblock_id && $request->has('parent_element_id')) {
            $values['parent_element_id'] = $request->validated('parent_element_id');
        }

        $element->catalog()->updateOrCreate([], $values);
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(IblockElementRequest $request): array
    {
        return $request->safe()->only([
            'name', 'code', 'section_id',
            'preview_text', 'preview_text_type', 'detail_text', 'detail_text_type',
            'is_active', 'sort', 'active_from', 'active_to',
            'meta_title', 'meta_description', 'meta_keywords',
        ]);
    }

    protected function handlePictures(IblockElementRequest $request, IblockElement $element): void
    {
        $directory = Nexor::directory('elements');

        $element->preview_picture = Uploads::handle($request, 'preview_picture', $element->preview_picture, $directory);
        $element->detail_picture = Uploads::handle($request, 'detail_picture', $element->detail_picture, $directory);
    }

    /**
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
