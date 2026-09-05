<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iblock_type_id')->constrained('iblock_types')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('picture')->nullable();
            $table->text('description')->nullable();
            $table->string('list_url')->nullable();
            $table->string('section_url')->nullable();
            $table->string('detail_url')->nullable();
            $table->boolean('has_sections')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['iblock_type_id', 'is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblocks');
    }
};
