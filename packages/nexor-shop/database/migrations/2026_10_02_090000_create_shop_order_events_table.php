<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Код статуса СДЭК: по нему видно, что посылка дошла и следить дальше незачем.
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->string('delivery_status_code', 40)->nullable()->after('delivery_status');
        });

        // Что поменялось у заказа само, пока в панели никого не было.
        Schema::create('shop_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('previous')->nullable();
            $table->string('current');
            $table->timestamp('created_at')->nullable()->index();
        });

        // До какого события человек уже видел уведомления.
        Schema::create('shop_order_event_reads', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('last_event_id')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_event_reads');
        Schema::dropIfExists('shop_order_events');

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn('delivery_status_code');
        });
    }
};
