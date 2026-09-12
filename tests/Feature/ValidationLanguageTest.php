<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Панель говорит по-русски — значит, и её отказы тоже.
 *
 * Без файла переводов Laravel отвечает встроенными английскими строками, и в
 * форме появлялось «The символьный код has already been taken.».
 */
class ValidationLanguageTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_a_required_field_is_reported_in_russian(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $message = $this->actingAs($admin)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [])
            ->assertStatus(422)
            ->json('errors.name.0');

        $this->assertSame('Поле название обязательно для заполнения.', $message);
    }

    public function test_a_taken_code_explains_itself(): void
    {
        $iblock = Iblock::factory()->create();
        IblockElement::factory()->for($iblock)->create(['code' => 'stul']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $message = $this->actingAs($admin)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", ['name' => 'Стул', 'code' => 'stul'])
            ->assertStatus(422)
            ->json('errors.code.0');

        $this->assertSame('Такой символьный код в этом инфоблоке уже занят — измените его.', $message);
    }

    public function test_the_same_code_is_free_in_another_infoblock(): void
    {
        $first = Iblock::factory()->create();
        IblockElement::factory()->for($first)->create(['code' => 'stul']);

        $second = Iblock::factory()->create();
        $admin = $this->grantIblock($this->adminWith(), $second, ['view', 'create']);

        // Коды уникальны внутри инфоблока, а не по всей базе.
        $this->actingAs($admin)
            ->postJson("/admin/api/iblocks/{$second->id}/elements", ['name' => 'Стул', 'code' => 'stul'])
            ->assertCreated();
    }
}
