<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            if (! Schema::hasColumn('parties', 'credit_limit')) {
                $table->decimal('credit_limit', 18, 2)->nullable()->after('address');
            }
            if (! Schema::hasColumn('parties', 'payment_terms')) {
                $table->string('payment_terms', 100)->nullable()->after('credit_limit');
            }
            if (! Schema::hasColumn('parties', 'department')) {
                $table->string('department', 100)->nullable()->after('payment_terms');
            }
            if (! Schema::hasColumn('parties', 'designation')) {
                $table->string('designation', 100)->nullable()->after('department');
            }
            if (! Schema::hasColumn('parties', 'salary_amount')) {
                $table->decimal('salary_amount', 18, 2)->nullable()->after('designation');
            }
            if (! Schema::hasColumn('parties', 'ownership_percentage')) {
                $table->decimal('ownership_percentage', 5, 2)->nullable()->after('salary_amount');
            }
            if (! Schema::hasColumn('parties', 'contact_info')) {
                $table->string('contact_info', 255)->nullable()->after('ownership_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            foreach (['credit_limit', 'payment_terms', 'department', 'designation', 'salary_amount', 'ownership_percentage', 'contact_info'] as $column) {
                if (Schema::hasColumn('parties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
