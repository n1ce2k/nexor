<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Database\Seeders\IblockSeeder;
use Nexor\Cms\Database\Seeders\MailTemplateSeeder;
use Nexor\Cms\Database\Seeders\RoleSeeder;
use Nexor\Cms\Database\Seeders\SettingSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles, settings and infoblock types ship with the CMS package.
        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            IblockSeeder::class,
            MailTemplateSeeder::class,
        ]);

        // Application-specific demo content.
        $this->call([
            UserSeeder::class,
            PageSeeder::class,
        ]);
    }
}
