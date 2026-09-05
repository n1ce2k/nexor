<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Nexor\Cms\Http\Requests\IblockRequest;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Uploads;

class IblockController extends Controller
{
    public function index(Request $request): View
    {
        $iblocks = Iblock::query()
            ->with('type')
            ->withCount(['elements', 'sections', 'properties'])
            ->when($request->filled('search'), fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->trim().'%')
                    ->orWhere('code', 'like', '%'.$request->string('search')->trim().'%'),
            ))
            ->when($request->filled('type'), fn ($query) => $query->where('iblock_type_id', $request->integer('type')))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $types = IblockType::query()->ordered()->get();

        return view('nexor::admin.iblocks.index', compact('iblocks', 'types'));
    }

    public function create(): View
    {
        return view('nexor::admin.iblocks.form', [
            'iblock' => new Iblock(['is_active' => true, 'has_sections' => true, 'sort' => 500]),
            'types' => IblockType::query()->ordered()->get(),
        ]);
    }

    public function store(IblockRequest $request): RedirectResponse
    {
        $iblock = new Iblock($request->safe()->except(['picture', 'picture_remove']));
        $iblock->picture = Uploads::handle($request, 'picture', null, 'iblocks');
        $iblock->save();

        ActivityLogger::created($iblock);

        return redirect()->route('admin.iblocks.properties.index', $iblock)
            ->with('success', 'Инфоблок «'.$iblock->name.'» создан. Теперь добавьте свойства.');
    }

    public function show(Iblock $iblock): RedirectResponse
    {
        return redirect()->route('admin.iblocks.edit', $iblock);
    }

    public function edit(Iblock $iblock): View
    {
        return view('nexor::admin.iblocks.form', [
            'iblock' => $iblock,
            'types' => IblockType::query()->ordered()->get(),
        ]);
    }

    public function update(IblockRequest $request, Iblock $iblock): RedirectResponse
    {
        $iblock->fill($request->safe()->except(['picture', 'picture_remove']));
        $iblock->picture = Uploads::handle($request, 'picture', $iblock->picture, 'iblocks');
        $iblock->save();

        ActivityLogger::updated($iblock);

        return redirect()->route('admin.iblocks.index')
            ->with('success', 'Инфоблок «'.$iblock->name.'» сохранён.');
    }

    public function destroy(Iblock $iblock): RedirectResponse
    {
        ActivityLogger::deleted($iblock);
        $iblock->delete();

        return redirect()->route('admin.iblocks.index')
            ->with('success', 'Инфоблок «'.$iblock->name.'» удалён.');
    }
}
