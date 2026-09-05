<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Nexor\Cms\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@nexor.local'],
            [
                'name' => 'Администратор',
                'password' => 'admin123',
                'is_active' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $superAdmin = Role::query()->where('code', Role::SUPER_ADMIN)->first();

        if ($superAdmin) {
            $admin->roles()->syncWithoutDetaching([$superAdmin->id]);
        }
    }
}
