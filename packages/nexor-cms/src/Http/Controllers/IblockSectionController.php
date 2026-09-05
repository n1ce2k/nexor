<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Http\Requests\IblockSectionRequest;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Uploads;

class IblockSectionController extends Controller
{
    public function index(Request $request, Iblock $iblock): View
    {
        abort_unless($iblock->has_sections, 404);

        $sections = $iblock->sections()
            ->withCount('elements')
            ->when($request->filled('search'), fn ($query) => $query->where(
                'name', 'like', '%'.$request->string('search')->trim().'%',
            ))
            ->ordered()
            ->get();

        return view('nexor::admin.iblocks.sections.index', compact('iblock', 'sections'));
    }

    public function create(Request $request, Iblock $iblock): View
    {
        abort_unless($iblock->has_sections, 404);

        return view('nexor::admin.iblocks.sections.form', [
            'iblock' => $iblock,
            'section' => new IblockSection([
                'is_active' => true,
                'sort' => 500,
                'parent_id' => $request->integer('parent') ?: null,
            ]),
            'parents' => $this->parentOptions($iblock),
        ]);
    }

    public function store(IblockSectionRequest $request, Iblock $iblock): RedirectResponse
    {
        $section = new IblockSection($request->safe()->except(['picture', 'picture_remove']));
        $section->iblock_id = $iblock->id;
        $section->picture = Uploads::handle($request, 'picture', null, 'sections');
        $section->save();

        ActivityLogger::created($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.sections.index', $iblock)
            ->with('success', 'Раздел «'.$section->name.'» создан.');
    }

    public function show(Iblock $iblock, IblockSection $section): RedirectResponse
    {
        return redirect()->route('admin.iblocks.elements.index', [$iblock, 'section' => $section->id]);
    }

    public function edit(Iblock $iblock, IblockSection $section): View
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        return view('nexor::admin.iblocks.sections.form', [
            'iblock' => $iblock,
            'section' => $section,
            'parents' => $this->parentOptions($iblock, $section),
        ]);
    }

    public function update(IblockSectionRequest $request, Iblock $iblock, IblockSection $section): RedirectResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        $section->fill($request->safe()->except(['picture', 'picture_remove']));
        $section->picture = Uploads::handle($request, 'picture', $section->picture, 'sections');
        $section->save();

        ActivityLogger::updated($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");

        return redirect()->route('admin.iblocks.sections.index', $iblock)
            ->with('success', 'Раздел «'.$section->name.'» сохранён.');
    }

    public function destroy(Iblock $iblock, IblockSection $section): RedirectResponse
    {
        abort_unless($section->iblock_id === $iblock->id, 404);

        ActivityLogger::deleted($section, "Раздел «{$section->name}» инфоблока «{$iblock->name}»");
        Uploads::delete($section->picture);
        $section->delete();

        return redirect()->route('admin.iblocks.sections.index', $iblock)
            ->with('success', 'Раздел и все вложенные разделы удалены.');
    }

    /**
     * Flat, indented list of possible parents, excluding the section's own subtree.
     *
     * @return Collection<int, IblockSection>
     */
    protected function parentOptions(Iblock $iblock, ?IblockSection $exclude = null): Collection
    {
        return $iblock->sections()
            ->ordered()
            ->get()
            ->when($exclude, fn (Collection $sections) => $sections->reject(
                fn (IblockSection $section) => $section->id === $exclude->id
                    || str_starts_with((string) $section->path, $exclude->path.$exclude->id.'/'),
            ))
            ->values();
    }
}
