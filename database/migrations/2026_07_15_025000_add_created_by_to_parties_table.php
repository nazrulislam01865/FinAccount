<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parties') || Schema::hasColumn('parties', 'created_by')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->after('profile_pic')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('parties') || ! Schema::hasColumn('parties', 'created_by')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
