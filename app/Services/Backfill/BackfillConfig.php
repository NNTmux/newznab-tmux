<?php

declare(strict_types=1);

namespace App\Services\Backfill;

use App\Models\Settings;

/**
 * Configuration DTO for Backfill processing.
 * Encapsulates all settings in an immutable object for easier testing and injection.
 */
final readonly class BackfillConfig
{
    public function __construct(
        public bool $compressedHeaders = true,
        public bool $echoCli = false,
        public string $safeBackFillDate = '2012-08-14',
        public string $safePartRepair = 'backfill',
        public bool $disableBackfillGroup = false,
    ) {}

    /**
     * Create configuration from application settings.
     */
    public static function fromSettings(): self
    {
        return new self(
            compressedHeaders: (bool) config('nntmux_nntp.compressed_headers'),
            echoCli: (bool) config('nntmux.echocli'),
            safeBackFillDate: self::getSettingString('safebackfilldate', '2012-08-14'),
            safePartRepair: self::getSettingInt('safepartrepair', 0) === 1 ? 'update' : 'backfill',
            disableBackfillGroup: self::getSettingInt('disablebackfillgroup', 0) === 1,
        );
    }

    private static function getSettingString(string $key, string $default): string
    {
        $value = Settings::settingValue($key);

        return $value !== '' ? (string) $value : $default;
    }

    private static function getSettingInt(string $key, int $default): int
    {
        $value = Settings::settingValue($key);

        return $value !== '' ? (int) $value : $default;
    }
}
