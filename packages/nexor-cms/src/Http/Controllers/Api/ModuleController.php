<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Modules\Module;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Permissions;

/**
 * Страница «Модули»: лицензия сайта, функции по уровням и установленные модули.
 */
class ModuleController extends ApiController
{
    public function index(): JsonResponse
    {
        $manager = Nexor::modules();
        $license = $manager->license();

        return response()->json([
            'license' => ['value' => $license->value, 'label' => $license->label(), 'rank' => $license->rank()],
            'licenses' => License::options(),
            'features' => collect($manager->features())->map(fn (array $feature, string $code) => [
                'code' => $code,
                'label' => $feature['label'],
                'license' => $feature['license']->value,
                'module' => $feature['module'],
                'allowed' => $manager->allows($code),
            ])->values(),
            'modules' => collect($manager->all())->map(fn (Module $module) => $this->describe($module))->values(),
        ]);
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $manager = Nexor::modules();
        $module = $manager->find($code);

        abort_unless($module, 404);

        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);

        $state = $manager->setEnabled($code, (bool) $data['is_enabled']);

        // Включённому модулю нужны его права в каталоге ролей.
        Permissions::syncStatic();

        ActivityLogger::updated($state, ($data['is_enabled'] ? 'Включён' : 'Выключен').' модуль «'.$module->name().'»');

        return $this->ok(
            'Модуль «'.$module->name().'» '.($data['is_enabled'] ? 'включён.' : 'выключен.'),
            ['module' => $this->describe($module)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(Module $module): array
    {
        $manager = Nexor::modules();

        return [
            'code' => $module->code(),
            'name' => $module->name(),
            'description' => $module->description(),
            'version' => $module->version(),
            'license' => ['value' => $module->license()->value, 'label' => $module->license()->label()],
            'is_licensed' => $manager->license()->allows($module->license()),
            'is_enabled' => $manager->switchedOn($module->code()),
            'is_active' => $manager->enabled($module->code()),
        ];
    }
}
