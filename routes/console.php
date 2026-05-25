<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
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
