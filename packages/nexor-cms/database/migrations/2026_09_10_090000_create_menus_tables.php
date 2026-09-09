<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            $table->string('type', 30)->default('link');
            $table->string('title')->nullable();
            $table->string('url')->nullable();

            // Ссылки на сущности: страница, раздел или инфоблок-источник.
            // Адрес считается при выводе, поэтому переименование его не ломает.
            $table->foreignId('iblock_id')->nullable()->constrained('iblocks')->nullOnDelete();
            $table->foreignId('element_id')->nullable()->constrained('iblock_elements')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('iblock_sections')->nullOnDelete();

            // Для динамического пункта: докуда разворачивать разделы.
            $table->unsignedTinyInteger('max_depth')->default(2);
            $table->boolean('with_elements')->default(false);

            $table->string('target', 20)->nullable();
            $table->string('css_class', 190)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('visibility', 20)->default('all');

            // Подсвечивать пункт, когда открыта вложенная страница.
            $table->boolean('highlight_children')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
