<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblocks', function (Blueprint $table): void {
            // Канонический адрес элемента: с путём разделов или сразу под инфоблоком.
            $table->string('element_url', 20)->default('nested')->after('detail_url');
        });
    }

    public function down(): void
    {
        Schema::table('iblocks', function (Blueprint $table): void {
            $table->dropColumn('element_url');
        });
    }
};
