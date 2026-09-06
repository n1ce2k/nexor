<?php

namespace Nexor\Cms;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nexor\Cms\Console\InstallCommand;
use Nexor\Cms\Console\LoginLinkCommand;
use Nexor\Cms\Console\SetPasswordCommand;
use Nexor\Cms\Console\SyncPermissionsCommand;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Http\Middleware\CheckIblockPermission;
use Nexor\Cms\Http\Middleware\CheckMaintenanceMode;
use Nexor\Cms\Http\Middleware\CheckPermission;
use Nexor\Cms\Http\Middleware\EnsureUserCanAccessAdmin;
use Nexor\Cms\Support\MailConfig;
use Nexor\Cms\Support\Nexor;

class NexorServiceProvider extends ServiceProvider
{
    /** @var array<string, class-string> */
    protected array $middleware = [
        'nexor.admin' => EnsureUserCanAccessAdmin::class,
        'nexor.permission' => CheckPermission::class,
        'nexor.iblock' => CheckIblockPermission::class,
        'nexor.maintenance' => CheckMaintenanceMode::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/nexor.php'), 'nexor');

        // The HTTP kernel replaces the router's middleware groups when it is
        // resolved, so the guard is re-attached right after that happens too.
        $this->app->afterResolving(HttpKernel::class, fn () => $this->registerMaintenanceMode());
    }

    public function boot(): void
    {
        $this->registerMiddleware();
        $this->registerMaintenanceMode();
        $this->registerRoutes();
        $this->registerMail();
        $this->registerBindings();
        $this->registerGates();

        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'nexor');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                LoginLinkCommand::class,
                SetPasswordCommand::class,
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

        // The API is registered first: the panel's catch-all route would
        // otherwise swallow every /admin/api/* request.
        Route::group([
            'prefix' => Nexor::routePrefix().'/api',
            'as' => 'admin.api.',
            'middleware' => [...$middleware, 'auth', 'nexor.admin'],
        ], fn () => $this->loadRoutesFrom($this->path('routes/api.php')));

        Route::group([
            'prefix' => Nexor::routePrefix(),
            'as' => 'admin.',
            'middleware' => $middleware,
        ], fn () => $this->loadRoutesFrom($this->path('routes/admin.php')));
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
    }

    protected function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
