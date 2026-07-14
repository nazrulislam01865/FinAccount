<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feed_business_areas')) {
            Schema::create('feed_business_areas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('code', 60);
                $table->string('name');
                $table->string('unit_label', 80)->default('Unit');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'feed_business_area_company_code_unique');
                $table->index(['company_id', 'is_active'], 'feed_business_area_company_active_idx');
                $table->foreign('company_id', 'feed_business_area_company_fk')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            });
        }

        $this->seedDefaultAreas();
        $this->backfillAreasFromExistingUnits();
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_business_areas');
    }

    private function seedDefaultAreas(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $defaults = [
            ['code' => 'cattle', 'name' => 'Cattle', 'unit_label' => 'Shed'],
            ['code' => 'fish', 'name' => 'Fish', 'unit_label' => 'Pond'],
            ['code' => 'vegetables', 'name' => 'Vegetables', 'unit_label' => 'Vegetable / Crop'],
        ];

        DB::table('companies')->select('id')->orderBy('id')->chunkById(100, function ($companies) use ($defaults): void {
            $now = now();

            foreach ($companies as $company) {
                foreach ($defaults as $default) {
                    DB::table('feed_business_areas')->updateOrInsert(
                        ['company_id' => $company->id, 'code' => $default['code']],
                        $default + [
                            'company_id' => $company->id,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        });
    }

    private function backfillAreasFromExistingUnits(): void
    {
        if (! Schema::hasTable('feed_business_tracking_units')) {
            return;
        }

        $now = now();

        DB::table('feed_business_tracking_units')
            ->select('company_id', 'business_area')
            ->whereNotNull('business_area')
            ->distinct()
            ->orderBy('company_id')
            ->chunk(200, function ($rows) use ($now): void {
                foreach ($rows as $row) {
                    $code = (string) $row->business_area;
                    $name = Str::headline(str_replace(['_', '-'], ' ', $code));
                    $unitLabel = match ($code) {
                        'cattle' => 'Shed',
                        'fish' => 'Pond',
                        'vegetables' => 'Vegetable / Crop',
                        default => 'Unit',
                    };

                    DB::table('feed_business_areas')->updateOrInsert(
                        ['company_id' => $row->company_id, 'code' => $code],
                        [
                            'company_id' => $row->company_id,
                            'code' => $code,
                            'name' => $name,
                            'unit_label' => $unitLabel,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }
};
