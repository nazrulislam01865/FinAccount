<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('transaction_type', 50)->nullable();
                $table->foreignId('transaction_head_id')->nullable()->constrained('transaction_heads')->nullOnDelete();
                $table->boolean('approval_required')->default(false);
                $table->decimal('threshold_amount', 18, 2)->nullable();
                $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->unsignedInteger('approval_level')->default(1);
                $table->boolean('auto_approve_below_amount')->default(true);
                $table->string('status', 20)->default('Active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'transaction_type', 'status'], 'approval_workflows_type_status_idx');
                $table->index(['transaction_head_id', 'status'], 'approval_workflows_head_status_idx');
            });
        }

        if (! Schema::hasTable('approval_logs')) {
            Schema::create('approval_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('approval_workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
                $table->foreignId('voucher_header_id')->constrained('voucher_headers')->cascadeOnDelete();
                $table->unsignedInteger('approval_level')->default(1);
                $table->string('action', 30);
                $table->text('remarks')->nullable();
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acted_at')->nullable();
                $table->timestamps();

                $table->index(['voucher_header_id', 'action'], 'approval_logs_voucher_action_idx');
                $table->index(['company_id', 'action', 'acted_at'], 'approval_logs_company_action_idx');
            });
        }

        if (Schema::hasTable('voucher_headers')) {
            Schema::table('voucher_headers', function (Blueprint $table): void {
                if (! Schema::hasColumn('voucher_headers', 'lifecycle_state')) {
                    $table->string('lifecycle_state', 30)->nullable()->after('status')->index();
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                }

                if (! Schema::hasColumn('audit_logs', 'module')) {
                    $table->string('module', 120)->nullable()->after('auditable_id');
                }

                if (! Schema::hasColumn('audit_logs', 'action')) {
                    $table->string('action', 60)->nullable()->after('event');
                }

                if (! Schema::hasColumn('audit_logs', 'ip_address')) {
                    $table->string('ip_address', 64)->nullable()->after('user_id');
                }

                if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs')) {
            foreach (['user_agent', 'ip_address', 'action', 'module'] as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    Schema::table('audit_logs', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }

            if (Schema::hasColumn('audit_logs', 'company_id')) {
                Schema::table('audit_logs', function (Blueprint $table): void {
                    try {
                        $table->dropForeign(['company_id']);
                    } catch (Throwable) {
                        // Older installations may not have the foreign key name available.
                    }

                    $table->dropColumn('company_id');
                });
            }
        }

        if (Schema::hasTable('voucher_headers') && Schema::hasColumn('voucher_headers', 'lifecycle_state')) {
            Schema::table('voucher_headers', function (Blueprint $table): void {
                $table->dropColumn('lifecycle_state');
            });
        }

        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('approval_workflows');
    }
};
