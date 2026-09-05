<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Models\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Renders every admin screen once.
 *
 * The forms build themselves from Blade components and, for elements, from the
 * infoblock's properties — this catches a broken template that unit-level tests
 * would never touch.
 */
class AdminScreensTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_every_admin_screen_renders(): void
    {
        $this->seed(SettingSeeder::class);

        $admin = $this->superAdmin();
        $user = User::factory()->create();
        $role = Role::factory()->create();

        $iblock = Iblock::factory()->create();
        $section = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $element = IblockElement::factory()->for($iblock)->create();

        // One property of every type, so the element form exercises each editor.
        foreach (PropertyType::cases() as $index => $type) {
            IblockProperty::factory()->for($iblock)->ofType($type)->create([
                'code' => 'P_'.strtoupper($type->value),
                'name' => $type->label(),
                'sort' => ($index + 1) * 100,
                'is_shown_in_list' => true,
                'is_filterable' => $type->isFilterable(),
            ]);
        }

        $multiple = IblockProperty::factory()->for($iblock)->multiple()->create(['code' => 'P_MULTI']);
        $select = IblockProperty::factory()->for($iblock)->withEnums(['Один', 'Два'])->create(['code' => 'P_ENUM']);

        $screens = [
            route('admin.dashboard'),
            route('admin.profile.edit'),

            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.show', $user),
            route('admin.users.edit', $user),

            route('admin.roles.index'),
            route('admin.roles.create'),
            route('admin.roles.edit', $role),

            route('admin.iblock-types.index'),
            route('admin.iblock-types.create'),
            route('admin.iblock-types.edit', $iblock->type),

            route('admin.iblocks.index'),
            route('admin.iblocks.create'),
            route('admin.iblocks.edit', $iblock),

            route('admin.iblocks.properties.index', $iblock),
            route('admin.iblocks.properties.create', $iblock),
            route('admin.iblocks.properties.edit', [$iblock, $select]),
            route('admin.iblocks.properties.edit', [$iblock, $multiple]),

            route('admin.iblocks.sections.index', $iblock),
            route('admin.iblocks.sections.create', $iblock),
            route('admin.iblocks.sections.edit', [$iblock, $section]),

            route('admin.iblocks.elements.index', $iblock),
            route('admin.iblocks.elements.create', $iblock),
            route('admin.iblocks.elements.edit', [$iblock, $element]),

            route('admin.settings.index'),
            route('admin.logs.index'),
        ];

        foreach ($screens as $url) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk("Экран {$url} не отрендерился");
        }
    }

    #[DataProvider('restrictedScreens')]
    public function test_a_screen_is_closed_without_its_permission(string $route): void
    {
        $this->actingAs($this->adminWith())
            ->get(route($route))
            ->assertForbidden();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function restrictedScreens(): array
    {
        return [
            'пользователи' => ['admin.users.index'],
            'роли' => ['admin.roles.index'],
            'типы инфоблоков' => ['admin.iblock-types.index'],
            'инфоблоки' => ['admin.iblocks.index'],
            'настройки' => ['admin.settings.index'],
            'журнал' => ['admin.logs.index'],
        ];
    }
}
