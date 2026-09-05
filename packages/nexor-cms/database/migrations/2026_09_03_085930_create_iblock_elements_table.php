<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iblock_id')->constrained('iblocks')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('iblock_sections')->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('preview_picture')->nullable();
            $table->text('preview_text')->nullable();
            $table->string('preview_text_type', 10)->default('text');
            $table->string('detail_picture')->nullable();
            $table->longText('detail_text')->nullable();
            $table->string('detail_text_type', 10)->default('html');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('meta_keywords', 500)->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['iblock_id', 'is_active', 'sort']);
            $table->index(['iblock_id', 'code']);
            $table->index(['section_id', 'is_active', 'sort']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_elements');
    }
};
