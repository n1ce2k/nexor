<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_bindings', function (Blueprint $table) {
            $table->id();
            // Отпечаток установки: по нему видно, что базу перенесли на другой сайт.
            $table->uuid('install_id')->unique();
            $table->string('host');
            $table->unsignedBigInteger('key_serial')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_bindings');
    }
};
