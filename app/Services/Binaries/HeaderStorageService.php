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

        $this->failedInserts = [];

        $chunkSize = max(1, $this->config->partsChunkSize);
        foreach (array_chunk($headers, $chunkSize) as $chunk) {
            $this->storeChunk($chunk, $groupMySQL, $addToPartRepair);
        }

        return array_values(array_unique($this->failedInserts));
    }

    /**
     * Store one bounded header chunk inside its own transaction.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $groupMySQL
     */
    private function storeChunk(array $headers, array $groupMySQL, bool $addToPartRepair): void
    {
        $this->collectionHandler->reset();
        $this->binaryHandler->reset();
        $this->partHandler->reset();
        $this->partHandler->setAddToPartRepair($addToPartRepair);

        $chunkNumbers = array_values(array_filter(array_map(
            static fn (array $header): mixed => $header['Number'] ?? null,
            $headers
        )));

        // Create transaction
        $transaction = new HeaderStorageTransaction(
            $this->collectionHandler,
            $this->binaryHandler,
            $this->partHandler
        );

        $transaction->begin();

        $this->processHeaderChunk($headers, $groupMySQL, $transaction, $addToPartRepair);

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
            if ($addToPartRepair) {
                $this->failedInserts = array_merge(
                    $this->failedInserts,
                    $chunkNumbers,
                    $this->partHandler->getFailedNumbers()
                );
            }

            return;
        }

        $this->failedInserts = array_merge(
            $this->failedInserts,
            $this->partHandler->getFailedNumbers()
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $headers
     * @param  array<string, mixed>  $groupMySQL
     */
    private function processHeaderChunk(array $headers, array $groupMySQL, HeaderStorageTransaction $transaction, bool $addToPartRepair): void
    {
        $totalFilesByIndex = [];
        $fileNumbersByIndex = [];

        foreach ($headers as $index => $header) {
            [$fileNumber, $totalFiles] = $this->extractFileNumberAndTotal($header);
            $fileNumbersByIndex[$index] = $fileNumber;
            $totalFilesByIndex[$index] = $totalFiles;
        }

        $collectionIds = $this->collectionHandler->getOrCreateCollections(
            $headers,
            $groupMySQL['id'],
            $groupMySQL['name'],
            $totalFilesByIndex,
            $transaction->getBatchNoise()
        );

        $binaryRecords = [];
        foreach ($headers as $index => $header) {
            if (! isset($collectionIds[$index])) {
                $this->markHeaderFailed($header, $transaction, $addToPartRepair);

                continue;
            }

            $binaryRecords[$index] = [
                'header' => $header,
                'collection_id' => $collectionIds[$index],
                'file_number' => $fileNumbersByIndex[$index],
            ];
        }

        $binaryIds = $this->binaryHandler->getOrCreateBinaries($binaryRecords, $groupMySQL['id']);

        foreach ($binaryRecords as $index => $record) {
            $header = $record['header'];
            if (! isset($binaryIds[$index])) {
                $this->markHeaderFailed($header, $transaction, $addToPartRepair);

                continue;
            }

            if (! $this->partHandler->addPart($binaryIds[$index], $header)) {
                $this->markHeaderFailed($header, $transaction, $addToPartRepair);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array{0: int, 1: int}
     */
    private function extractFileNumberAndTotal(array $header): array
    {
        $fileCount = $this->getFileCount($header['matches'][1]);
        if ($fileCount[1] === 0 && $fileCount[3] === 0) {
            $fileCount = $this->getFileCount($header['matches'][0]);
        }

        return [(int) $fileCount[1], (int) $fileCount[3]];
    }

    /** @param  array<string, mixed>  $header */
    private function markHeaderFailed(array $header, HeaderStorageTransaction $transaction, bool $addToPartRepair): void
    {
        $transaction->markError();
        if ($addToPartRepair && isset($header['Number'])) {
            $this->failedInserts[] = $header['Number'];
        }
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
