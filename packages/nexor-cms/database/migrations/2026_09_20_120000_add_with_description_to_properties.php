<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblock_properties', function (Blueprint $table) {
            // Описание принадлежит значению, а не свойству: у свойства только
            // выключатель этого поля — как WITH_DESCRIPTION в Битриксе.
            $table->boolean('with_description')->default(false)->after('default_value');
            $table->dropColumn('description');
        });

        Schema::table('iblock_element_values', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('value_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('iblock_properties', function (Blueprint $table) {
            $table->text('description')->nullable()->after('default_value');
            $table->dropColumn('with_description');
        });

        Schema::table('iblock_element_values', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
