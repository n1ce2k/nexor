<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\PropertyValues;
use Nexor\Cms\Support\Uploads;

/**
 * @mixin IblockElement
 */
class IblockElementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iblock_id' => $this->iblock_id,
            'section_id' => $this->section_id,
            'section' => IblockSectionResource::make($this->whenLoaded('section')),
            'code' => $this->code,
            'name' => $this->name,
            'preview_picture' => $this->preview_picture,
            'preview_picture_url' => $this->preview_picture_url,
            'preview_text' => $this->preview_text,
            'preview_text_type' => $this->preview_text_type,
            'detail_picture' => $this->detail_picture,
            'detail_picture_url' => $this->detail_picture_url,
            'detail_text' => $this->detail_text,
            'detail_text_type' => $this->detail_text_type,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'active_from' => $this->active_from?->toIso8601String(),
            'active_to' => $this->active_to?->toIso8601String(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'views' => $this->views,
            'catalog' => $this->whenLoaded('catalog', fn () => $this->catalog ? [
                'type' => $this->catalog->type?->value,
                'offers_by_properties' => $this->catalog->offers_by_properties,
                'price' => $this->catalog->price,
                'currency' => $this->catalog->currency?->value,
                'currency_symbol' => $this->catalog->currency?->symbol(),
                'discount_percent' => $this->catalog->discount_percent,
                'final_price' => $this->catalog->finalPrice(),
                'quantity' => $this->catalog->quantity,
                'measure' => $this->catalog->measure,
                'ratio' => $this->catalog->ratio,
                'quantity_trace' => $this->catalog->quantity_trace,
                'can_buy_zero' => $this->catalog->can_buy_zero,
                'is_available' => $this->catalog->isAvailable(),
                'parent_element_id' => $this->catalog->parent_element_id,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'editor' => $this->whenLoaded('editor', fn () => $this->editor?->name),
            'section_ids' => $this->whenLoaded('sections', fn () => $this->sections->pluck('id')),
            'properties' => $this->when(
                $this->relationLoaded('values'),
                fn () => $this->formValues(),
            ),
            // Подписи к значениям — у свойств с включённым описанием.
            'property_descriptions' => $this->when(
                $this->relationLoaded('values'),
                fn () => PropertyValues::descriptionsForForm($this->resource),
            ),
            'display' => $this->when(
                $this->relationLoaded('values') && $this->resource->iblock?->relationLoaded('properties'),
                fn () => $this->displayColumns(),
            ),
        ];
    }

    /**
     * Values keyed by property code, shaped the way the Vue form expects them.
     *
     * @return array<string, mixed>
     */
    protected function formValues(): array
    {
        $values = PropertyValues::forForm($this->resource);

        foreach ($values as $code => $value) {
            if (! is_array($value)) {
                continue;
            }

            // File rows carry a public URL so the form can preview them.
            $values[$code] = array_map(
                fn ($row) => is_array($row) && isset($row['path'])
                    ? $row + ['url' => Uploads::url($row['path'])]
                    : $row,
                $value,
            );
        }

        return $values;
    }

    /**
     * Printable value per property flagged as a list column.
     *
     * @return array<string, string>
     */
    protected function displayColumns(): array
    {
        return $this->resource->iblock->properties
            ->where('is_shown_in_list', true)
            ->mapWithKeys(fn (IblockProperty $property) => [
                $property->code => $this->resource->displayValue($property),
            ])
            ->all();
    }
}
