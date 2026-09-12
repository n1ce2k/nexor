<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Enums\ProductType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            // Валюта цены; пересчёта между валютами нет — только подпись.
            $table->string('currency', 3)->default(Currency::RUB->value)->after('price');

            // Простой товар или товар, чья цена приходит из предложений.
            $table->string('type', 20)->default(ProductType::Simple->value)->after('parent_element_id');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_products', function (Blueprint $table): void {
            $table->dropColumn(['currency', 'type']);
        });
    }
};
