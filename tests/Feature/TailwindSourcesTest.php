<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Nexor\Cms\Support\TailwindSources;
use Tests\TestCase;

class TailwindSourcesTest extends TestCase
{
    protected string $css;

    protected function setUp(): void
    {
        parent::setUp();

        $this->css = storage_path('framework/testing/tailwind-app.css');
        File::ensureDirectoryExists(dirname($this->css));
    }

    protected function tearDown(): void
    {
        File::delete($this->css);

        parent::tearDown();
    }

    public function test_the_package_views_are_added_next_to_the_other_sources(): void
    {
        File::put($this->css, "@import 'tailwindcss';\n\n@source '../views';\n\n@theme {}\n");

        $line = TailwindSources::add(base_path('vendor/n1ce2k/nexor-shop/resources/views'), $this->css);

        $this->assertSame("@source '../../vendor/n1ce2k/nexor-shop/resources/views';", $line);
        $this->assertSame(
            "@import 'tailwindcss';\n\n@source '../views';\n{$line}\n\n@theme {}\n",
            File::get($this->css),
        );
    }

    public function test_without_sources_the_line_goes_after_the_tailwind_import(): void
    {
        File::put($this->css, "@import 'tailwindcss';\n\n@theme {}\n");

        TailwindSources::add(base_path('packages/nexor-cms/resources/views/components'), $this->css);

        $this->assertSame(
            "@import 'tailwindcss';\n\n@source '../../packages/nexor-cms/resources/views/components';\n\n@theme {}\n",
            File::get($this->css),
        );
    }

    public function test_running_twice_adds_nothing(): void
    {
        File::put($this->css, "@import 'tailwindcss';\n");
        $views = base_path('vendor/n1ce2k/nexor-cms/resources/views/site');

        TailwindSources::add($views, $this->css);
        $once = File::get($this->css);

        $this->assertNull(TailwindSources::add($views, $this->css));
        $this->assertSame($once, File::get($this->css));
    }

    public function test_a_site_without_app_css_is_left_alone(): void
    {
        $this->assertNull(TailwindSources::add(base_path('vendor/x/views'), $this->css));
        $this->assertFileDoesNotExist($this->css);
    }
}
