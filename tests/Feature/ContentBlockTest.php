<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Models\ContentBlock;
use Nexor\Cms\Support\ContentBlocks;
use Nexor\Cms\Support\InlineEditor;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Блоки, правимые прямо на странице сайта: вывод, режим правки и сохранение.
 */
class ContentBlockTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Драйвер задаётся в .env сайта, а тесты должны идти одинаково везде:
        // без этого прогон у владельца с NEXOR_CONTENT_DB=false писал бы
        // тестовые блоки в настоящий storage/app/nexor-content.json.
        config(['nexor.content.database' => true, 'nexor.content.file' => 'nexor-content-test.json']);

        @unlink(ContentBlocks::file());
        ContentBlocks::forgetCache();
    }

    protected function tearDown(): void
    {
        @unlink(ContentBlocks::file());

        parent::tearDown();
    }

    protected function editor(): object
    {
        return $this->adminWith([InlineEditor::PERMISSION]);
    }

    // ------------------------------------------------------------------ вывод

    public function test_a_block_shows_what_the_template_says_until_it_is_edited(): void
    {
        $html = Blade::render('<x-nexor::edit key="about.title" as="h2" class="big">О компании</x-nexor::edit>');

        $this->assertStringContainsString('<h2 class="big"', $html);
        $this->assertStringContainsString('О компании', $html);
        // Посетителю разметка редактора не достаётся.
        $this->assertStringNotContainsString('data-nexor-edit', $html);
    }

    public function test_an_edited_block_wins_over_the_template(): void
    {
        ContentBlocks::put('about.title', 'text', 'Про нас');

        $html = Blade::render('<x-nexor::edit key="about.title" as="h2">О компании</x-nexor::edit>');

        $this->assertStringContainsString('Про нас', $html);
        $this->assertStringNotContainsString('О компании', $html);
    }

    public function test_a_picture_falls_back_to_the_one_in_the_markup(): void
    {
        $html = Blade::render('<x-nexor::edit.image key="about.photo" src="/assets/about.jpg" alt="О компании" />');

        $this->assertStringContainsString('src="/assets/about.jpg"', $html);

        ContentBlocks::put('about.photo', 'image', 'content/new.jpg');

        $html = Blade::render('<x-nexor::edit.image key="about.photo" src="/assets/about.jpg" />');

        $this->assertStringContainsString('/storage/content/new.jpg', $html);
    }

    // ------------------------------------------------------------ режим правки

    public function test_the_editor_only_reaches_someone_who_may_edit(): void
    {
        // Гость: ни полосы режима, ни скрипта.
        $this->get('/?nexor-edit=1')->assertOk()->assertDontSee('nexor-edit-bar');

        $this->actingAs($this->adminWith([]))
            ->get('/?nexor-edit=1')
            ->assertOk()
            ->assertDontSee('nexor-edit-bar');

        $this->actingAs($this->editor())
            ->get('/?nexor-edit=1')
            ->assertOk()
            ->assertSee('nexor-edit-bar')
            ->assertSee('inline-editor.js', false);
    }

    public function test_the_mode_can_be_turned_off_again(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/?nexor-edit=1')->assertSee('nexor-edit-bar');
        $this->actingAs($editor)->get('/?nexor-edit=0')->assertDontSee('nexor-edit-bar');
    }

    public function test_the_editor_script_is_addressed_by_its_own_fingerprint(): void
    {
        $html = $this->actingAs($this->editor())->get('/?nexor-edit=1')->assertOk()->getContent();

        // Отпечаток файла, а не версия CMS: иначе правка самого редактора
        // десять минут не доезжала бы до браузера из-за кеша.
        $this->assertMatchesRegularExpression('#inline-editor\.js\?v=[0-9a-f]{10}#', $html);
        $this->assertMatchesRegularExpression('#inline-editor\.css\?v=[0-9a-f]{10}#', $html);
    }

    public function test_the_editor_assets_are_closed_to_strangers(): void
    {
        $this->get('/nexor/content/assets/inline-editor.js')->assertForbidden();
        $this->actingAs($this->editor())->get('/nexor/content/assets/inline-editor.js')->assertOk();
        $this->actingAs($this->editor())->get('/nexor/content/assets/../../.env')->assertNotFound();
    }

    // -------------------------------------------------------------- сохранение

    public function test_saving_needs_the_permission(): void
    {
        $this->actingAs($this->adminWith([]))
            ->patchJson('/nexor/content/about.title', ['value' => 'Взлом', 'type' => 'text'])
            ->assertForbidden();

        $this->assertNull(ContentBlocks::get('about.title'));
    }

    public function test_a_saved_block_loses_everything_dangerous(): void
    {
        $this->actingAs($this->editor())
            ->patchJson('/nexor/content/about.lead', [
                'type' => 'html',
                'value' => 'Мы <b>лучшие</b><script>alert(1)</script> <a href="javascript:alert(1)" onclick="alert(1)">тут</a>',
            ])
            ->assertOk()
            ->assertJsonPath('value', 'Мы <b>лучшие</b>alert(1) <a>тут</a>');

        $this->assertSame('Мы <b>лучшие</b>alert(1) <a>тут</a>', ContentBlocks::get('about.lead'));
    }

    public function test_a_plain_block_keeps_no_markup_at_all(): void
    {
        $this->actingAs($this->editor())
            ->patchJson('/nexor/content/about.title', ['type' => 'text', 'value' => 'Про <b>нас</b>'])
            ->assertOk()
            ->assertJsonPath('value', 'Про нас');
    }

    public function test_a_picture_is_replaced_and_the_old_file_goes_away(): void
    {
        Storage::fake('public');

        $editor = $this->editor();

        $first = $this->actingAs($editor)
            ->post('/nexor/content/about.photo/image', ['image' => UploadedFile::fake()->image('one.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('value');

        Storage::disk('public')->assertExists($first);

        $second = $this->actingAs($editor)
            ->post('/nexor/content/about.photo/image', ['image' => UploadedFile::fake()->image('two.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('value');

        // Ключ один на сайт, значит прежняя картинка больше нигде не нужна.
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->assertSame($second, ContentBlocks::get('about.photo'));
    }

    public function test_a_block_can_be_returned_to_the_template(): void
    {
        ContentBlocks::put('about.title', 'text', 'Про нас');

        $this->actingAs($this->editor())
            ->deleteJson('/nexor/content/about.title')
            ->assertOk();

        $this->assertNull(ContentBlocks::get('about.title'));
        $this->assertStringContainsString(
            'О компании',
            Blade::render('<x-nexor::edit key="about.title" as="h2">О компании</x-nexor::edit>'),
        );
    }

    // ------------------------------------------------------------ переносы

    public function test_a_multiline_block_keeps_its_line_breaks(): void
    {
        $this->actingAs($this->editor())
            ->patchJson('/nexor/content/about.desc', [
                'type' => 'text',
                // Браузер присылает переносы тегом, в хранилище это переводы строк.
                'value' => 'Первый абзац.<br>Второй абзац.',
            ])
            ->assertOk()
            ->assertJsonPath('value', "Первый абзац.\nВторой абзац.");

        $html = Blade::render('<x-nexor::edit key="about.desc" as="p" multiline>Текст</x-nexor::edit>');

        // На странице перенос снова становится тегом.
        $this->assertStringContainsString('Первый абзац.<br />'."\n".'Второй абзац.', $html);
    }

    public function test_a_single_line_block_gets_no_breaks(): void
    {
        ContentBlocks::put('about.title', 'text', "Про\nнас");

        $html = Blade::render('<x-nexor::edit key="about.title" as="h2">О компании</x-nexor::edit>');

        $this->assertStringNotContainsString('<br', $html);
    }

    public function test_the_wrappers_contenteditable_adds_become_breaks(): void
    {
        // Браузеры заворачивают новую строку то в <div>, то в <p> — и то и
        // другое должно стать переносом, а не пропасть вместе с текстом.
        $this->actingAs($this->editor())
            ->patchJson('/nexor/content/about.lead', [
                'type' => 'html',
                'value' => '<div>Первый</div><div>Второй</div>',
            ])
            ->assertOk()
            ->assertJsonPath('value', 'Первый<br>Второй');
    }

    public function test_a_multiline_block_is_editable_as_one_piece(): void
    {
        $this->actingAs($this->editor())->get('/?nexor-edit=1');

        $html = Blade::render('<x-nexor::edit key="about.desc" as="p" multiline>Текст</x-nexor::edit>');

        // Скрипту нужна пометка, чтобы Enter переносил строку, а не сохранял.
        $this->assertStringContainsString('data-nexor-breaks="1"', $html);

        $single = Blade::render('<x-nexor::edit key="about.title" as="h2">Заголовок</x-nexor::edit>');

        $this->assertStringNotContainsString('data-nexor-breaks', $single);
    }

    // ------------------------------------------------------------ автозапись

    public function test_opening_a_page_in_edit_mode_writes_its_blocks_down(): void
    {
        $this->actingAs($this->editor())->get('/?nexor-edit=1')->assertOk();

        ContentBlocks::forgetCache();
        $blocks = ContentBlocks::all();

        // Главная собрана из компонентов сайта; проверяем сам механизм на
        // блоке, который точно на ней есть после установки.
        $this->assertNotSame([], $blocks, 'Режим правки должен записать блоки страницы.');

        foreach ($blocks as $block) {
            $this->assertFalse($block['edited'], 'Записанный сам блок не считается правленным.');
        }
    }

    public function test_a_page_seen_by_a_visitor_writes_nothing(): void
    {
        $this->get('/')->assertOk();

        $this->assertSame([], ContentBlocks::all());
    }

    public function test_a_block_written_by_itself_still_obeys_the_template(): void
    {
        ContentBlocks::seed('about.title', 'text', 'О компании');

        // Текст в шаблоне поменяли — на сайте виден он, а не запись.
        $html = Blade::render('<x-nexor::edit key="about.title" as="h2">Про нас</x-nexor::edit>');

        $this->assertStringContainsString('Про нас', $html);
        $this->assertStringNotContainsString('О компании', $html);
    }

    public function test_editing_makes_the_block_win_over_the_template(): void
    {
        ContentBlocks::seed('about.title', 'text', 'О компании');

        $this->actingAs($this->editor())
            ->patchJson('/nexor/content/about.title', ['type' => 'text', 'value' => 'Про нас'])
            ->assertOk();

        $this->assertTrue(ContentBlocks::all()['about.title']['edited']);
        $this->assertStringContainsString(
            'Про нас',
            Blade::render('<x-nexor::edit key="about.title" as="h2">О компании</x-nexor::edit>'),
        );
    }

    // -------------------------------------------------------------- команда

    public function test_the_command_collects_blocks_from_templates(): void
    {
        $views = storage_path('framework/testing/content-views');

        @mkdir($views, 0777, true);
        file_put_contents($views.'/page.blade.php', <<<'BLADE'
            <x-nexor::edit key="scan.title" as="h2" class="t">Почему с нами работают</x-nexor::edit>
            <x-nexor::edit key="scan.lead" as="p" html>Полный <b>цикл</b>.</x-nexor::edit>
            <x-nexor::edit.image key="scan.photo" src="/assets/about.jpg" alt="Фото" />
            <x-nexor::edit key="{{ $code }}" as="p">Вычисляемый ключ</x-nexor::edit>
            BLADE);

        $this->artisan('nexor:content:scan', ['--path' => [$views]])->assertSuccessful();

        ContentBlocks::forgetCache();
        $blocks = ContentBlocks::all();

        $this->assertSame('Почему с нами работают', $blocks['scan.title']['value']);
        $this->assertSame('text', $blocks['scan.title']['type']);
        $this->assertSame('html', $blocks['scan.lead']['type']);
        $this->assertSame('/assets/about.jpg', $blocks['scan.photo']['value']);
        // Ключ из переменной команда перечислить не может и молча не выдумывает.
        $this->assertCount(3, $blocks);

        // Повторный проход ничего не портит и правку не затирает.
        ContentBlocks::put('scan.title', 'text', 'Правленное');
        $this->artisan('nexor:content:scan', ['--path' => [$views]])->assertSuccessful();

        ContentBlocks::forgetCache();
        $this->assertSame('Правленное', ContentBlocks::get('scan.title'));

        @unlink($views.'/page.blade.php');
        @rmdir($views);
    }

    public function test_the_command_can_drop_keys_that_left_the_templates(): void
    {
        $views = storage_path('framework/testing/content-views');

        @mkdir($views, 0777, true);
        file_put_contents($views.'/page.blade.php', '<x-nexor::edit key="scan.title" as="h2">Заголовок</x-nexor::edit>');

        ContentBlocks::put('scan.gone', 'text', 'Блока больше нет в шаблонах');

        $this->artisan('nexor:content:scan', ['--path' => [$views], '--prune' => true])->assertSuccessful();

        ContentBlocks::forgetCache();

        $this->assertArrayHasKey('scan.title', ContentBlocks::all());
        $this->assertArrayNotHasKey('scan.gone', ContentBlocks::all());

        @unlink($views.'/page.blade.php');
        @rmdir($views);
    }

    // ----------------------------------------------------------- хранилища

    public function test_blocks_live_in_the_database_by_default(): void
    {
        ContentBlocks::put('about.title', 'text', 'Про нас');

        $this->assertDatabaseHas('content_blocks', ['key' => 'about.title', 'value' => 'Про нас']);
        $this->assertSame('Про нас', ContentBlock::query()->where('key', 'about.title')->value('value'));
    }

    public function test_a_json_file_can_hold_them_instead(): void
    {
        config(['nexor.content.database' => false]);
        ContentBlocks::forgetCache();

        ContentBlocks::put('about.title', 'text', 'Из файла');

        $this->assertFileExists(ContentBlocks::file());
        $this->assertSame('Из файла', ContentBlocks::get('about.title'));
        // База при этом не задействована вовсе.
        $this->assertDatabaseCount('content_blocks', 0);

        ContentBlocks::forget('about.title');

        $this->assertNull(ContentBlocks::get('about.title'));
    }
}
