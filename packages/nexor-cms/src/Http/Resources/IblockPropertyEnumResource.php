<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\IblockPropertyEnum;

/**
 * @mixin IblockPropertyEnum
 */
class IblockPropertyEnumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'code' => $this->code,
            'is_default' => $this->is_default,
            'sort' => $this->sort,
        ];
    }
}
