<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Enums\ProductType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\PropertyValues;

class IblockElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route middleware `iblock:` already checked the infoblock permission.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $iblock = $this->iblock();
        $element = $this->route('element');

        $base = [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:190', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('iblock_elements', 'code')
                    ->where('iblock_id', $iblock->id)
                    ->ignore($element?->id)
                    ->withoutTrashed(),
            ],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('iblock_sections', 'id')->where('iblock_id', $iblock->id),
            ],
            'sections' => ['array'],
            'sections.*' => ['integer', Rule::exists('iblock_sections', 'id')->where('iblock_id', $iblock->id)],
            'preview_text' => ['nullable', 'string', 'max:65535'],
            'preview_text_type' => ['nullable', 'in:text,html'],
            'preview_picture' => ['nullable', 'image', 'max:8192'],
            'preview_picture_remove' => ['boolean'],
            'detail_text' => ['nullable', 'string'],
            'detail_text_type' => ['nullable', 'in:text,html'],
            'detail_picture' => ['nullable', 'image', 'max:8192'],
            'detail_picture_remove' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
        ];

        if ($iblock->hasCommerce()) {
            $base += [
                'catalog' => ['nullable', 'array'],
                'catalog.type' => ['nullable', Rule::enum(ProductType::class)],
                'catalog.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
                'catalog.currency' => ['nullable', Rule::enum(Currency::class)],
                'catalog.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'catalog.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
                'catalog.measure' => ['nullable', 'string', 'max:20'],
                'catalog.ratio' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
                'catalog.quantity_trace' => ['boolean'],
                'catalog.can_buy_zero' => ['boolean'],
            ];
        }

        // Предложение привязывается только к товару своего каталога.
        if ($iblock->product_iblock_id) {
            $base['parent_element_id'] = [
                'nullable', 'integer',
                Rule::exists('iblock_elements', 'id')
                    ->where('iblock_id', $iblock->product_iblock_id)
                    ->whereNull('deleted_at'),
            ];
        }

        return array_merge($base, PropertyValues::rules($this->properties()));
    }

    /**
     * Drops blank entries a form may send for an untouched multi-select.
     *
     * An element without a section is normal, so an empty list must not turn
     * into `sections.0` failing the `integer` rule.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('sections')) {
            return;
        }

        $this->merge([
            'sections' => array_values(array_filter((array) $this->input('sections'), 'filled')),
        ]);
    }

    /**
     * Human-readable names so validation messages talk about the property, not the key.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'name' => 'название',
            'code' => 'символьный код',
            'section_id' => 'раздел',
            'sort' => 'сортировка',
            'active_from' => 'начало активности',
            'active_to' => 'окончание активности',
            'catalog.type' => 'тип товара',
            'catalog.price' => 'цена',
            'catalog.currency' => 'валюта',
            'catalog.discount_percent' => 'скидка',
            'catalog.quantity' => 'доступное количество',
            'catalog.measure' => 'единица измерения',
            'catalog.ratio' => 'коэффициент',
            'parent_element_id' => 'товар',
        ];

        foreach ($this->properties() as $property) {
            $attributes['properties.'.$property->code] = mb_strtolower($property->name);
            $attributes['properties.'.$property->code.'.*'] = mb_strtolower($property->name);
            $attributes['property_files.'.$property->code] = mb_strtolower($property->name);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Символьный код может содержать только латиницу в нижнем регистре, цифры, дефис и подчёркивание.',
            // Код подставляется из названия автоматически, поэтому важно сказать,
            // что занят он именно в этом инфоблоке, и его достаточно поправить.
            'code.unique' => 'Такой символьный код в этом инфоблоке уже занят — измените его.',
        ];
    }

    public function iblock(): Iblock
    {
        return $this->route('iblock');
    }

    /**
     * @return Collection<int, IblockProperty>
     */
    public function properties(): Collection
    {
        return $this->iblock()->properties()->active()->with('enums')->get();
    }
}
