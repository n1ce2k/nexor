<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            // Как товар показывает предложения: переключателем по свойствам, у
            // каждого свой адрес, — или списком под товаром на одной странице.
            $table->boolean('offers_by_properties')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->dropColumn('offers_by_properties');
        });
    }
};
