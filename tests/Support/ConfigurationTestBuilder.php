<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ConfigurationTestBuilder
{
    public static function installDefaults(): void
    {
        if (Schema::hasTable('site_configurations')) {
            return;
        }

        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_09_30_000000_replace_settings_with_typed_domain_configuration.php');
        $migration->up();
    }

    /** @param array<string, mixed> $attributes */
    public static function update(ConfigurationDomain $domain, array $attributes): void
    {
        self::installDefaults();
        DB::table($domain->table())->where('id', 1)->update([
            ...$attributes,
            'updated_at' => now(),
        ]);
        app(ConfigurationProvider::class)->forget($domain);
    }

    /** @param array<string, mixed> $attributes */
    public static function updateRuntime(array $attributes): void
    {
        self::installDefaults();
        DB::table('processing_runtime_states')->where('id', 1)->update([
            ...$attributes,
            'updated_at' => now(),
        ]);
    }
}
