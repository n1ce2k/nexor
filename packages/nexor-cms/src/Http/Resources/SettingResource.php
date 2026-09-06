<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\Setting;

/**
 * @mixin Setting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'value' => $this->publicValue(),
            'type' => $this->type,
            'is_system' => $this->is_system,
            'is_encrypted' => $this->is_encrypted,
            'input' => str_replace('.', '__', $this->key),
            'group' => $this->group,
            'name' => $this->name,
            'hint' => $this->hint,
            'options' => $this->options,
            'sort' => $this->sort,
        ];
    }
}
