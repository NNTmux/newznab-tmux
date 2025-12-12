<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use App\Models\Settings;

/**
 * Configuration DTO for Binaries processing.
 * Encapsulates all settings in an immutable object for easier testing and injection.
 */
final readonly class BinariesConfig
{
    public function __construct(
        public int $messageBuffer = 20000,
        public bool $compressedHeaders = true,
        public bool $partRepair = true,
        public bool $newGroupScanByDays = false,
        public int $newGroupMessagesToScan = 50000,
        public int $newGroupDaysToScan = 3,
        public int $partRepairLimit = 15000,
        public int $partRepairMaxTries = 3,
        public int $partsChunkSize = 5000,
        public int $binariesUpdateChunkSize = 1000,
        public bool $echoCli = false,
    ) {}

    /**
     * Create configuration from application settings.
     */
    public static function fromSettings(): self
    {
        return new self(
            messageBuffer: self::getSettingInt('maxmssgs', 20000),
            compressedHeaders: (bool) config('nntmux_nntp.compressed_headers'),
            partRepair: self::getSettingInt('partrepair', 1) === 1,
            newGroupScanByDays: self::getSettingInt('newgroupscanmethod', 0) === 1,
            newGroupMessagesToScan: self::getSettingInt('newgroupmsgstoscan', 50000),
            newGroupDaysToScan: self::getSettingInt('newgroupdaystoscan', 3),
            partRepairLimit: self::getSettingInt('maxpartrepair', 15000),
            partRepairMaxTries: self::getSettingInt('partrepairmaxtries', 3),
            partsChunkSize: max(100, (int) config('nntmux.parts_chunk_size', 5000)),
            binariesUpdateChunkSize: max(100, (int) config('nntmux.binaries_update_chunk_size', 1000)),
            echoCli: (bool) config('nntmux.echocli'),
        );
    }

    private static function getSettingInt(string $key, int $default): int
    {
        $value = Settings::settingValue($key);

        return $value !== '' ? (int) $value : $default;
    }
}

