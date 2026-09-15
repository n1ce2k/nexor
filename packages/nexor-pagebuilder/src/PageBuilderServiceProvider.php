<?php

namespace Nexor\PageBuilder;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Nexor;
use Nexor\PageBuilder\Blocks\AccordionBlock;
use Nexor\PageBuilder\Blocks\BlockRegistry;
use Nexor\PageBuilder\Blocks\HeaderBlock;
use Nexor\PageBuilder\Blocks\PhotoBlock;
use Nexor\PageBuilder\Blocks\QuoteBlock;
use Nexor\PageBuilder\Blocks\TableBlock;
use Nexor\PageBuilder\Blocks\TextBlock;
use Nexor\PageBuilder\Blocks\TextImageBlock;
use Nexor\PageBuilder\Blocks\VideoBlock;
use Nexor\PageBuilder\Console\InstallCommand;
use Nexor\PageBuilder\Console\PublishComponentCommand;
use Nexor\PageBuilder\Http\Controllers\AssetController;
use Nexor\PageBuilder\Models\Layout;

class PageBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/nexor-pagebuilder.php'), 'nexor-pagebuilder');

        // Раньше boot() ядра: оно подключит маршруты модуля.
        Nexor::modules()->register(new PageBuilderModule);

        $this->app->singleton(BlockRegistry::class, function (): BlockRegistry {
            $registry = new BlockRegistry;

            foreach ([
                new HeaderBlock, new TextBlock, new QuoteBlock, new TextImageBlock,
                new PhotoBlock, new VideoBlock, new AccordionBlock, new TableBlock,
            ] as $block) {
                $registry->register($block);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'nexor-pagebuilder');

        // Стили и скрипт блоков для сайта: статика без сессии.
        if (! $this->app->routesAreCached()) {
            Route::get('nexor-pagebuilder/assets/{file}', AssetController::class)
                ->where('file', '[a-z]+\.(css|js)')
                ->name('nexor-pagebuilder.assets');
        }

        // Элемент удалён насовсем — его блоки больше никому не нужны.
        IblockElement::forceDeleted(function (IblockElement $element): void {
            Layout::query()->where('owner_type', Layout::OWNER_ELEMENT)->where('owner_id', $element->id)->delete();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishComponentCommand::class,
            ]);

            $this->publishes([
                $this->path('resources/views') => resource_path('views/vendor/nexor-pagebuilder'),
            ], 'nexor-pagebuilder-views');

            $this->publishes([
                $this->path('config/nexor-pagebuilder.php') => config_path('nexor-pagebuilder.php'),
            ], 'nexor-pagebuilder-config');
        }
    }

    protected function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
