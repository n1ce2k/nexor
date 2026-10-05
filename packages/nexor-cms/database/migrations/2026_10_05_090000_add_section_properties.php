<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Свойства разделов.
     *
     * Описания свойств лежат в той же таблице, что и у элементов, — с пометкой,
     * кому они принадлежат: так типы, списки вариантов и поля формы общие.
     * Значения — в своей таблице той же формы, что у элементов.
     */
    public function up(): void
    {
        Schema::table('iblock_properties', function (Blueprint $table) {
            $table->string('target', 20)->default('element')->after('iblock_id');

            // Код свободен отдельно у элементов и у разделов: свойство раздела
            // может называться так же, как свойство элемента.
            $table->dropUnique(['iblock_id', 'code']);
            $table->unique(['iblock_id', 'target', 'code']);
        });

        Schema::create('iblock_section_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('iblock_sections')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('iblock_properties')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(500);
            $table->string('value_string')->nullable();
            $table->longText('value_text')->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->decimal('value_decimal', 20, 6)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->timestamp('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->foreignId('value_enum_id')->nullable()->constrained('iblock_property_enums')->cascadeOnDelete();
            $table->foreignId('value_element_id')->nullable()->constrained('iblock_elements')->cascadeOnDelete();
            $table->foreignId('value_section_id')->nullable()->constrained('iblock_sections')->cascadeOnDelete();
            $table->foreignId('value_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['section_id', 'property_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_section_values');

        // Свойства разделов уходят вместе с таблицей значений: иначе их коды
        // столкнулись бы с кодами свойств элементов в прежнем уникальном ключе.
        DB::table('iblock_properties')->where('target', 'section')->delete();

        Schema::table('iblock_properties', function (Blueprint $table) {
            $table->dropUnique(['iblock_id', 'target', 'code']);
            $table->unique(['iblock_id', 'code']);
            $table->dropColumn('target');
        });
    }
};
