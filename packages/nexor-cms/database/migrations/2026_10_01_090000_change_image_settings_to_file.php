<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Тип настройки «Изображение» стал «Файлом»: загружать в настройки
     * приходится не только картинки.
     */
    public function up(): void
    {
        DB::table('settings')->where('type', 'image')->update(['type' => 'file']);
    }

    public function down(): void
    {
        DB::table('settings')->where('type', 'file')->update(['type' => 'image']);
    }
};
