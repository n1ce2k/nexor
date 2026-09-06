<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblocks', function (Blueprint $table): void {
            // What one element of this infoblock is called: «товар», «статья».
            // Empty means the panel says just «Добавить».
            $table->string('element_name', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('iblocks', function (Blueprint $table): void {
            $table->dropColumn('element_name');
        });
    }
};
