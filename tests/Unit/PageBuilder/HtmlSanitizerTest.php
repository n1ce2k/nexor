<?php

namespace Tests\Unit\PageBuilder;

use Nexor\PageBuilder\Support\HtmlSanitizer;
use Nexor\PageBuilder\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_formatting_and_cyrillic_survive(): void
    {
        $html = '<h2>Вышка-тура</h2><p><strong>Жирный</strong> и <em>курсив</em>, <a href="/katalog">каталог</a></p>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_scripts_styles_and_handlers_are_removed(): void
    {
        $clean = HtmlSanitizer::clean(
            '<p onclick="steal()" style="color:red">Текст<script>alert(1)</script></p><style>p{}</style><iframe src="//evil"></iframe><img src=x onerror=alert(1)>',
        );

        $this->assertSame('<p>Текст</p>', $clean);
    }

    #[DataProvider('unsafeLinks')]
    public function test_unsafe_links_lose_their_address(string $href): void
    {
        $this->assertSame('<p><a>ссылка</a></p>', HtmlSanitizer::clean('<p><a href="'.$href.'">ссылка</a></p>'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'with spaces' => [' java script:alert(1)'],
            'upper case' => ['JaVaScRiPt:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'protocol relative' => ['//evil.example'],
        ];
    }

    public function test_a_new_tab_link_gets_noopener(): void
    {
        $this->assertSame(
            '<p><a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a></p>',
            HtmlSanitizer::clean('<p><a href="https://example.com" target="_blank" rel="opener">x</a></p>'),
        );
    }

    public function test_unknown_tags_are_unwrapped_but_their_text_stays(): void
    {
        $this->assertSame('<p>важный текст</p>', HtmlSanitizer::clean('<p><font color="red">важный</font> <mark>текст</mark></p>'));
    }

    public function test_a_quill_bullet_list_becomes_a_real_bullet_list(): void
    {
        $clean = HtmlSanitizer::clean('<ol><li data-list="bullet"><span class="ql-ui"></span>Один</li><li data-list="bullet">Два</li></ol>');

        $this->assertSame('<ul><li data-list="bullet">Один</li><li data-list="bullet">Два</li></ul>', $clean);
    }

    public function test_an_empty_editor_is_an_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean('<p><br></p>'));
    }

    #[DataProvider('videoLinks')]
    public function test_video_links_become_player_addresses(string $url, ?string $src): void
    {
        $this->assertSame($src, VideoEmbed::parse($url)['src'] ?? null);
    }

    public function test_player_settings_become_player_parameters(): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&loop=1&playlist=dQw4w9WgXcQ&controls=0',
            VideoEmbed::parse('https://youtu.be/dQw4w9WgXcQ', ['autoplay' => true, 'muted' => true, 'loop' => true, 'controls' => false])['src'],
        );
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function videoLinks(): array
    {
        return [
            'youtube' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'rutube' => ['https://rutube.ru/video/0123456789abcdef0123456789abcdef/', 'https://rutube.ru/play/embed/0123456789abcdef0123456789abcdef'],
            'vk' => ['https://vkvideo.ru/video-12345_67890', 'https://vkvideo.ru/video_ext.php?oid=-12345&id=67890'],
            'any site' => ['https://evil.example/watch?v=dQw4w9WgXcQ', null],
            'lookalike host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ', null],
        ];
    }
}
