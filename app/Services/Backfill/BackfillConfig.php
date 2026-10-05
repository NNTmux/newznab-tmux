<?php

declare(strict_types=1);

namespace App\Services\Backfill;

use App\Services\Configuration\ConfigurationProvider;

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
        $configuration = app(ConfigurationProvider::class)->ingestion();

        return new self(
            compressedHeaders: (bool) config('nntmux_nntp.compressed_headers'),
            echoCli: (bool) config('nntmux.echocli'),
            safeBackFillDate: $configuration->safeBackfillDate,
            safePartRepair: $configuration->safePartRepair ? 'update' : 'backfill',
            disableBackfillGroup: $configuration->disableBackfillGroup,
        );
    }
}
