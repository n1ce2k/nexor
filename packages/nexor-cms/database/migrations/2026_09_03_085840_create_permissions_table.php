<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('group')->default('general');
            $table->string('group_label')->nullable();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->index(['group', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
