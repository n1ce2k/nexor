<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Tests\TestCase;

/**
 * Список элементов конкретного раздела: `:section_id` у catalog.section и news.list.
 */
class ListingSectionIdTest extends TestCase
{
    use RefreshDatabase;

    protected Iblock $catalog;

    protected IblockSection $chairs;

    protected IblockSection $barStools;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = Iblock::factory()->create(['code' => 'katalog', 'is_active' => true, 'has_sections' => true]);
        $this->chairs = IblockSection::factory()->create(['iblock_id' => $this->catalog->id, 'code' => 'stulya']);
        $this->barStools = IblockSection::factory()->childOf($this->chairs)->create(['code' => 'barnye']);
        $tables = IblockSection::factory()->create(['iblock_id' => $this->catalog->id, 'code' => 'stoly']);

        IblockElement::factory()->for($this->catalog)->create(['name' => 'Стул венский', 'section_id' => $this->chairs->id, 'is_active' => true]);
        IblockElement::factory()->for($this->catalog)->create(['name' => 'Стул барный', 'section_id' => $this->barStools->id, 'is_active' => true]);
        IblockElement::factory()->for($this->catalog)->create(['name' => 'Стол обеденный', 'section_id' => $tables->id, 'is_active' => true]);
    }

    public function test_the_catalog_lists_only_the_given_section_with_its_children(): void
    {
        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" :section_id="$id" />', ['id' => $this->chairs->id]);

        $this->assertStringContainsString('Стул венский', $html);
        $this->assertStringContainsString('Стул барный', $html);
        $this->assertStringNotContainsString('Стол обеденный', $html);
    }

    public function test_recursive_off_keeps_only_the_section_itself(): void
    {
        $html = Blade::render(
            '<x-nexor::catalog.section iblock="katalog" section_id="'.$this->chairs->id.'" :recursive="false" />',
        );

        $this->assertStringContainsString('Стул венский', $html);
        $this->assertStringNotContainsString('Стул барный', $html);
    }

    public function test_the_news_list_takes_a_section_id_too(): void
    {
        $html = Blade::render('<x-nexor::news.list :iblock="$iblock" :section_id="$id" />', [
            'iblock' => $this->catalog->id,
            'id' => $this->barStools->id,
        ]);

        $this->assertStringContainsString('Стул барный', $html);
        $this->assertStringNotContainsString('Стул венский', $html);
    }

    public function test_the_page_address_does_not_override_the_pinned_section(): void
    {
        // Страница раздела «Столы» — адрес сам указывает на другой раздел.
        $this->app->instance('request', Request::create('/katalog/stoly'));

        $this->assertStringContainsString('Стол обеденный', Blade::render('<x-nexor::catalog.section iblock="katalog" />'));

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" :section_id="$id" />', ['id' => $this->barStools->id]);

        $this->assertStringContainsString('Стул барный', $html);
        $this->assertStringNotContainsString('Стол обеденный', $html);
    }

    public function test_a_missing_or_foreign_section_gives_an_empty_list(): void
    {
        $foreign = IblockSection::factory()->create([
            'iblock_id' => Iblock::factory()->create(['code' => 'drugoy', 'is_active' => true, 'has_sections' => true])->id,
        ]);

        foreach ([999999, $foreign->id] as $id) {
            $html = Blade::render('<x-nexor::catalog.section iblock="katalog" :section_id="$id" />', ['id' => $id]);

            $this->assertStringNotContainsString('Стул', $html);
            $this->assertStringNotContainsString('Стол', $html);
        }

        $list = Blade::render('<x-nexor::catalog.section iblock="katalog" :section_id="999999" :paginate="false" />');

        $this->assertStringNotContainsString('Стул', $list);
    }

    public function test_a_section_id_that_is_not_a_number_fails_loudly(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('section_id «stulya» — нужно число');

        Blade::render('<x-nexor::catalog.section iblock="katalog" section_id="stulya" />');
    }
}
