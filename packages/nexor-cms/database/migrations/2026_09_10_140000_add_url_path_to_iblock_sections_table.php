<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nexor\Cms\Models\IblockSection;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblock_sections', function (Blueprint $table): void {
            // Путь из символьных кодов: `mebel/stulya`. Рядом с `path` из id,
            // но нужен для адресов: иначе каждая ссылка на раздел тянула бы
            // предков отдельным запросом, а разделов на странице десятки.
            $table->string('url_path', 1000)->nullable()->after('path');
        });

        IblockSection::query()->orderBy('depth')->each(fn (IblockSection $section) => $section->save());
    }

    public function down(): void
    {
        Schema::table('iblock_sections', function (Blueprint $table): void {
            $table->dropColumn('url_path');
        });
    }
};
