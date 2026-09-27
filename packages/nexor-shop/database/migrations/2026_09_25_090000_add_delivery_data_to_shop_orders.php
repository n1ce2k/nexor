<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            // Что покупатель выбрал у службы доставки: город, тариф, пункт выдачи
            // или адрес и обещанный срок. Снимок на момент заказа — как и цены.
            $table->json('delivery_data')->nullable()->after('delivery_name');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn('delivery_data');
        });
    }
};
