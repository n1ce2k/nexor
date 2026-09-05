<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_element_section', function (Blueprint $table) {
            $table->foreignId('element_id')->constrained('iblock_elements')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('iblock_sections')->cascadeOnDelete();

            $table->primary(['element_id', 'section_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_element_section');
    }
};
