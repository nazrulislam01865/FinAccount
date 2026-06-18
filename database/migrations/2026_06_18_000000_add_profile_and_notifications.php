<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('profile_photo_path')->nullable()->after('email_verified_at');
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_notification_deliveries')) {
            Schema::create('accounting_notification_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('dedupe_key', 191);
                $table->uuid('notification_id')->nullable();
                $table->timestamp('delivered_at');
                $table->timestamps();

                $table->unique(['user_id', 'dedupe_key'], 'accounting_notification_user_dedupe_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_notification_deliveries');
        Schema::dropIfExists('notifications');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'profile_photo_path')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('profile_photo_path');
            });
        }
    }
};
