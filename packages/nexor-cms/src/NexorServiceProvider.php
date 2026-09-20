<?php

namespace Nexor\Cms;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Nexor\Cms\Console\InstallCommand;
use Nexor\Cms\Console\LicenseCommand;
use Nexor\Cms\Console\LoginLinkCommand;
use Nexor\Cms\Console\PublishComponentCommand;
use Nexor\Cms\Console\RunUpdateCommand;
use Nexor\Cms\Console\ScanContentCommand;
use Nexor\Cms\Console\SetPasswordCommand;
use Nexor\Cms\Console\SyncPermissionsCommand;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Http\Controllers\AssetController;
use Nexor\Cms\Http\Middleware\CheckFeature;
use Nexor\Cms\Http\Middleware\CheckIblockPermission;
use Nexor\Cms\Http\Middleware\CheckMaintenanceMode;
use Nexor\Cms\Http\Middleware\CheckPermission;
use Nexor\Cms\Http\Middleware\EnsureUserCanAccessAdmin;
use Nexor\Cms\Http\Middleware\InjectInlineEditor;
use Nexor\Cms\Services\InfoBlockService;
use Nexor\Cms\Support\MailConfig;
use Nexor\Cms\Support\Modules\ModuleManager;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\View\Components\News\Listing as NewsListing;

class NexorServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string> */
    protected array $middleware = [
        'nexor.admin' => EnsureUserCanAccessAdmin::class,
        'nexor.permission' => CheckPermission::class,
        'nexor.iblock' => CheckIblockPermission::class,
        'nexor.maintenance' => CheckMaintenanceMode::class,
        'nexor.feature' => CheckFeature::class,
    ];

    public function register(): void
    {
        $this->mergeNestedConfig($this->path('config/nexor.php'), 'nexor');

        // Модули регистрируются в register() своих провайдеров — раньше, чем
        // ядро в boot() подключит их маршруты.
        $this->app->singleton(ModuleManager::class);

        // Ядро выборок для публичной части. Один экземпляр на запрос, чтобы
        // компоненты на одной странице не искали инфоблок по коду заново.
        $this->app->singleton(InfoBlockService::class);

        // The HTTP kernel replaces the router's middleware groups when it is
        // resolved, so the guard is re-attached right after that happens too.
        $this->app->afterResolving(HttpKernel::class, fn () => $this->registerMaintenanceMode());
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerMaintenanceMode();
        $this->registerInlineEditor();
        $this->registerRoutes();
        $this->registerMail();
        $this->registerBindings();
        $this->registerGates();

        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'nexor');
        $this->registerComponents();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                LicenseCommand::class,
                LoginLinkCommand::class,
                PublishComponentCommand::class,
                RunUpdateCommand::class,
                SetPasswordCommand::class,
                ScanContentCommand::class,
                SyncPermissionsCommand::class,
            ]);

            $this->registerPublishing();
        }
    }

    /**
     * Aliases are registered under a `nexor.` prefix so they can never collide
     * with middleware the host application already defines.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        foreach ($this->middleware as $alias => $class) {
            $router->aliasMiddleware($alias, $class);
        }

        // Short aliases too, but only when the application has not claimed them.
        foreach ($this->middleware as $alias => $class) {
            $short = str_replace('nexor.', '', $alias);

            if (! array_key_exists($short, $router->getMiddleware())) {
                $router->aliasMiddleware($short, $class);
            }
        }
    }

    /**
     * Guards the public site while maintenance mode is on.
     *
     * Pushed onto the `web` group rather than a route file, so pages the host
     * application declares are covered too. The middleware lets the panel and
     * signed-in users through.
     *
     * Called twice on purpose — once here and once after the kernel resolves —
     * because whichever runs last is the one that survives. The push itself is
     * idempotent.
     */
    protected function registerMaintenanceMode(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', CheckMaintenanceMode::class);
    }

    /**
     * Режим правки блоков на страницах сайта.
     *
     * Тоже на группе `web`: править можно и страницы, которые объявляет само
     * приложение, а не только те, что пришли из пакета.
     */
    protected function registerInlineEditor(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', InjectInlineEditor::class);
    }

    /**
     * SMTP credentials entered in the panel win over the ones in .env.
     */
    protected function registerMail(): void
    {
        $this->app->booted(function (): void {
            if (Schema::hasTable('settings')) {
                MailConfig::apply();
            }
        });
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $middleware = config('nexor.route.middleware', ['web']);

        // Готовая сборка админки — статика без сессии; раньше catch-all панели.
        Route::get(Nexor::routePrefix().'/nexor-assets/{package}/{path}', AssetController::class)
            ->where('package', '[a-z0-9-]+')
            ->where('path', '.+')
            ->name('admin.assets');

        // The API is registered first: the panel's catch-all route would
        // otherwise swallow every /admin/api/* request.
        Route::group([
            'prefix' => Nexor::routePrefix().'/api',
            'as' => 'admin.api.',
            // Без 'auth': гостя разворачивает сам nexor.admin. Стандартный
            // auth ведёт на маршрут login, которого в чистом Laravel нет.
            'middleware' => [...$middleware, 'nexor.admin'],
        ], function (): void {
            $this->loadRoutesFrom($this->path('routes/api.php'));

            // API модулей — внутри той же группы и тоже до catch-all панели.
            // Выключенный модуль отвечает 404 целиком.
            foreach (Nexor::modules()->all() as $module) {
                if ($file = $module->apiRoutes()) {
                    Route::middleware('nexor.feature:'.$module->code())->group($file);
                }
            }
        });

        Route::group([
            'prefix' => Nexor::routePrefix(),
            'as' => 'admin.',
            'middleware' => $middleware,
        ], fn () => $this->loadRoutesFrom($this->path('routes/admin.php')));

        // Публичная часть: приём форм компонента `form`.
        Route::group(['middleware' => $middleware], function (): void {
            $this->loadRoutesFrom($this->path('routes/site.php'));

            // Главная, поиск и страницы инфоблоков — fallback-маршруты: маршруты
            // приложения с теми же адресами всегда важнее.
            if (config('nexor.site.routes', true)) {
                $this->loadRoutesFrom($this->path('routes/pages.php'));
            }

            foreach (Nexor::modules()->all() as $module) {
                if ($file = $module->webRoutes()) {
                    Route::middleware('nexor.feature:'.$module->code())->group($file);
                }
            }
        });
    }

    /**
     * The user model lives in the host app, so `{user}` cannot be resolved by
     * implicit binding against a package class.
     */
    protected function registerBindings(): void
    {
        Route::bind('user', fn (string $value): Model => Nexor::newUser()
            ->newQuery()
            ->where(Nexor::newUser()->getRouteKeyName(), $value)
            ->firstOrFail());
    }

    /**
     * Permission codes live in the database, so instead of declaring a gate per
     * ability any dotted ability is resolved against the user's permissions.
     */
    protected function registerGates(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof NexorUser) {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });
    }

    /**
     * Компоненты публичной части: `<x-nexor::catalog.section />`.
     *
     * Имя тега разворачивается в класс: `catalog.section` →
     * `Nexor\Cms\View\Components\Catalog\Section`. Если класса нет, Blade
     * ищет анонимный компонент в `resources/views/components` пакета —
     * именно так живут `<x-nexor::admin.*>` из Blade-админки.
     */
    protected function registerComponents(): void
    {
        Blade::componentNamespace('Nexor\Cms\View\Components', 'nexor');

        // @feature('shop') … @endfeature — кусок шаблона только для доступной функции.
        Blade::if('feature', fn (string $code) => Nexor::feature($code));
        // `list` — зарезервированное слово PHP, класса `News\List` не бывает.
        // А имя `news.list` привычное, поэтому тег связан с классом псевдонимом.
        Blade::component(NewsListing::class, 'nexor::news.list');

        // Форма обратной связи без перезагрузки: <livewire:nexor::feedback-form :form-id="3" />
        // (обычно её подключает сам <x-nexor::form :id="3" />).
        Livewire::addNamespace('nexor', classNamespace: 'Nexor\\Cms\\Livewire');
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            $this->path('config/nexor.php') => config_path('nexor.php'),
        ], 'nexor-config');

        $this->publishes([
            $this->path('resources/views') => resource_path('views/vendor/nexor'),
        ], 'nexor-views');

        $this->publishes([
            $this->path('database/seeders') => database_path('seeders/nexor'),
        ], 'nexor-seeders');

        // Стартовые шаблоны сайта: макет, главная, страница, поиск, 404.
        $this->publishes([
            $this->path('stubs/site/views/site') => resource_path('views/site'),
            $this->path('stubs/site/views/errors') => resource_path('views/errors'),
        ], 'nexor-site');

        // Шаблоны компонентов целиком. Отдельный компонент удобнее
        // забирать командой `php artisan nexor:component`.
        $this->publishes([
            $this->path('resources/views/components') => resource_path('views/vendor/nexor/components'),
        ], 'nexor-components');
    }

    /**
     * Как `mergeConfigFrom`, но и во вложенных разделах.
     *
     * Опубликованный `config/nexor.php` мог появиться раньше нового ключа
     * пакета: штатное слияние заменило бы раздел `panel` целиком, и ключ
     * `panel.assets` пропал бы вместе с `NEXOR_PANEL_ASSETS`. Здесь недостающие
     * ключи берутся из пакета, а списки (массивы без ключей) сайта не
     * смешиваются с пакетными.
     */
    protected function mergeNestedConfig(string $path, string $key): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set($key, $this->withDefaults($config->get($key, []), require $path));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>  $defaults
     * @return array<array-key, mixed>
     */
    protected function withDefaults(array $values, array $defaults): array
    {
        if (array_is_list($values) && $values !== []) {
            return $values;
        }

        foreach ($defaults as $name => $default) {
            if (! array_key_exists($name, $values)) {
                $values[$name] = $default;
            } elseif (is_array($values[$name]) && is_array($default) && ! array_is_list($default)) {
                $values[$name] = $this->withDefaults($values[$name], $default);
            }
        }

        return $values;
    }

    protected function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
