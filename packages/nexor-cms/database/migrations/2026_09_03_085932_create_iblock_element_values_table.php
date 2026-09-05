<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_element_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained('iblock_elements')->cascadeOnDelete();
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
            $table->timestamps();

            $table->index(['element_id', 'property_id']);
            $table->index(['property_id', 'value_string']);
            $table->index(['property_id', 'value_int']);
            $table->index(['property_id', 'value_decimal']);
            $table->index(['property_id', 'value_enum_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_element_values');
    }
};
