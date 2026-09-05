<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iblock_property_enums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('iblock_properties')->cascadeOnDelete();
            $table->string('value');
            $table->string('code')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->index(['property_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iblock_property_enums');
    }
};
