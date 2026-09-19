<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Динамический пункт по умолчанию растворяется в разделах; с этим
            // флагом он рисуется сам, а разделы становятся его детьми.
            $table->boolean('with_title')->default(false)->after('with_elements');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('with_title');
        });
    }
};
