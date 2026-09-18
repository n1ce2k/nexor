<?php

namespace Nexor\Cms\Support\Modules;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Models\ModuleState;
use Nexor\Cms\Support\Licensing;

/**
 * Лицензия сайта, установленные модули и доступные функции.
 *
 * Функция — это код вида `catalog.offers` или `shop.promocodes` с минимальным
 * уровнем лицензии. Код модуля (`shop`) — тоже функция: «модуль включён».
 * Всё, что прячется по лицензии, спрашивает только `allows()`.
 */
class ModuleManager
{
    /**
     * Функции самого ядра.
     *
     * @return array<string, array{label: string, license: License}>
     */
    public static function coreFeatures(): array
    {
        return [
            'catalog.offers' => ['label' => 'Торговые предложения', 'license' => License::Standart],
        ];
    }

    /** @var array<string, Module> */
    protected array $modules = [];

    /** @var Collection<string, ModuleState>|null */
    protected ?Collection $states = null;

    public function register(Module $module): void
    {
        $this->modules[$module->code()] = $module;
    }

    /**
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(string $code): ?Module
    {
        return $this->modules[$code] ?? null;
    }

    /**
     * Уровень лицензии: его задаёт ключ сайта.
     *
     * Всё, что про разбор ключа и запасной уровень, живёт в Licensing —
     * здесь остаётся только вопрос «что нам доступно».
     */
    public function license(): License
    {
        return Licensing::edition();
    }

    /**
     * Модуль установлен, включён на сайте и покрыт лицензией.
     */
    public function enabled(string $code): bool
    {
        $module = $this->find($code);

        if (! $module || ! $this->license()->allows($module->license())) {
            return false;
        }

        return $this->state($code)?->is_enabled ?? true;
    }

    /**
     * Включён ли модуль на сайте — без оглядки на лицензию.
     */
    public function switchedOn(string $code): bool
    {
        return $this->state($code)?->is_enabled ?? true;
    }

    public function setEnabled(string $code, bool $enabled): ModuleState
    {
        $state = ModuleState::query()->firstOrNew(['code' => $code]);
        $state->is_enabled = $enabled;
        $state->save();

        $this->states = null;

        return $state;
    }

    /**
     * Настройки модуля: сохранённые поверх умолчаний.
     *
     * @return array<string, mixed>
     */
    public function settings(string $code): array
    {
        $defaults = $this->find($code)?->defaultSettings() ?? [];

        return array_replace_recursive($defaults, $this->state($code)?->settings ?? []);
    }

    /**
     * Дописывает настройки модуля. Вложенные ключи заменяются целиком: список
     * курсов валют, например, приходит полностью, а не по одному курсу.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateSettings(string $code, array $values): array
    {
        $state = ModuleState::query()->firstOrNew(['code' => $code]);
        $state->settings = array_replace($state->settings ?? [], $values);
        $state->save();

        $this->states = null;

        return $this->settings($code);
    }

    /**
     * Все функции: ядра и модулей.
     *
     * @return array<string, array{label: string, license: License, module: string|null}>
     */
    public function features(): array
    {
        $features = [];

        foreach (self::coreFeatures() as $code => $feature) {
            $features[$code] = $feature + ['module' => null];
        }

        foreach ($this->modules as $module) {
            foreach ($module->features() as $code => $feature) {
                $features[$code] = $feature + ['module' => $module->code()];
            }
        }

        return $features;
    }

    /**
     * Доступна ли функция или модуль прямо сейчас.
     */
    public function allows(string $code): bool
    {
        if (isset($this->modules[$code])) {
            return $this->enabled($code);
        }

        $feature = $this->features()[$code] ?? null;

        if (! $feature || ! $this->license()->allows($feature['license'])) {
            return false;
        }

        return $feature['module'] === null || $this->enabled($feature['module']);
    }

    /**
     * Карта «код → доступно» для панели.
     *
     * @return array<string, bool>
     */
    public function allowed(): array
    {
        $map = [];

        foreach (array_keys($this->modules) as $code) {
            $map[$code] = $this->enabled($code);
        }

        foreach (array_keys($this->features()) as $code) {
            $map[$code] = $this->allows($code);
        }

        return $map;
    }

    /**
     * Права всех установленных модулей — даже выключенных, чтобы роли можно
     * было настроить заранее.
     *
     * @return array<string, array{label: string, sort: int, items: array<string, string>}>
     */
    public function permissionDefinitions(): array
    {
        $definitions = [];

        foreach ($this->modules as $module) {
            $definitions += $module->permissions();
        }

        return $definitions;
    }

    protected function state(string $code): ?ModuleState
    {
        if ($this->states === null) {
            // До первой миграции таблицы ещё нет, а провайдеры уже спрашивают.
            $this->states = Schema::hasTable('modules')
                ? ModuleState::query()->get()->keyBy('code')
                : collect();
        }

        return $this->states->get($code);
    }
}
