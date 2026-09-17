<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Свои поля пользователей — как свойства инфоблока, только у учётных записей.
 *
 * Значения лежат в типизированной EAV-таблице: у каждого типа своя колонка со
 * своим индексом, поэтому выборка по полю не зависит от числа пользователей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fields', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->string('hint')->nullable();
            $table->string('type', 20);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multiple')->default(false);
            // Показывать колонкой в списке пользователей и в фильтре.
            $table->boolean('is_shown_in_list')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('user_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('user_fields')->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(500);
            $table->string('value_string')->nullable();
            $table->longText('value_text')->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->decimal('value_decimal', 20, 6)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->timestamp('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'field_id']);
            $table->index(['field_id', 'value_string']);
            $table->index(['field_id', 'value_int']);
            $table->index(['field_id', 'value_decimal']);
            $table->index(['field_id', 'value_bool']);
            $table->index(['field_id', 'value_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_field_values');
        Schema::dropIfExists('user_fields');
    }
};
