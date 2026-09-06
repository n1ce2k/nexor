<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblocks', function (Blueprint $table) {
            // A page-backed infoblock owns a folder under resources/views,
            // the way a Bitrix folder is a page.
            $table->boolean('has_page')->default(false)->after('has_sections');
            $table->string('page_path')->nullable()->after('has_page');
        });
    }

    public function down(): void
    {
        Schema::table('iblocks', function (Blueprint $table) {
            $table->dropColumn(['has_page', 'page_path']);
        });
    }
};
