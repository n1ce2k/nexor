<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Приём оплаты онлайн: провайдер у способа оплаты, платежи и возвраты.
 *
 * Платёж хранит внешний идентификатор и последний ответ провайдера целиком:
 * деньги — та область, где «что именно он нам ответил» важнее аккуратности
 * схемы, а разбирательства случаются спустя месяцы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_payment_methods', function (Blueprint $table): void {
            // none — способ без приёма денег (наличные, счёт).
            $table->string('provider', 20)->default('none')->after('description');
            // Секреты внутри зашифрованы ключом приложения.
            $table->text('settings')->nullable()->after('provider');
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->string('payment_status', 20)->default('unpaid')->after('payment_name');
            $table->timestamp('paid_at')->nullable()->after('payment_status');

            $table->index('payment_status');
        });

        Schema::create('shop_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 14, 2);
            $table->decimal('refunded', 14, 2)->default(0);
            $table->string('currency', 3);
            $table->text('confirmation_url')->nullable();
            $table->string('idempotence_key', 64);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            // Последний ответ провайдера целиком.
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shop_payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('shop_payments')->cascadeOnDelete();
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            // Кто вернул деньги из панели.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payment_refunds');
        Schema::dropIfExists('shop_payments');

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'paid_at']);
        });

        Schema::table('shop_payment_methods', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'settings']);
        });
    }
};
