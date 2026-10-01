<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Настройки модуля Cookie писались без группы, и база ставила им «general»
     * — на экране настроек сайта они висели пустыми полями. Каждой такой
     * строке достаётся группа по началу ключа: cookies.enabled → cookies.
     */
    public function up(): void
    {
        DB::table('settings')
            ->whereNull('name')
            ->where('group', 'general')
            ->where('key', 'like', 'cookies.%')
            ->update(['group' => 'cookies']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereNull('name')
            ->where('group', 'cookies')
            ->update(['group' => 'general']);
    }
};
