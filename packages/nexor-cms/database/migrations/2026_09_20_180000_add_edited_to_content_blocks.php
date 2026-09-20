<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            // false — блок записан сам, из шаблона, и шаблон остаётся главным;
            // true — его правил человек, теперь показывается сохранённое.
            $table->boolean('edited')->default(true)->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('content_blocks', function (Blueprint $table) {
            $table->dropColumn('edited');
        });
    }
};
