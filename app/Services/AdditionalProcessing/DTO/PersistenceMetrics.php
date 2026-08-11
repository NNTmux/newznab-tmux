<?php

declare(strict_types=1);

namespace App\Services\AdditionalProcessing\DTO;

final readonly class PersistenceMetrics
{
    public function __construct(
        public int $databaseStatements = 0,
        public float $databaseMilliseconds = 0.0,
        public int $searchSyncRequests = 0,
        public int $searchSyncExecutions = 0,
    ) {}
}
