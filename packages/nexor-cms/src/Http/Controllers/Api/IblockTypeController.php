<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Requests\IblockTypeRequest;
use Nexor\Cms\Http\Resources\IblockTypeResource;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\ActivityLogger;

class IblockTypeController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $types = IblockType::query()
            ->withCount('iblocks')
            ->ordered()
            ->paginate($this->perPage($request));

        return IblockTypeResource::collection($types);
    }

    public function show(IblockType $iblockType): IblockTypeResource
    {
        return IblockTypeResource::make($iblockType->loadCount('iblocks'));
    }

    public function store(IblockTypeRequest $request): JsonResponse
    {
        $type = IblockType::query()->create($request->validated());

        ActivityLogger::created($type);

        return IblockTypeResource::make($type)
            ->additional(['message' => 'Тип «'.$type->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockTypeRequest $request, IblockType $iblockType): JsonResponse
    {
        $iblockType->update($request->validated());

        ActivityLogger::updated($iblockType);

        return IblockTypeResource::make($iblockType)
            ->additional(['message' => 'Тип «'.$iblockType->name.'» сохранён.'])
            ->response();
    }

    public function destroy(IblockType $iblockType): JsonResponse
    {
        if ($iblockType->iblocks()->exists()) {
            return $this->refuse('В этом типе есть инфоблоки — сначала удалите или перенесите их.');
        }

        ActivityLogger::deleted($iblockType);
        $iblockType->delete();

        return $this->ok('Тип инфоблоков удалён.');
    }
}
