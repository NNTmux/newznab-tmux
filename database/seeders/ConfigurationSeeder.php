<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Support\LegacySettingsManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        require_once database_path('support/LegacySettingsManifest.php');

        $now = now();
        foreach (LegacySettingsManifest::DOMAINS as $table => $definitions) {
            $attributes = ['id' => 1];
            foreach ($definitions as $column => $definition) {
                $attributes[$column] = $definition['default'];
            }

            DB::table($table)->insertOrIgnore([...$attributes, 'created_at' => $now, 'updated_at' => $now]);
        }

        $runtime = ['id' => 1];
        foreach (LegacySettingsManifest::RUNTIME as $definition) {
            $runtime[$definition['column']] = $definition['default'];
        }
        DB::table('processing_runtime_states')->insertOrIgnore([...$runtime, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([4, 8, 9, 11, 15, 16, 17, 18, 19, 46, 47, 48, 49, 50, 51, 52, 53, 59, 60] as $color) {
            DB::table('tmux_color_exclusions')->insertOrIgnore([
                'tmux_configuration_id' => 1,
                'color' => $color,
            ]);
        }
    }
}
