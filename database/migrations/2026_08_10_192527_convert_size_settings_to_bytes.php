<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Settings that were stored in gigabytes and their byte multiplier.
     *
     * @var array<string, int>
     */
    private const array GB_SETTINGS = [
        'maxsizetopostprocess' => 1073741824,
        'maxsizetoprocessnfo' => 1073741824,
    ];

    /**
     * Settings that were stored in megabytes and their byte multiplier.
     *
     * @var array<string, int>
     */
    private const array MB_SETTINGS = [
        'minsizetopostprocess' => 1048576,
        'minsizetoprocessnfo' => 1048576,
    ];

    /**
     * Convert legacy MB/GB size setting values to bytes.
     *
     * minsizetoformrelease / maxsizetoformrelease were always stored in bytes
     * and are intentionally untouched. On a fresh install the settings table
     * is still empty when migrations run, so this is a no-op and the seeder
     * inserts byte defaults directly.
     */
    public function up(): void
    {
        foreach (self::GB_SETTINGS + self::MB_SETTINGS as $name => $multiplier) {
            $value = DB::table('settings')->where('name', $name)->value('value');

            if (! is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            DB::table('settings')
                ->where('name', $name)
                ->update(['value' => (string) ((int) round((float) $value * $multiplier))]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::GB_SETTINGS + self::MB_SETTINGS as $name => $multiplier) {
            $value = DB::table('settings')->where('name', $name)->value('value');

            if (! is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            DB::table('settings')
                ->where('name', $name)
                ->update(['value' => (string) (int) ((float) $value / $multiplier)]);
        }
    }
};
