<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Раскладки из блоков.
     *
     * Владелец описан парой `owner_type` + `owner_id`, а не внешним ключом на
     * элемент: следом появятся страницы-конструкторы (`owner_type = page`),
     * и таблица останется той же.
     */
    public function up(): void
    {
        Schema::create('pagebuilder_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 40);
            $table->unsignedBigInteger('owner_id');
            $table->string('surface', 20)->default('detail');
            $table->json('content');
            // Текст всех блоков одной строкой — для поиска по сайту.
            $table->longText('search_text')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'surface']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagebuilder_layouts');
    }
};
