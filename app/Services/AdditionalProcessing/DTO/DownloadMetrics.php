<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class DownloadMetrics
{
    public function __construct(
        public int $logicalRequests = 0,
        public int $networkRequests = 0,
        public int $cacheHits = 0,
        public int $bytesDownloaded = 0,
        public int $bytesReused = 0,
    ) {}
}
