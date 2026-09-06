<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Requests\IblockSectionRequest;
use Nexor\Cms\Http\Resources\IblockSectionResource;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

class IblockSectionController extends ApiController
{
    public function index(Request $request, Iblock $iblock): AnonymousResourceCollection
    {
        abort_unless($iblock->has_sections, 404);

        $sections = $iblock->sections()
            ->withCount(['elements', 'children'])
            ->when($request->filled('search'), fn ($query) => $query->where(
                'name', 'like', '%'.$request->string('search')->trim().'%',
            ))
            ->ordered()
            ->get();

        return IblockSectionResource::collection($sections);
    }

    public function show(Iblock $iblock, IblockSection $section): IblockSectionResource
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        return IblockSectionResource::make($section->loadCount('elements'));
    }

    public function store(IblockSectionRequest $request, Iblock $iblock): JsonResponse
    {
        $section = new IblockSection($request->safe()->except(['picture', 'picture_remove']));
        $section->iblock_id = $iblock->id;
        $section->picture = Uploads::handle($request, 'picture', null, Nexor::directory('sections'));
        $section->save();

        ActivityLogger::created($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return IblockSectionResource::make($section)
            ->additional(['message' => 'Раздел «'.$section->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockSectionRequest $request, Iblock $iblock, IblockSection $section): JsonResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        $section->fill($request->safe()->except(['picture', 'picture_remove']));
        $section->picture = Uploads::handle($request, 'picture', $section->picture, Nexor::directory('sections'));
        $section->save();

        ActivityLogger::updated($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return IblockSectionResource::make($section->fresh())
            ->additional(['message' => 'Раздел «'.$section->name.'» сохранён.'])
            ->response();
    }

    public function destroy(Iblock $iblock, IblockSection $section): JsonResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");
        Uploads::delete($section->picture);
        $section->delete();

        return $this->ok('Раздел и все вложенные разделы удалены.');
    }
}
