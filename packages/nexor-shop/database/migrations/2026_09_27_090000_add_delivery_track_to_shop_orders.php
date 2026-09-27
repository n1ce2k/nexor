<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            // Заявка в службе доставки: её номер у службы, трек-номер и то,
            // на чём остановилась передача. Номер приходит не сразу, поэтому
            // состояние и ошибка хранятся отдельно от самого трека.
            $table->string('delivery_state', 20)->default('none')->after('delivery_data');
            $table->string('delivery_request_id')->nullable()->after('delivery_state');
            $table->string('delivery_track', 64)->nullable()->after('delivery_request_id');
            $table->string('delivery_status')->nullable()->after('delivery_track');
            $table->text('delivery_error')->nullable()->after('delivery_status');
            $table->timestamp('delivery_synced_at')->nullable()->after('delivery_error');

            $table->index('delivery_track');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropIndex(['delivery_track']);
            $table->dropColumn([
                'delivery_state', 'delivery_request_id', 'delivery_track',
                'delivery_status', 'delivery_error', 'delivery_synced_at',
            ]);
        });
    }
};
