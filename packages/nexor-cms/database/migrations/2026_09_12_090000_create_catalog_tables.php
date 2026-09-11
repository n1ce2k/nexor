<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iblocks', function (Blueprint $table): void {
            $table->boolean('is_catalog')->default(false)->after('has_page');

            // Двусторонняя связь «каталог ↔ предложения»: по ней видно и какие
            // предложения у каталога, и что сам инфоблок — чьи-то предложения.
            $table->foreignId('offers_iblock_id')->nullable()->after('is_catalog')
                ->constrained('iblocks')->nullOnDelete();
            $table->foreignId('product_iblock_id')->nullable()->after('offers_iblock_id')
                ->constrained('iblocks')->nullOnDelete();
        });

        // Торговые данные элемента — отдельной таблицей, а не свойствами:
        // по цене и остаткам сортируют и фильтруют, а в EAV это дорого.
        Schema::create('catalog_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('element_id')->unique()->constrained('iblock_elements')->cascadeOnDelete();

            // У торгового предложения — товар, к которому оно относится.
            $table->foreignId('parent_element_id')->nullable()->constrained('iblock_elements')->cascadeOnDelete();

            $table->decimal('price', 14, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('measure', 20)->default('шт');
            $table->decimal('ratio', 10, 3)->default(1);

            // Количественный учёт и покупка при нулевом остатке.
            $table->boolean('quantity_trace')->default(false);
            $table->boolean('can_buy_zero')->default(false);

            $table->timestamps();

            $table->index('parent_element_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');

        Schema::table('iblocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_iblock_id');
            $table->dropConstrainedForeignId('offers_iblock_id');
            $table->dropColumn('is_catalog');
        });
    }
};
