<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Состояние модулей: сами модули — composer-пакеты, здесь только
        // включён ли модуль на этом сайте и его настройки.
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
