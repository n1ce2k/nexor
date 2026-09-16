<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Tests\TestCase;

/**
 * Детальная элемента вне его страницы — например, новость на главной:
 * по инфоблоку и id или коду.
 */
class DetailComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function news(): Iblock
    {
        return Iblock::factory()->create(['code' => 'news', 'is_active' => true, 'has_sections' => false]);
    }

    public function test_a_news_item_is_found_by_id(): void
    {
        $item = IblockElement::factory()->for($this->news())->create([
            'name' => 'Открытие магазина',
            'is_active' => true,
            'detail_text' => 'Ждём всех в субботу',
            'detail_text_type' => 'text',
        ]);

        $html = Blade::render('<x-nexor::news.detail iblock="news" :id="$id" />', ['id' => $item->id]);

        $this->assertStringContainsString('Открытие магазина', $html);
        $this->assertStringContainsString('Ждём всех в субботу', $html);
    }

    public function test_the_id_can_be_written_as_a_plain_attribute(): void
    {
        $item = IblockElement::factory()->for($this->news())->create(['name' => 'Акция', 'is_active' => true]);

        $html = Blade::render('<x-nexor::catalog.element iblock="news" id="'.$item->id.'" />');

        $this->assertStringContainsString('Акция', $html);
    }

    public function test_a_hidden_or_missing_item_renders_nothing(): void
    {
        $hidden = IblockElement::factory()->for($this->news())->create(['name' => 'Черновик', 'is_active' => false]);

        $this->assertSame('', trim(Blade::render('<x-nexor::news.detail iblock="news" :id="$id" />', ['id' => $hidden->id])));
        $this->assertSame('', trim(Blade::render('<x-nexor::news.detail iblock="news" :id="999999" />')));
        $this->assertSame('', trim(Blade::render('<x-nexor::news.detail iblock="news" code="net-takoy" />')));
    }

    public function test_an_element_of_another_iblock_is_not_shown(): void
    {
        $this->news();
        $foreign = IblockElement::factory()->for(Iblock::factory()->create(['code' => 'katalog', 'is_active' => true]))
            ->create(['name' => 'Стул', 'is_active' => true]);

        $this->assertSame('', trim(Blade::render('<x-nexor::news.detail iblock="news" :id="$id" />', ['id' => $foreign->id])));
    }

    public function test_the_iblock_can_be_given_by_its_id(): void
    {
        $news = $this->news();
        $item = IblockElement::factory()->for($news)->create(['name' => 'Новость по id', 'is_active' => true]);

        $this->assertStringContainsString(
            'Новость по id',
            Blade::render('<x-nexor::news.detail :iblock="$iblock" :id="$id" />', ['iblock' => $news->id, 'id' => $item->id]),
        );

        $this->assertStringContainsString(
            'Новость по id',
            Blade::render('<x-nexor::news.detail iblock="'.$news->id.'" code="'.$item->code.'" />'),
        );
    }

    public function test_lists_accept_the_iblock_id_too(): void
    {
        $news = $this->news();
        IblockElement::factory()->for($news)->create(['name' => 'Первая', 'is_active' => true]);

        $this->assertStringContainsString('Первая', Blade::render('<x-nexor::news.list iblock="'.$news->id.'" />'));
        $this->assertStringContainsString('Первая', Blade::render('<x-nexor::catalog.section :iblock="$id" />', ['id' => $news->id]));
    }

    public function test_an_inactive_iblock_is_not_found_by_id_either(): void
    {
        $hidden = Iblock::factory()->create(['code' => 'arkhiv', 'is_active' => false]);

        $this->assertStringContainsString('Инфоблок недоступен', Blade::render('<x-nexor::news.list iblock="'.$hidden->id.'" />'));
    }

    public function test_an_iblock_code_of_digits_only_is_refused(): void
    {
        $iblock = $this->news();
        $admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);

        $this->actingAs($admin)
            ->putJson("/admin/api/iblocks/{$iblock->id}", [
                'iblock_type_id' => $iblock->iblock_type_id,
                'code' => '2024',
                'name' => $iblock->name,
            ])
            ->assertJsonValidationErrors(['code' => 'не может состоять из одних цифр']);
    }

    public function test_a_call_without_id_or_code_fails_loudly(): void
    {
        $this->news();

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('нужен либо :element, либо iblock вместе с code или id');

        Blade::render('<x-nexor::news.detail iblock="news" />');
    }

    public function test_a_switched_off_iblock_does_not_break_the_page(): void
    {
        $news = $this->news();
        $item = IblockElement::factory()->for($news)->create(['name' => 'Новость', 'is_active' => true]);
        $news->update(['is_active' => false]);

        $html = Blade::render('<main>до <x-nexor::news.detail iblock="news" :id="$id" /> после</main>', ['id' => $item->id]);

        $this->assertStringContainsString('Инфоблок недоступен', $html);
        $this->assertStringContainsString('после', $html);
    }
}
