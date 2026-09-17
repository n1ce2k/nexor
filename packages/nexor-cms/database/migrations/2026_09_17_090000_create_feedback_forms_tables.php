<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Формы обратной связи как сущности, соглашения и записи форм — как веб-формы
 * и пользовательские соглашения в Битриксе.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // Текст у галочки и часть его, которая становится ссылкой на полный текст.
            $table->string('label', 500);
            $table->string('link_text')->nullable();
            $table->longText('text')->nullable();
            $table->string('text_type', 10)->default('html');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('feedback_forms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('button_text')->default('Отправить');
            $table->string('success_text', 1000)->default('Спасибо! Мы получили ваше сообщение.');
            $table->foreignId('mail_template_id')->nullable()->constrained('mail_templates')->nullOnDelete();
            // Кому слать, если не указано в почтовом шаблоне.
            $table->string('to')->nullable();
            $table->foreignId('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->boolean('agreement_popup')->default(true);
            $table->boolean('ajax')->default(true);
            $table->boolean('store_submissions')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();
        });

        Schema::create('feedback_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('feedback_forms')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('label');
            $table->string('type', 20)->default('string');
            $table->boolean('is_required')->default(false);
            $table->string('placeholder')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort')->default(500);
            $table->timestamps();

            $table->unique(['form_id', 'code']);
        });

        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('feedback_forms')->cascadeOnDelete();
            // Снимок: код поля → { label, type, value }. Правка формы старые записи не меняет.
            $table->json('data');
            // Кто с чем согласился и когда — для учёта согласий на обработку данных.
            $table->foreignId('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->timestamp('agreed_at')->nullable();
            $table->string('page_url', 1000)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_submissions');
        Schema::dropIfExists('feedback_form_fields');
        Schema::dropIfExists('feedback_forms');
        Schema::dropIfExists('agreements');
    }
};
