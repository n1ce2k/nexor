<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nexor\Shop\Enums\DeliveryProvider;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_delivery_methods', function (Blueprint $table): void {
            // Кто считает цену: настройки способа или служба доставки.
            $table->string('provider', 20)->default(DeliveryProvider::None->value)->after('description');

            // Настройки службы: ключи, отправитель, тарифы, габариты, правила цены.
            // Секреты внутри зашифрованы отдельно — см. Support\Delivery\Deliveries.
            $table->json('settings')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('shop_delivery_methods', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'settings']);
        });
    }
};
