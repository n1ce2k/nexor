<?php

namespace Nexor\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

        return array_merge($base, PropertyValues::rules($this->properties()));
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
