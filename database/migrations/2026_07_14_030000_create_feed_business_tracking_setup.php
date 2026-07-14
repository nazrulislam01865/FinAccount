<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration may be re-run after a failed MySQL foreign-key creation.
        // Drop the new setup tables first so the fixed short constraint names can be applied cleanly.
        Schema::dropIfExists('feed_business_tracking_default_assignments');
        Schema::dropIfExists('feed_business_tracking_settings');
        Schema::dropIfExists('feed_business_tracking_units');

        Schema::create('feed_business_tracking_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('feed_business_tracking_units')->nullOnDelete();
            $table->string('business_area', 40);
            $table->string('unit_type', 80);
            $table->string('code', 50);
            $table->string('name');
            $table->string('responsible_person')->nullable();
            $table->date('start_date')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'feed_tracking_unit_company_code_unique');
            $table->index(['company_id', 'business_area'], 'feed_tracking_unit_area_index');
            $table->index(['company_id', 'parent_id'], 'feed_tracking_unit_parent_index');
        });

        Schema::create('feed_business_tracking_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('require_farm_tracking')->default(true);
            $table->boolean('allow_mixed_businesses')->default(true);
            $table->boolean('allow_shared_allocation')->default(true);
            $table->boolean('track_production_cycle')->default(true);
            $table->timestamps();
        });

        Schema::create('feed_business_tracking_default_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_label')->nullable();
            $table->string('business_area', 40);
            $table->unsignedBigInteger('business_tracking_unit_id')->nullable();
            $table->boolean('allow_override')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'source_type', 'source_id'], 'feed_tracking_assignment_source_index');
            $table->foreign('business_tracking_unit_id', 'feed_bt_assign_unit_fk')
                ->references('id')
                ->on('feed_business_tracking_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_business_tracking_default_assignments');
        Schema::dropIfExists('feed_business_tracking_settings');
        Schema::dropIfExists('feed_business_tracking_units');
    }
};
