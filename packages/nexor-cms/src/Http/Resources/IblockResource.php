<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Support\Modules\ModuleFields;

/**
 * @mixin Iblock
 */
class IblockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iblock_type_id' => $this->iblock_type_id,
            'type' => IblockTypeResource::make($this->whenLoaded('type')),
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'picture' => $this->picture,
            'list_url' => $this->list_url,
            'section_url' => $this->section_url,
            'detail_url' => $this->detail_url,
            'element_url' => $this->element_url?->value ?? 'nested',
            'has_sections' => $this->has_sections,
            'has_page' => $this->has_page,
            'page_path' => $this->page_path,
            'element_name' => $this->element_name,
            'add_element_label' => $this->addElementLabel(),
            'pagination_template' => $this->pagination_template?->value ?? PaginationTemplate::Simple->value,
            'per_page' => $this->per_page,
            'has_load_more' => $this->has_load_more,
            'load_more_size' => $this->load_more_size,
            'is_catalog' => (bool) $this->is_catalog,
            'offers_iblock_id' => $this->offers_iblock_id,
            'product_iblock_id' => $this->product_iblock_id,
            'has_commerce' => $this->resource->hasCommerce(),
            'has_offers' => $this->resource->hasOffers(),
            // Переключатели модулей: `module_settings.pagebuilder.detail`.
            'module_settings' => ModuleFields::iblockSettingValues($this->resource),
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'elements_count' => $this->whenCounted('elements'),
            'sections_count' => $this->whenCounted('sections'),
            'properties_count' => $this->whenCounted('properties'),
            'abilities' => [
                'view' => $request->user()?->hasPermission($this->permissionCode('view')) ?? false,
                'create' => $request->user()?->hasPermission($this->permissionCode('create')) ?? false,
                'update' => $request->user()?->hasPermission($this->permissionCode('update')) ?? false,
                'delete' => $request->user()?->hasPermission($this->permissionCode('delete')) ?? false,
            ],
        ];
    }
}
