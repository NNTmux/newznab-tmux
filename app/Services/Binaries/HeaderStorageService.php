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

    /** @var array<int, int|string> Article numbers that failed to insert */
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
     * @param  array<int, array<string, mixed>>  $headers  Parsed headers with 'matches' already populated
     * @param  array<string, mixed>  $groupMySQL  Group info from database
     * @param  bool  $addToPartRepair  Whether to track failed inserts
     * @return array<int, int|string> Article numbers that failed to insert
     */
    public function store(array $headers, array $groupMySQL, bool $addToPartRepair = true): array
    {
        if (empty($headers)) {
            return [];
        }

        $this->failedInserts = [];

        // Use the dedicated header chunk size, NOT partsChunkSize. The latter
        // controls single-row part flushes and is normally much larger; using
        // it here forces every collection/binary bulk INSERT and OR-clause
        // SELECT to scale to thousands of rows per chunk, which exhausts PHP
        // and MySQL memory.
        $chunkSize = max(1, $this->config->headerChunkSize);

        // Walk the array with offset slicing instead of array_chunk() so we
        // don't materialize every chunk simultaneously in memory.
        $total = \count($headers);
        $headers = array_values($headers);
        for ($offset = 0; $offset < $total; $offset += $chunkSize) {
            $chunk = \array_slice($headers, $offset, $chunkSize);
            $this->storeChunk($chunk, $groupMySQL, $addToPartRepair);
            unset($chunk);
        }

        return array_values(array_unique($this->failedInserts));
    }

    /**
     * Store one bounded header chunk inside its own transaction.
     *
     * @param  array<int, array<string, mixed>>  $headers
     * @param  array<string, mixed>  $groupMySQL
     */
    private function storeChunk(array $headers, array $groupMySQL, bool $addToPartRepair): void
    {
        $this->collectionHandler->reset();
        $this->binaryHandler->reset();
        $this->partHandler->reset();
        $this->partHandler->setAddToPartRepair($addToPartRepair);

        $chunkNumbers = [];
        foreach ($headers as $header) {
            if (isset($header['Number']) && (\is_int($header['Number']) || \is_string($header['Number']))) {
                $chunkNumbers[] = $header['Number'];
            }
        }

        // Create transaction
        $transaction = new HeaderStorageTransaction(
            $this->collectionHandler,
            $this->binaryHandler
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
        if ($addToPartRepair && isset($header['Number']) && (\is_int($header['Number']) || \is_string($header['Number']))) {
            $this->failedInserts[] = $header['Number'];
        }
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
