<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('email');
            $table->string('phone', 50)->nullable()->after('avatar');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->boolean('is_super_admin')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('is_super_admin');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'avatar', 'phone', 'is_active', 'is_super_admin', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
