<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iblock_id')->constrained('iblocks')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('hint')->nullable();
            $table->string('type', 50);
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_shown_in_list')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->text('default_value')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['iblock_id', 'code']);
            $table->index(['iblock_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_properties');
    }
};
