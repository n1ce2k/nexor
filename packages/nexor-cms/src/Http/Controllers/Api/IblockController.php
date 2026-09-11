<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Requests\IblockRequest;
use Nexor\Cms\Http\Resources\IblockResource;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\CatalogManager;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\PageGenerator;
use Nexor\Cms\Support\Uploads;

class IblockController extends ApiController
{
    /** @var array<int, string> */
    protected const SORTABLE = ['name', 'code', 'sort', 'created_at'];

    public function index(Request $request): AnonymousResourceCollection
    {
        [$column, $direction] = $this->sorting($request, self::SORTABLE, 'sort');

        $iblocks = Iblock::query()
            ->with('type')
            ->withCount(['elements', 'sections', 'properties'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';

                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
            })
            ->when($request->filled('type'), fn ($query) => $query->where('iblock_type_id', $request->integer('type')))
            ->orderBy($column, $direction)
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return IblockResource::collection($iblocks);
    }

    public function show(Iblock $iblock): IblockResource
    {
        return IblockResource::make($iblock->load('type')->loadCount(['elements', 'sections', 'properties']));
    }

    public function store(IblockRequest $request): JsonResponse
    {
        $iblock = new Iblock($request->safe()->except(['picture', 'picture_remove']));
        $iblock->picture = Uploads::handle($request, 'picture', null, Nexor::directory('iblocks'));
        $iblock->save();

        $this->syncPage($iblock);
        CatalogManager::sync($iblock);

        ActivityLogger::created($iblock);

        return IblockResource::make($iblock->load('type'))
            ->additional(['message' => 'Инфоблок «'.$iblock->name.'» создан.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(IblockRequest $request, Iblock $iblock): JsonResponse
    {
        $iblock->fill($request->safe()->except(['picture', 'picture_remove']));
        $iblock->picture = Uploads::handle($request, 'picture', $iblock->picture, Nexor::directory('iblocks'));
        $iblock->save();

        $this->syncPage($iblock);
        CatalogManager::sync($iblock);

        ActivityLogger::updated($iblock);

        return IblockResource::make($iblock->load('type'))
            ->additional(['message' => 'Инфоблок «'.$iblock->name.'» сохранён.'])
            ->response();
    }

    public function destroy(Iblock $iblock): JsonResponse
    {
        ActivityLogger::deleted($iblock);
        $iblock->delete();

        return $this->ok('Инфоблок удалён.');
    }

    /**
     * Scaffolds resources/views/<code>/ when the switch is on.
     *
     * The file is written once; afterwards it belongs to whoever edits it.
     */
    protected function syncPage(Iblock $iblock): void
    {
        if (! $iblock->has_page) {
            return;
        }

        $path = PageGenerator::create($iblock);

        if ($path !== $iblock->page_path) {
            $iblock->forceFill(['page_path' => $path])->save();
        }
    }
}
