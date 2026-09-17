<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Логин пользователя: войти можно и по нему, и по e-mail.
 *
 * Колонка допускает NULL, чтобы миграция не падала на уже заведённых
 * пользователях; тем, кто был, логин собирается из адреса почты.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login', 100)->nullable()->unique()->after('name');
        });

        $taken = [];

        foreach (DB::table('users')->select('id', 'email')->orderBy('id')->cursor() as $user) {
            $base = Str::of((string) $user->email)->before('@')->lower()->replaceMatches('/[^a-z0-9._-]+/', '')->limit(90, '')->value();
            $base = $base === '' ? 'user' : $base;
            $login = $base;

            for ($index = 2; isset($taken[$login]); $index++) {
                $login = $base.$index;
            }

            $taken[$login] = true;

            DB::table('users')->where('id', $user->id)->update(['login' => $login]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }
};
