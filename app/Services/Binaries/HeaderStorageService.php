<?php

declare(strict_types=1);

namespace App\Services\Binaries;

/**
 * Orchestrates the header storage process.
 *
 * This service coordinates the CollectionHandler, BinaryHandler, PartHandler,
 * and HeaderStorageTransaction to store parsed headers into the database.
 */
final class HeaderStorageService
{
    private CollectionHandler $collectionHandler;

    private BinaryHandler $binaryHandler;

    private PartHandler $partHandler;

    private BinariesConfig $config;

    /** @var array<int> Article numbers that failed to insert */
    private array $failedInserts = [];

    public function __construct(
        ?CollectionHandler $collectionHandler = null,
        ?BinaryHandler $binaryHandler = null,
        ?PartHandler $partHandler = null,
        ?BinariesConfig $config = null
    ) {
        $this->config = $config ?? BinariesConfig::fromSettings();
        $this->collectionHandler = $collectionHandler ?? new CollectionHandler;
        $this->binaryHandler = $binaryHandler ?? new BinaryHandler;
        $this->partHandler = $partHandler ?? new PartHandler(
            $this->config->partsChunkSize,
            true
        );
    }

    /**
     * Store parsed headers to the database.
     *
     * @param  array<string, mixed>  $headers  Parsed headers with 'matches' already populated
     * @param  array<string, mixed>  $groupMySQL  Group info from database
     * @param  bool  $addToPartRepair  Whether to track failed inserts
     * @return array<string, mixed> Article numbers that failed to insert
     */
    public function store(array $headers, array $groupMySQL, bool $addToPartRepair = true): array
    {
        if (empty($headers)) {
            return [];
        }

        // Reset all handlers
        $this->collectionHandler->reset();
        $this->binaryHandler->reset();
        $this->partHandler->reset();
        $this->partHandler->setAddToPartRepair($addToPartRepair);
        $this->failedInserts = [];

        // Create transaction
        $transaction = new HeaderStorageTransaction(
            $this->collectionHandler,
            $this->binaryHandler,
            $this->partHandler
        );

        $transaction->begin();

        // Process each header
        foreach ($headers as $header) {
            if (! $this->processHeader($header, $groupMySQL, $transaction)) {
                if ($addToPartRepair && isset($header['Number'])) {
                    $this->failedInserts[] = $header['Number'];
                }
            }
        }

        // Flush remaining parts
        if ($this->partHandler->hasPending()) {
            if (! $this->partHandler->flush()) {
                $transaction->markError();
            }
        }

        // Flush binary aggregate updates
        if (! $transaction->hasErrors()) {
            if (! $this->binaryHandler->flushUpdates($this->config->binariesUpdateChunkSize)) {
                $transaction->markError();
            }
        }

        // Finish transaction
        if (! $transaction->finish()) {
            // All failed
            if ($addToPartRepair) {
                return array_unique(array_merge(
                    $this->failedInserts,
                    $this->partHandler->getFailedNumbers()
                ));
            }

            return [];
        }

        return array_unique(array_merge(
            $this->failedInserts,
            $this->partHandler->getFailedNumbers()
        ));
    }

    /**
     * @param  array<string, mixed>  $groupMySQL
     * @param  array<string, mixed>  $header
     */
    private function processHeader(array $header, array $groupMySQL, HeaderStorageTransaction $transaction): bool
    {
        // Get file count from subject
        $fileCount = $this->getFileCount($header['matches'][1]);
        if ($fileCount[1] === 0 && $fileCount[3] === 0) {
            $fileCount = $this->getFileCount($header['matches'][0]);
        }

        $totalFiles = (int) $fileCount[3];
        $fileNumber = (int) $fileCount[1];

        // Get or create collection
        $collectionId = $this->collectionHandler->getOrCreateCollection(
            $header,
            $groupMySQL['id'],
            $groupMySQL['name'],
            $totalFiles,
            $transaction->getBatchNoise()
        );

        if ($collectionId === null) {
            $transaction->markError();

            return false;
        }

        // Get or create binary
        $binaryId = $this->binaryHandler->getOrCreateBinary(
            $header,
            $collectionId,
            $groupMySQL['id'],
            $fileNumber
        );

        if ($binaryId === null) {
            $transaction->markError();

            return false;
        }

        // Add part
        if (! $this->partHandler->addPart($binaryId, $header)) {
            $transaction->markError();

            return false;
        }

        return true;
    }

    /**
     * @return array<int, int|string>
     */
    private function getFileCount(string $subject): array
    {
        if (! preg_match('/[[(\s](\d{1,5})(\/|[\s_]of[\s_]|-)(\d{1,5})[])[\s$:]/i', $subject, $fileCount)) {
            $fileCount[1] = $fileCount[3] = 0;
        }

        return $fileCount;
    }
}
