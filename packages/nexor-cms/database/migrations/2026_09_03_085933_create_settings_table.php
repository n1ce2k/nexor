<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('group', 50)->default('general');
            $table->string('name')->nullable();
            $table->string('hint')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->index(['group', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
