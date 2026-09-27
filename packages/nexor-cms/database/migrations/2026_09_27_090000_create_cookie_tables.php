<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_counters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Категория согласия, без которой код в страницу не попадёт.
            $table->string('category', 20)->default('analytics');
            // Куда вставлять: в <head> или перед </body>.
            $table->string('placement', 10)->default('head');
            $table->text('code');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->index(['is_active', 'category', 'placement']);
        });

        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            // Что именно разрешил посетитель: {technical, analytics, marketing}.
            $table->json('preferences');
            // Адрес усечён до подсети: для спора этого хватает, а хранить
            // полный адрес каждого посетителя годами — лишнее.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consents');
        Schema::dropIfExists('cookie_counters');
    }
};
