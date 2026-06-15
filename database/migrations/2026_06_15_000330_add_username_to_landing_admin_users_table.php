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
        if (! Schema::hasTable('landing_admin_users') || Schema::hasColumn('landing_admin_users', 'username')) {
            return;
        }

        Schema::table('landing_admin_users', function (Blueprint $table): void {
            $table->string('username', 100)->nullable()->unique()->after('name');
        });

        DB::table('landing_admin_users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->get()
            ->each(function (object $admin): void {
                $base = Str::lower(Str::before((string) $admin->email, '@'));
                $base = preg_replace('/[^a-z0-9._-]+/', '', $base) ?: 'landingadmin';
                $username = $base;
                $suffix = 1;

                while (DB::table('landing_admin_users')->where('username', $username)->exists()) {
                    $username = $base.$suffix;
                    $suffix++;
                }

                DB::table('landing_admin_users')
                    ->where('id', $admin->id)
                    ->update(['username' => $username]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('landing_admin_users') || ! Schema::hasColumn('landing_admin_users', 'username')) {
            return;
        }

        Schema::table('landing_admin_users', function (Blueprint $table): void {
            $table->dropUnique('landing_admin_users_username_unique');
            $table->dropColumn('username');
        });
    }
};
