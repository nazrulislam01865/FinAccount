<?php

use App\Support\AccountingRbac;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug']);
        });

        Schema::create('accounting_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('module')->index();
            $table->string('action', 30)->default('View');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('route_name')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('accounting_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('accounting_permissions')->cascadeOnDelete();
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('accounting_role_id')
                ->nullable()
                ->after('role')
                ->constrained('accounting_roles')
                ->nullOnDelete();
            $table->string('account_status', 20)->default('active')->after('accounting_role_id')->index();
        });

        Schema::create('accounting_user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('accounting_permissions')->cascadeOnDelete();
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'permission_id']);
        });

        AccountingRbac::syncAllCompanies(true);
        AccountingRbac::assignExistingUsers();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_user_permissions');

        Schema::table('users', function (Blueprint $table): void {
            try {
                $table->dropConstrainedForeignId('accounting_role_id');
            } catch (\Throwable) {
                $table->dropColumn('accounting_role_id');
            }
            $table->dropIndex(['account_status']);
            $table->dropColumn('account_status');
        });

        Schema::dropIfExists('accounting_role_permissions');
        Schema::dropIfExists('accounting_permissions');
        Schema::dropIfExists('accounting_roles');
    }
};
