<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Вкладки формы «Telegram» и «Защита».
 *
 * Настройки лежат JSON-ом: их набор ещё будет расти. Секреты внутри
 * (токен бота, серверные ключи капчи) зашифрованы ключом приложения.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_forms', function (Blueprint $table) {
            $table->text('telegram')->nullable()->after('store_submissions');
            $table->text('protection')->nullable()->after('telegram');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_forms', function (Blueprint $table) {
            $table->dropColumn(['telegram', 'protection']);
        });
    }
};
