<?php

namespace Tests\Feature;

use App\Models\User;
use Nexor\Cms\Support\UserModelSetup;
use Tests\TestCase;

/**
 * `nexor:install` сам готовит модель пользователя приложения: на свежем
 * Laravel в ней нет ни контракта, ни трейтов, и установка падала на первой
 * же учётной записи.
 */
class UserModelSetupTest extends TestCase
{
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
}
PHP;
    }

    public function test_the_stock_model_gets_the_contract_traits_and_columns(): void
    {
        $patched = UserModelSetup::apply($this->stockModel());

        $this->assertStringContainsString('use Nexor\Cms\Contracts\NexorUser;', $patched);
        $this->assertStringContainsString('use Nexor\Cms\Models\Concerns\HasRoles;', $patched);
        $this->assertStringContainsString('use Nexor\Cms\Models\Concerns\HasUserFields;', $patched);
        $this->assertStringContainsString('class User extends Authenticatable implements NexorUser', $patched);
        $this->assertStringContainsString('use HasFactory, HasRoles, HasUserFields, Notifiable;', $patched);
        $this->assertStringContainsString(
            "#[Fillable(['name', 'email', 'password', 'login', 'phone', 'avatar', 'is_active', 'is_super_admin'])]",
            $patched,
        );
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

    public function test_an_unusual_model_is_left_alone(): void
    {
        $this->assertNull(UserModelSetup::apply("<?php\n\n// пусто\n"));
    }

    public function test_the_model_of_this_application_is_already_ready(): void
    {
        $this->assertSame([], UserModelSetup::missing(User::class));
    }
}
