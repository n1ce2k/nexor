<?php

namespace Nexor\Shop;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Console\InstallCommand;
use Nexor\Shop\Console\PublishComponentCommand;
use Nexor\Shop\Support\Cart;

class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/nexor-shop.php'), 'nexor-shop');

        // Раньше boot() ядра: оно подключит маршруты модуля.
        Nexor::modules()->register(new ShopModule);

        // Одна корзина на запрос. Не scoped-синглтон: между запросами одного
        // процесса (тесты, Octane) корзина обязана перечитываться из cookie.
        $this->app->bind(Cart::class, function ($app): Cart {
            /** @var Request $request */
            $request = $app['request'];

            if (! $request->attributes->has(Cart::class)) {
                $request->attributes->set(Cart::class, new Cart($request));
            }

            return $request->attributes->get(Cart::class);
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->path('database/migrations'));
        $this->loadViewsFrom($this->path('resources/views'), 'nexor-shop');

        // Уведомления ЮKassa приходят без сессии и без токена формы: подписи
        // у них нет, поэтому состояние платежа мы всё равно перечитываем у неё.
        foreach ([PreventRequestForgery::class, ValidateCsrfToken::class] as $csrf) {
            if (class_exists($csrf) && method_exists($csrf, 'except')) {
                $csrf::except('shop/payment/yookassa');

                break;
            }
        }

        // <livewire:nexor-shop::cart-page /> → Nexor\Shop\Livewire\CartPage
        Livewire::addNamespace('nexor-shop', classNamespace: 'Nexor\\Shop\\Livewire');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishComponentCommand::class,
            ]);

            $this->publishes([
                $this->path('config/nexor-shop.php') => config_path('nexor-shop.php'),
            ], 'nexor-shop-config');

            $this->publishes([
                $this->path('resources/views') => resource_path('views/vendor/nexor-shop'),
            ], 'nexor-shop-views');
        }
    }

    protected function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
