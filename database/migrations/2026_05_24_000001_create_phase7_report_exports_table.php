<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_exports')) {
            Schema::create('report_exports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('report_name', 100);
                $table->json('filters_json')->nullable();
                $table->string('status', 30)->default('Pending');
                $table->string('file_path')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'report_name', 'status'], 'report_exports_company_report_status_idx');
                $table->index(['user_id', 'status'], 'report_exports_user_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
