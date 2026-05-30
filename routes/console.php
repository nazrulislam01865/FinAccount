<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment('Accounting Sprint 1 is ready.');
})->purpose('Display a short inspirational message');

Artisan::command('accounting:backup-database', function () {
    $connection = DB::connection();
    $driver = $connection->getDriverName();
    $timestamp = now()->format('Ymd_His');
    $disk = Storage::disk('local');
    $directory = 'backups/database';
    $disk->makeDirectory($directory);

    if ($driver === 'sqlite') {
        $databasePath = $connection->getDatabaseName();
        if (! is_string($databasePath) || ! File::exists($databasePath)) {
            $this->error('SQLite database file was not found.');
            return 1;
        }

        $target = storage_path("app/{$directory}/accounting_{$timestamp}.sqlite");
        File::copy($databasePath, $target);
        $this->info("SQLite database backup created: {$target}");
        return 0;
    }

    $tables = match ($driver) {
        'mysql', 'mariadb' => collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0])->values()->all(),
        default => [],
    };

    if ($tables === []) {
        $this->error("Database backup is not configured for the {$driver} driver.");
        return 1;
    }

    $target = storage_path("app/{$directory}/accounting_{$timestamp}.jsonl");
    $handle = fopen($target, 'wb');

    if ($handle === false) {
        $this->error('Could not open backup file for writing.');
        return 1;
    }

    fwrite($handle, json_encode([
        'type' => 'metadata',
        'driver' => $driver,
        'created_at' => now()->toIso8601String(),
        'app' => config('app.name'),
    ], JSON_THROW_ON_ERROR).PHP_EOL);

    foreach ($tables as $table) {
        DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use ($handle, $table) {
            foreach ($rows as $row) {
                fwrite($handle, json_encode([
                    'type' => 'row',
                    'table' => $table,
                    'data' => (array) $row,
                ], JSON_THROW_ON_ERROR).PHP_EOL);
            }
        });
    }

    fclose($handle);
    $this->info("Logical database backup created: {$target}");
    return 0;
})->purpose('Create a daily database backup for production accounting records');

Artisan::command('accounting:backup-files', function () {
    $timestamp = now()->format('Ymd_His');
    $source = storage_path('app');
    $backupDirectory = storage_path('app/backups/files');
    File::ensureDirectoryExists($backupDirectory);

    if (class_exists(ZipArchive::class)) {
        $target = "{$backupDirectory}/accounting_files_{$timestamp}.zip";
        $zip = new ZipArchive();

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Could not create file backup archive.');
            return 1;
        }

        foreach (File::allFiles($source) as $file) {
            $path = $file->getPathname();

            if (str_contains($path, DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $zip->addFile($path, str_replace($source.DIRECTORY_SEPARATOR, '', $path));
        }

        $zip->close();
        $this->info("File backup created: {$target}");
        return 0;
    }

    $target = "{$backupDirectory}/accounting_files_{$timestamp}";
    File::copyDirectory($source, $target);
    $this->info("File backup folder created: {$target}");
    return 0;
})->purpose('Create a backup of uploaded/private accounting files');

Schedule::command('accounting:backup-database')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('accounting:backup-files')->dailyAt('02:15')->withoutOverlapping();

Artisan::command('srs:phase4-check', function () {
    try {
        DB::connection()->getPdo();
    } catch (\Throwable $exception) {
        $this->error('Database connection is not ready: ' . $exception->getMessage());
        $this->line('Run migrations after configuring .env, then execute this command again.');
        return 1;
    }

    $checks = [];
    $requiredTables = [
        'journal_headers',
        'journal_lines',
        'audit_logs',
        'approval_workflows',
        'approval_logs',
        'report_exports',
    ];

    foreach ($requiredTables as $table) {
        $checks[] = ["table:{$table}", Schema::hasTable($table)];
    }

    $requiredRoles = [
        'Super Admin',
        'Company Admin',
        'Accountant',
        'Data Entry Operator',
        'Manager / Approver',
        'Auditor / Viewer',
        'Business Owner',
    ];

    foreach ($requiredRoles as $role) {
        $checks[] = ["role:{$role}", Schema::hasTable('roles') && DB::table('roles')->where('name', $role)->exists()];
    }

    $requiredPermissions = [
        'dashboard.view',
        'transactions.view',
        'transactions.create',
        'transactions.journal.create',
        'transactions.reverse',
        'approvals.view',
        'approvals.manage',
        'audit-trail.view',
        'reports.view',
        'reports.full',
        'api.view',
        'api.manage',
    ];

    foreach ($requiredPermissions as $permission) {
        $checks[] = ["permission:{$permission}", Schema::hasTable('permissions') && DB::table('permissions')->where('name', $permission)->exists()];
    }

    $routes = collect(app('router')->getRoutes())->map(fn ($route) => $route->getName())->filter()->values();
    $requiredRoutes = [
        'approvals.index',
        'audit-trail.index',
        'manual-journals.index',
        'api.srs.accounts.index',
        'api.srs.transactions.post',
        'api.srs.manual-journals.post',
        'api.srs.reports.trial-balance',
        'api.srs.reports.balance-sheet',
    ];

    foreach ($requiredRoutes as $route) {
        $checks[] = ["route:{$route}", $routes->contains($route)];
    }

    $failed = collect($checks)->filter(fn ($check) => ! $check[1]);

    foreach ($checks as [$name, $passed]) {
        $this->line(($passed ? '[OK]   ' : '[MISS] ') . $name);
    }

    if ($failed->isNotEmpty()) {
        $this->error($failed->count() . ' SRS Phase 4 checks failed. Run migrations/seeders and verify configuration.');
        return 1;
    }

    $this->info('All SRS Phase 4 structural checks passed.');
    return 0;
})->purpose('Validate Phase 4 SRS roles, routes, audit, approval, and journal/report structure');

Artisan::command('app:production-check', function () {
    $checks = [
        ['APP_ENV production', app()->environment('production'), 'Set APP_ENV=production on the droplet.'],
        ['APP_DEBUG disabled', ! config('app.debug'), 'Set APP_DEBUG=false before public use.'],
        ['APP_KEY present', filled(config('app.key')), 'Run php artisan key:generate once if APP_KEY is empty.'],
        ['Configuration cached', app()->configurationIsCached(), 'Run php artisan config:cache after deployment.'],
        ['Storage writable', is_writable(storage_path()) && is_writable(storage_path('app')), 'Fix ownership/permissions for storage.'],
        ['Session driver persistent', in_array(config('session.driver'), ['database', 'redis', 'memcached'], true), 'Use SESSION_DRIVER=database or redis.'],
        ['Session encryption enabled', (bool) config('session.encrypt'), 'Use SESSION_ENCRYPT=true.'],
        ['Cache driver persistent', in_array(config('cache.default'), ['database', 'redis', 'memcached'], true), 'Use CACHE_STORE=database or redis.'],
        ['Queue not sync', config('queue.default') !== 'sync', 'Use QUEUE_CONNECTION=database or redis and run a worker.'],
        ['Security headers enabled', (bool) config('security.headers.enabled'), 'Use SECURITY_HEADERS_ENABLED=true.'],
    ];

    $failed = 0;

    foreach ($checks as [$label, $passed, $hint]) {
        $this->line(($passed ? '[OK]   ' : '[WARN] ') . $label);

        if (! $passed) {
            $this->line('       ' . $hint);
            $failed++;
        }
    }

    if ($failed > 0) {
        $this->warn($failed . ' production checks need attention.');
        return 1;
    }

    $this->info('All production readiness checks passed.');
    return 0;
})->purpose('Check production optimization, session, cache, queue, and security settings');
