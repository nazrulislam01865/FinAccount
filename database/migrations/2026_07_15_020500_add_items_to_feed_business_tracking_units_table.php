<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('feed_business_tracking_units', 'items')) {
            Schema::table('feed_business_tracking_units', function (Blueprint $table): void {
                $table->json('items')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('feed_business_tracking_units', 'items')) {
            Schema::table('feed_business_tracking_units', function (Blueprint $table): void {
                $table->dropColumn('items');
            });
        }
    }
};
