<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblock_properties', function (Blueprint $table) {
            // Описание свойства: подпись длиннее подсказки, её же видно и на сайте.
            $table->text('description')->nullable()->after('default_value');
        });
    }

    public function down(): void
    {
        Schema::table('iblock_properties', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
