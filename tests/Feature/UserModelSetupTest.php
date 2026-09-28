<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Support\UserModelSetup;
use Tests\TestCase;

/**
 * `nexor:install` сам готовит модель пользователя приложения: на свежем
 * Laravel в ней нет ни контракта, ни трейтов, и установка падала на первой
 * же учётной записи.
 */
class UserModelSetupTest extends TestCase
{
    // База нужна только экрану панели: до него доходит middleware настроек.
    use RefreshDatabase;

    /** Модель пользователя, как её создаёт свежий Laravel. */
    protected function stockModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
PHP;
    }

    /** Тот же образец, но с гарантированными переводами строк LF. */
    protected function lines(): string
    {
        return str_replace("\r\n", "\n", $this->stockModel());
    }

    public function test_the_stock_model_gets_the_contract_traits_and_columns(): void
    {
        $patched = UserModelSetup::apply($this->lines());

        $this->assertStringNotContainsString("\r", $patched);

        $this->assertStringContainsString('use Nexor\Cms\Contracts\NexorUser;', $patched);
        $this->assertStringContainsString('use Nexor\Cms\Models\Concerns\HasRoles;', $patched);
        $this->assertStringContainsString('use Nexor\Cms\Models\Concerns\HasUserFields;', $patched);
        $this->assertStringContainsString('class User extends Authenticatable implements NexorUser', $patched);
        $this->assertStringContainsString('use HasFactory, HasRoles, HasUserFields, Notifiable;', $patched);
        $this->assertStringContainsString(
            "#[Fillable(['name', 'email', 'password', 'login', 'phone', 'avatar', 'is_active', 'is_super_admin'])]",
            $patched,
        );

        // Без приведений типов панель получала бы дату строкой и падала.
        $this->assertStringContainsString("'is_active' => 'boolean',", $patched);
        $this->assertStringContainsString("'is_super_admin' => 'boolean',", $patched);
        $this->assertStringContainsString("'last_login_at' => 'datetime',", $patched);
        $this->assertStringContainsString("'password' => 'hashed',", $patched);
    }

    public function test_patching_twice_changes_nothing(): void
    {
        $once = UserModelSetup::apply($this->stockModel());

        $this->assertSame($once, UserModelSetup::apply($once));
    }

    public function test_an_old_style_fillable_property_is_extended(): void
    {
        $source = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements Something
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
PHP;

        $patched = UserModelSetup::apply($source);

        $this->assertStringContainsString('implements Something, NexorUser', $patched);
        $this->assertStringContainsString('use HasRoles, HasUserFields, Notifiable;', $patched);
        $this->assertStringContainsString("protected \$fillable = ['name', 'email', 'password', 'login',", $patched);
    }

    public function test_a_windows_model_with_crlf_is_patched_too(): void
    {
        // На Windows модель обычно лежит с CRLF — на них разбор и спотыкался.
        $crlf = str_replace("\n", "\r\n", $this->lines());
        $patched = UserModelSetup::apply($crlf);

        $this->assertNotNull($patched);
        $this->assertStringContainsString("use Nexor\Cms\Contracts\NexorUser;\r\n", $patched);
        $this->assertStringContainsString('implements NexorUser', $patched);
        $this->assertStringContainsString("'last_login_at' => 'datetime',\r\n", $patched);
        $this->assertStringNotContainsString("\r\r", $patched);
    }

    public function test_an_unusual_model_is_left_alone(): void
    {
        $this->assertNull(UserModelSetup::apply("<?php\n\n// пусто\n"));
    }

    public function test_the_model_of_this_application_is_already_ready(): void
    {
        $this->assertSame([], UserModelSetup::missing(User::class));
    }

    public function test_a_patched_model_needs_nothing_more(): void
    {
        // Так проверяется только что дописанный файл: загруженный класс в том
        // же процессе остался прежним и соврал бы.
        $this->assertFalse(UserModelSetup::sourceReady($this->lines()));
        $this->assertTrue(UserModelSetup::sourceReady(UserModelSetup::apply($this->lines())));
    }

    public function test_the_command_sees_a_ready_model(): void
    {
        $this->artisan('nexor:user-model')->assertSuccessful();
    }

    public function test_the_panel_explains_an_unprepared_model(): void
    {
        config(['nexor.user_model' => UnpreparedUser::class]);

        // Раньше тут падал BadMethodCallException из потрохов фреймворка —
        // по нему никто не догадается, что дописать.
        $this->get(route('admin.login'))
            ->assertStatus(503)
            ->assertSee('Модель пользователя не подготовлена')
            ->assertSee('php artisan nexor:user-model')
            ->assertSee('roles');

        $this->getJson('/admin/api/bootstrap')
            ->assertStatus(503)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'nexor:user-model'));
    }
}

/** Модель приложения, к которой ещё не приложили руки. */
class UnpreparedUser extends Authenticatable {}
