<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iblock_id')->constrained('iblocks')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('iblock_sections')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('picture')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->timestamps();

            $table->index(['iblock_id', 'parent_id', 'sort']);
            $table->index(['iblock_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_sections');
    }
};
