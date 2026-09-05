<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Nexor\Cms\Http\Requests\IblockTypeRequest;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\ActivityLogger;

class IblockTypeController extends Controller
{
    public function index(): View
    {
        $types = IblockType::query()
            ->withCount('iblocks')
            ->ordered()
            ->paginate(20);

        return view('nexor::admin.iblock-types.index', compact('types'));
    }

    public function create(): View
    {
        return view('nexor::admin.iblock-types.form', [
            'type' => new IblockType(['is_active' => true, 'has_sections' => true, 'sort' => 500]),
        ]);
    }

    public function store(IblockTypeRequest $request): RedirectResponse
    {
        $type = IblockType::query()->create($request->validated());

        ActivityLogger::created($type);

        return redirect()->route('admin.iblock-types.index')
            ->with('success', 'Тип инфоблоков «'.$type->name.'» создан.');
    }

    public function show(IblockType $iblockType): RedirectResponse
    {
        return redirect()->route('admin.iblock-types.edit', $iblockType);
    }

    public function edit(IblockType $iblockType): View
    {
        return view('nexor::admin.iblock-types.form', ['type' => $iblockType]);
    }

    public function update(IblockTypeRequest $request, IblockType $iblockType): RedirectResponse
    {
        $iblockType->update($request->validated());

        ActivityLogger::updated($iblockType);

        return redirect()->route('admin.iblock-types.index')
            ->with('success', 'Тип инфоблоков «'.$iblockType->name.'» сохранён.');
    }

    public function destroy(IblockType $iblockType): RedirectResponse
    {
        if ($iblockType->iblocks()->exists()) {
            return back()->with('error', 'В этом типе есть инфоблоки — сначала удалите или перенесите их.');
        }

        ActivityLogger::deleted($iblockType);
        $iblockType->delete();

        return redirect()->route('admin.iblock-types.index')
            ->with('success', 'Тип инфоблоков удалён.');
    }
}
