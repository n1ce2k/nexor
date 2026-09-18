<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Updates\ComposerRunner;
use Nexor\Cms\Support\Updates\PackageCatalog;
use Nexor\Cms\Support\Updates\UpdatePlan;

/**
 * Раздел «Обновления» и установка модулей.
 *
 * Обновление запускает composer, то есть исполняет код на сервере, — поэтому
 * здесь стоят отдельные ограничители: право `updates.manage`, выключатель в
 * конфигурации и закрытый список пакетов. Ничего из того, что пришло из
 * браузера, в команду не попадает: приходит только код пакета из списка.
 */
class UpdateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $fresh = $request->boolean('refresh');

        $packages = array_map(
            fn (string $code) => PackageCatalog::describe($code, $fresh),
            array_keys(PackageCatalog::all()),
        );

        return response()->json([
            'enabled' => $this->enabled(),
            'availability' => ComposerRunner::availability(),
            'packages' => $packages,
            'updates_available' => count(array_filter($packages, fn (array $package) => $package['update_available'])),
            'current' => ComposerRunner::status(),
            'version' => Nexor::VERSION,
        ]);
    }

    /**
     * Обновление установленных пакетов.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeRun($request);

        $data = $request->validate([
            'package' => ['nullable', 'string'],
        ]);

        $codes = isset($data['package']) ? [$data['package']] : array_keys(PackageCatalog::all());
        $packages = [];

        foreach ($codes as $code) {
            $entry = PackageCatalog::find($code);

            if ($entry === null) {
                return $this->refuse('Неизвестный пакет.');
            }

            if (PackageCatalog::installed($entry['package'])) {
                $packages[] = $entry['package'];
            }
        }

        if ($packages === []) {
            return $this->refuse('Обновлять нечего: эти пакеты не установлены.');
        }

        $plan = UpdatePlan::update($packages);

        return $this->run($plan, 'update', implode(', ', $packages));
    }

    /**
     * Установка модуля из списка доступных.
     */
    public function install(Request $request): JsonResponse
    {
        $this->authorizeRun($request);

        $data = $request->validate([
            'package' => ['required', 'string'],
        ]);

        $entry = PackageCatalog::find($data['package']);

        if ($entry === null || $entry['module'] === null) {
            return $this->refuse('Такого модуля нет в списке.');
        }

        if (PackageCatalog::installed($entry['package'])) {
            return $this->refuse('Модуль «'.$entry['name'].'» уже установлен.');
        }

        if (! Nexor::license()->allows($entry['license'])) {
            return $this->refuse('Модуль «'.$entry['name'].'» доступен с редакции '.$entry['license']->label().'.');
        }

        return $this->run(UpdatePlan::install($entry), 'install', $entry['package']);
    }

    /**
     * Ход задачи: панель опрашивает этот адрес, пока идёт установка.
     */
    public function status(Request $request, ?string $id = null): JsonResponse
    {
        $status = ComposerRunner::status($id);

        if ($status === null) {
            return response()->json(['state' => null]);
        }

        return response()->json($status);
    }

    /**
     * @param  array{title: string, steps: array<int, array<string, mixed>>}  $plan
     */
    protected function run(array $plan, string $action, string $subject): JsonResponse
    {
        $availability = ComposerRunner::availability();

        if (! $availability['ok']) {
            return $this->refuse((string) $availability['reason']);
        }

        if (ComposerRunner::isRunning()) {
            return $this->refuse('Одна задача уже выполняется — дождитесь её окончания.');
        }

        $id = ComposerRunner::start($plan['title'], $plan['steps']);

        ActivityLogger::log('update.'.$action, null, $plan['title'], ['package' => $subject]);

        return $this->ok('Задача запущена.', ['id' => $id, 'current' => ComposerRunner::status($id)]);
    }

    protected function enabled(): bool
    {
        return (bool) config('nexor.updates.enabled', true);
    }

    protected function authorizeRun(Request $request): void
    {
        abort_unless($this->enabled(), 403, 'Обновление из панели выключено в настройках сайта.');
        abort_unless($request->user()?->hasPermission('updates.manage'), 403);
    }
}
