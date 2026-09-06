<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Settings shipped by the CMS may be edited but never deleted.
            $table->boolean('is_system')->default(false)->after('type');
            $table->boolean('is_encrypted')->default(false)->after('is_system');
        });

        // Everything that exists at this point came from the package seeders.
        DB::table('settings')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_encrypted']);
        });
    }
};
