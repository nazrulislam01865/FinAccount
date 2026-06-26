<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('landing_page_settings')) {
            return;
        }

        DB::table('landing_page_settings')
            ->where('key', 'homepage')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $content = json_decode((string) $row->value, true);

                    if (! is_array($content) || ! isset($content['packages']) || ! is_array($content['packages'])) {
                        continue;
                    }

                    $changed = false;

                    foreach ($content['packages'] as &$package) {
                        if (! is_array($package)) {
                            continue;
                        }

                        if (array_key_exists('popular', $package) || array_key_exists('popular_label', $package)) {
                            unset($package['popular'], $package['popular_label']);
                            $changed = true;
                        }
                    }
                    unset($package);

                    if (! $changed) {
                        continue;
                    }

                    DB::table('landing_page_settings')
                        ->where('id', $row->id)
                        ->update([
                            'value' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // The removed recommendation fields are obsolete and intentionally not restored.
    }
};
