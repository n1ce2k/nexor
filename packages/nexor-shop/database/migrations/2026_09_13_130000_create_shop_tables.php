<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Поля формы заказа: редактор добавляет и убирает их сам.
        Schema::create('shop_order_fields', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('type', 20)->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('shop_delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            // С какой суммы заказа доставка бесплатна; null — никогда.
            $table->decimal('free_from', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('shop_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('shop_promocodes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20)->default('percent');
            $table->decimal('value', 14, 2);
            $table->decimal('min_sum', 14, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            // На что действует: весь заказ, товары разделов или отдельные товары.
            $table->string('scope', 20)->default('all');
            $table->json('section_ids')->nullable();
            $table->json('element_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shop_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->nullable()->unique();
            $table->string('status', 20)->default('new');
            $table->string('edition', 20);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // Снимок полей формы: подписи и значения на момент заказа.
            $table->json('customer');
            $table->string('currency', 3);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('delivery_price', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignId('promocode_id')->nullable()->constrained('shop_promocodes')->nullOnDelete();
            $table->string('promocode_code', 60)->nullable();
            $table->foreignId('delivery_method_id')->nullable()->constrained('shop_delivery_methods')->nullOnDelete();
            $table->string('delivery_name')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('shop_payment_methods')->nullOnDelete();
            $table->string('payment_name')->nullable();
            $table->text('manager_comment')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        // Строки заказа хранят снимок: смена цены или удаление товара старые
        // заказы не меняет.
        Schema::create('shop_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('element_id')->nullable()->constrained('iblock_elements')->nullOnDelete();
            $table->string('name');
            $table->string('url')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('measure', 20)->default('шт');
            // Цена за единицу в валюте магазина: до скидки товара и после неё.
            $table->decimal('base_price', 14, 2);
            $table->decimal('price', 14, 2);
            // Цена в валюте товара, если её пересчитали по курсу.
            $table->decimal('original_price', 14, 2)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->boolean('is_converted')->default(false);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('sum', 14, 2);
            $table->timestamps();
        });

        $now = now();

        DB::table('shop_order_fields')->insert([
            ['code' => 'NAME', 'name' => 'ФИО', 'type' => 'text', 'is_required' => true, 'is_active' => true, 'sort' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PHONE', 'name' => 'Телефон', 'type' => 'phone', 'is_required' => true, 'is_active' => true, 'sort' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EMAIL', 'name' => 'E-mail', 'type' => 'email', 'is_required' => false, 'is_active' => true, 'sort' => 300, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'COMMENT', 'name' => 'Комментарий', 'type' => 'textarea', 'is_required' => false, 'is_active' => true, 'sort' => 400, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->mailTemplates($now);
    }

    /**
     * Письма о новом заказе — обычными почтовыми шаблонами, чтобы их можно было
     * переписать в «Почтовых шаблонах».
     */
    protected function mailTemplates($now): void
    {
        if (! Schema::hasTable('mail_templates')) {
            return;
        }

        $templates = [
            [
                'code' => 'SHOP_ORDER_ADMIN',
                'name' => 'Магазин: новый заказ (администратору)',
                'description' => 'Доступно: #NUMBER#, #TOTAL#, #CUSTOMER#, #ITEMS#, #DELIVERY#, #PAYMENT#, #URL#',
                'subject' => 'Новый заказ №#NUMBER# на #TOTAL#',
                'body' => "Новый заказ №#NUMBER#.\n\nПокупатель:\n#CUSTOMER#\n\nТовары:\n#ITEMS#\n\nДоставка: #DELIVERY#\nОплата: #PAYMENT#\n\nИтого: #TOTAL#\n\nЗаказ в панели: #URL#",
            ],
            [
                'code' => 'SHOP_ORDER_CUSTOMER',
                'name' => 'Магазин: заказ принят (покупателю)',
                'description' => 'Доступно: #NUMBER#, #TOTAL#, #CUSTOMER#, #ITEMS#, #DELIVERY#, #PAYMENT#',
                'subject' => 'Ваш заказ №#NUMBER# принят',
                'body' => "Спасибо за заказ!\n\nНомер заказа: #NUMBER#\n\nТовары:\n#ITEMS#\n\nИтого: #TOTAL#\n\nМы свяжемся с вами для подтверждения.",
            ],
        ];

        foreach ($templates as $template) {
            if (DB::table('mail_templates')->where('code', $template['code'])->exists()) {
                continue;
            }

            DB::table('mail_templates')->insert($template + [
                'body_type' => 'text',
                'is_active' => true,
                'sort' => 800,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_promocodes');
        Schema::dropIfExists('shop_payment_methods');
        Schema::dropIfExists('shop_delivery_methods');
        Schema::dropIfExists('shop_order_fields');
    }
};
