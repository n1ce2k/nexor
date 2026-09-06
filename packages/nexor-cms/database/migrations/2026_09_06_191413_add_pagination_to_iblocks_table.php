<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblocks', function (Blueprint $table) {
            // How the public listing of this infoblock is paged.
            $table->string('pagination_template', 50)->default('pagination')->after('page_path');
            $table->unsignedInteger('per_page')->default(20)->after('pagination_template');
            $table->boolean('has_load_more')->default(false)->after('per_page');
            $table->unsignedInteger('load_more_size')->default(12)->after('has_load_more');
        });
    }

    public function down(): void
    {
        Schema::table('iblocks', function (Blueprint $table) {
            $table->dropColumn(['pagination_template', 'per_page', 'has_load_more', 'load_more_size']);
        });
    }
};
