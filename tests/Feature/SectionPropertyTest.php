<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Services\InfoBlockService;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Свойства разделов: свои описания, свои значения и выдача шаблону.
 *
 * Описания лежат в одной таблице со свойствами элементов, поэтому отдельно
 * проверяется, что одни не протекают в другие.
 */
class SectionPropertyTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected Iblock $iblock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iblock = Iblock::factory()->create([
            'code' => 'katalog', 'name' => 'Каталог', 'has_sections' => true, 'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function sectionProperty(string $code, string $type = 'string', array $attributes = []): IblockProperty
    {
        return IblockProperty::factory()->for($this->iblock)->create($attributes + [
            'target' => IblockProperty::TARGET_SECTION,
            'code' => $code,
            'name' => 'Свойство '.$code,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function section(array $attributes = []): IblockSection
    {
        return IblockSection::factory()->create($attributes + ['iblock_id' => $this->iblock->id, 'name' => 'Мебель']);
    }

    protected function api(string $path = ''): string
    {
        return "/admin/api/iblocks/{$this->iblock->id}".$path;
    }

    // ---------------------------------------------------------------- описания

    public function test_a_section_property_lives_apart_from_element_properties(): void
    {
        IblockProperty::factory()->for($this->iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $this->postJson($this->api('/properties'), [
            'target' => 'section', 'code' => 'BANNER', 'name' => 'Текст баннера', 'type' => 'string',
        ])->assertCreated()->assertJsonPath('data.target', 'section');

        // Каждый список показывает только своё.
        $this->assertSame(['ARTICLE'], $this->getJson($this->api('/properties'))->json('data.*.code'));
        $this->assertSame(['BANNER'], $this->getJson($this->api('/properties?target=section'))->json('data.*.code'));

        // Форма элемента о свойствах разделов не знает.
        $this->assertSame(['ARTICLE'], $this->getJson($this->api('/schema'))->json('properties.*.code'));
        $this->assertSame(['BANNER'], $this->getJson($this->api('/section-schema'))->json('properties.*.code'));
    }

    public function test_the_same_code_is_free_for_elements_and_for_sections(): void
    {
        IblockProperty::factory()->for($this->iblock)->create(['code' => 'COLOR', 'name' => 'Цвет']);

        $payload = ['target' => 'section', 'code' => 'COLOR', 'name' => 'Цвет раздела', 'type' => 'string'];

        $this->postJson($this->api('/properties'), $payload)->assertCreated();

        // А вот второе свойство разделов с тем же кодом — уже нет.
        $this->postJson($this->api('/properties'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_property_cannot_be_moved_between_elements_and_sections(): void
    {
        $property = $this->sectionProperty('BANNER');

        $this->putJson($this->api("/properties/{$property->id}"), [
            'target' => 'element', 'code' => 'BANNER', 'name' => 'Баннер', 'type' => 'string',
        ])->assertOk()->assertJsonPath('data.target', 'section');
    }

    // ---------------------------------------------------------------- значения

    public function test_a_section_keeps_the_values_of_its_properties(): void
    {
        $this->sectionProperty('BANNER');
        $this->sectionProperty('TAGS', 'string', ['is_multiple' => true]);
        $color = $this->sectionProperty('COLOR', 'select');
        $red = $color->enums()->create(['value' => 'Красный', 'code' => 'red', 'sort' => 100]);

        $section = $this->section();

        $this->putJson($this->api("/sections/{$section->id}"), [
            'name' => 'Мебель',
            'properties' => ['BANNER' => 'Скидки до 30%', 'TAGS' => ['хит', '', 'новинка'], 'COLOR' => $red->id],
        ])->assertOk();

        // Форма раздела получает значения обратно.
        $this->getJson($this->api("/sections/{$section->id}"))
            ->assertOk()
            ->assertJsonPath('properties.BANNER', 'Скидки до 30%')
            ->assertJsonPath('properties.TAGS', ['хит', 'новинка'])
            ->assertJsonPath('properties.COLOR', $red->id);

        $properties = $section->fresh()->properties();

        $this->assertSame('Скидки до 30%', $properties['BANNER']['value']);
        $this->assertSame('BANNER', $properties['BANNER']['code']);
        $this->assertSame('string', $properties['BANNER']['type']);
        $this->assertFalse($properties['BANNER']['multiple']);

        $this->assertSame(['хит', 'новинка'], $properties['TAGS']['value']);
        $this->assertCount(2, $properties['TAGS']['values']);

        // У списка value — подпись, raw — id варианта, рядом его код.
        $this->assertSame('Красный', $properties['COLOR']['value']);
        $this->assertSame($red->id, $properties['COLOR']['raw']);
        $this->assertSame('red', $properties['COLOR']['values'][0]['enum_code']);
        $this->assertSame($color->id, $properties['COLOR']['id']);
    }

    public function test_an_empty_property_is_still_in_the_array(): void
    {
        $this->sectionProperty('BANNER');
        $this->sectionProperty('TAGS', 'string', ['is_multiple' => true]);
        $this->sectionProperty('HIDDEN', 'string', ['is_active' => false]);

        $properties = $this->section()->properties();

        // Шаблон может обращаться по коду, не проверяя наличие ключа.
        $this->assertNull($properties['BANNER']['value']);
        $this->assertSame([], $properties['TAGS']['value']);
        $this->assertArrayNotHasKey('HIDDEN', $properties);
    }

    public function test_a_required_section_property_stops_the_save(): void
    {
        $this->sectionProperty('BANNER', 'string', ['is_required' => true]);

        $this->postJson($this->api('/sections'), ['name' => 'Мебель'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('properties.BANNER');

        $this->postJson($this->api('/sections'), ['name' => 'Мебель', 'properties' => ['BANNER' => 'Текст']])
            ->assertCreated();
    }

    public function test_a_file_property_gives_the_address_and_leaves_with_the_section(): void
    {
        Storage::fake('public');
        $this->sectionProperty('COVER', 'image');
        $section = $this->section();

        $this->post($this->api("/sections/{$section->id}"), [
            '_method' => 'PUT',
            'name' => 'Мебель',
            'property_files' => ['COVER' => UploadedFile::fake()->image('cover.jpg')],
        ], ['Accept' => 'application/json'])->assertOk();

        $cover = $section->fresh()->properties()['COVER'];

        Storage::disk('public')->assertExists($cover['raw']);
        $this->assertStringEndsWith($cover['raw'], $cover['value']);
        $this->assertStringStartsWith('/storage/', parse_url($cover['value'], PHP_URL_PATH));

        $this->deleteJson($this->api("/sections/{$section->id}"))->assertOk();

        Storage::disk('public')->assertMissing($cover['raw']);
    }

    public function test_element_properties_stay_untouched(): void
    {
        IblockProperty::factory()->for($this->iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул', 'type' => 'string']);
        $this->sectionProperty('BANNER');

        $element = IblockElement::factory()->for($this->iblock)->create(['name' => 'Стул']);

        // У элемента — только свойства элементов, у раздела — только свойства разделов.
        $this->assertSame(['ARTICLE'], $element->propertyValues()->keys()->all());
        $this->assertSame(['BANNER'], array_keys($this->section()->properties()));
    }

    // ------------------------------------------------------------------ выдача

    public function test_the_service_returns_properties_by_the_section_id(): void
    {
        $this->sectionProperty('BANNER');
        $section = $this->section();
        $section->values()->create(['property_id' => $this->iblock->sectionProperties()->first()->id, 'value_string' => 'Скидки']);

        $service = app(InfoBlockService::class);

        $this->assertSame('Скидки', $service->getSectionProperties($section->id)['BANNER']['value']);
        $this->assertSame('Скидки', $service->getSectionProperty($section->id, 'BANNER'));
        $this->assertNull($service->getSectionProperty($section->id, 'NOPE'));

        // Раздела нет — пустой массив, а не ошибка.
        $this->assertSame([], $service->getSectionProperties(999999));
        $this->assertSame(['BANNER'], $service->getSectionPropertyList('katalog')->pluck('code')->all());
    }

    public function test_a_list_of_sections_loads_the_values_at_once(): void
    {
        $property = $this->sectionProperty('BANNER');

        foreach (['Мебель', 'Декор', 'Свет'] as $index => $name) {
            $this->section(['name' => $name, 'code' => 'razdel-'.$index])
                ->values()->create(['property_id' => $property->id, 'value_string' => 'Баннер: '.$name]);
        }

        $sections = app(InfoBlockService::class)->getSections('katalog');

        // Значения уже загружены вместе со списком: шаблон не делает запрос на каждый раздел.
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $banners = $sections->map(fn (IblockSection $section) => $section->properties()['BANNER']['value'])->all();

        // Порядок разделов задаёт их сортировка, здесь он не важен.
        $this->assertEqualsCanonicalizing(['Баннер: Мебель', 'Баннер: Декор', 'Баннер: Свет'], $banners);
        $this->assertSame(0, $queries);
    }

    // ----------------------------------------------------------------- счётчик

    public function test_a_parent_section_counts_the_elements_of_its_children(): void
    {
        $parent = $this->section(['name' => 'Вышки-туры стальные', 'code' => 'vyshki']);
        $dachnik = $this->section(['name' => 'Дачник', 'code' => 'dachnik', 'parent_id' => $parent->id]);
        $tury = $this->section(['name' => 'Вышки-туры', 'code' => 'tury', 'parent_id' => $parent->id]);
        $empty = $this->section(['name' => 'Ограждения', 'code' => 'ograzhdeniya']);

        $inDachnik = IblockElement::factory()->for($this->iblock)->count(3)->create(['section_id' => $dachnik->id]);
        IblockElement::factory()->for($this->iblock)->count(6)->create(['section_id' => $tury->id]);

        // Один элемент привязан ещё и ко второму разделу ветки — в счёт родителя он идёт один раз.
        $inDachnik->first()->sections()->attach($tury->id);

        // Удалённый в корзину не считается.
        IblockElement::factory()->for($this->iblock)->create(['section_id' => $tury->id])->delete();

        $counts = collect($this->getJson($this->api('/sections'))->assertOk()->json('data'))
            ->pluck('elements_count', 'id');

        $this->assertSame(3, $counts[$dachnik->id]);
        $this->assertSame(7, $counts[$tury->id]);
        $this->assertSame(9, $counts[$parent->id]);
        $this->assertSame(0, $counts[$empty->id]);
    }
}
