<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Nexor\Cms\Http\Requests\AgreementRequest;
use Nexor\Cms\Http\Resources\AgreementResource;
use Nexor\Cms\Models\Agreement;
use Nexor\Cms\Support\ActivityLogger;

class AgreementController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->value();

        $agreements = Agreement::query()
            ->withCount('forms')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'),
            ))
            ->ordered()
            ->paginate($this->perPage($request));

        return AgreementResource::collection($agreements);
    }

    public function show(Agreement $agreement): AgreementResource
    {
        return AgreementResource::make($agreement->loadCount('forms'));
    }

    public function store(AgreementRequest $request): JsonResponse
    {
        $agreement = Agreement::query()->create($request->validated());

        ActivityLogger::created($agreement, 'Соглашение: '.$agreement->name);

        return AgreementResource::make($agreement)
            ->additional(['message' => 'Соглашение «'.$agreement->name.'» создано.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(AgreementRequest $request, Agreement $agreement): JsonResponse
    {
        $agreement->update($request->validated());

        ActivityLogger::updated($agreement, 'Соглашение: '.$agreement->name);

        return AgreementResource::make($agreement)
            ->additional(['message' => 'Соглашение «'.$agreement->name.'» сохранено.'])
            ->response();
    }

    /**
     * Формы, где оно стояло, остаются без соглашения (внешний ключ обнуляется).
     */
    public function destroy(Agreement $agreement): JsonResponse
    {
        ActivityLogger::deleted($agreement, 'Соглашение: '.$agreement->name);
        $agreement->delete();

        return $this->ok('Соглашение удалено.');
    }
}
