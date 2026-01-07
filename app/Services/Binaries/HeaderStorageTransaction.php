<?php

declare(strict_types=1);

namespace App\Services\Binaries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles header storage transactions and rollback cleanup.
 */
final class HeaderStorageTransaction
{
    private CollectionHandler $collectionHandler;

    private BinaryHandler $binaryHandler;

    private PartHandler $partHandler;

    private string $batchNoise;

    private bool $hadErrors = false;

    public function __construct(
        CollectionHandler $collectionHandler,
        BinaryHandler $binaryHandler,
        PartHandler $partHandler
    ) {
        $this->collectionHandler = $collectionHandler;
        $this->binaryHandler = $binaryHandler;
        $this->partHandler = $partHandler;
        $this->batchNoise = bin2hex(random_bytes(8));
    }

    /**
     * Get the batch noise marker for this transaction.
     */
    public function getBatchNoise(): string
    {
        return $this->batchNoise;
    }

    /**
     * Start a new database transaction.
     */
    public function begin(): void
    {
        DB::beginTransaction();
        $this->hadErrors = false;
    }

    /**
     * Mark that an error occurred.
     */
    public function markError(): void
    {
        $this->hadErrors = true;
    }

    /**
     * Check if errors occurred.
     */
    public function hasErrors(): bool
    {
        return $this->hadErrors;
    }

    /**
     * Commit the transaction if no errors, rollback otherwise.
     */
    public function finish(): bool
    {
        if ($this->hadErrors) {
            $this->rollbackAndCleanup();

            return false;
        }

        try {
            DB::commit();

            return true;
        } catch (\Throwable $e) {
            $this->rollbackAndCleanup();

            if (config('app.debug') === true) {
                Log::error('HeaderStorageTransaction commit failed: '.$e->getMessage());
            }

            return false;
        }
    }

    /**
     * Perform rollback and cleanup any orphaned data.
     */
    private function rollbackAndCleanup(): void
    {
        try {
            DB::rollBack();
        } catch (\Throwable $e) {
            // Already rolled back
        }

        $this->cleanup();
    }

    /**
     * Cleanup rows that may have been inserted before rollback.
     */
    private function cleanup(): void
    {
        try {
            $this->cleanupParts();
            $this->cleanupBinaries();
            $this->cleanupCollections();

            // Final guard for sqlite
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DELETE FROM parts');
                DB::statement('DELETE FROM binaries');
                DB::statement('DELETE FROM collections');
            }
        } catch (\Throwable $e) {
            if (config('app.debug') === true) {
                Log::warning('Post-rollback cleanup failed: '.$e->getMessage());
            }
        }
    }

    private function cleanupParts(): void
    {
        $numbers = $this->partHandler->getInsertedNumbers();
        if (! empty($numbers)) {
            $placeholders = implode(',', array_fill(0, \count($numbers), '?'));
            DB::statement("DELETE FROM parts WHERE number IN ({$placeholders})", $numbers);
        }
    }

    private function cleanupBinaries(): void
    {
        $ids = $this->binaryHandler->getInsertedIds();
        if (! empty($ids)) {
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));
            DB::statement("DELETE FROM binaries WHERE id IN ({$placeholders})", $ids);
        }
    }

    private function cleanupCollections(): void
    {
        $insertedIds = $this->collectionHandler->getInsertedIds();
        $allIds = $this->collectionHandler->getAllIds();
        $hashes = $this->collectionHandler->getBatchHashes();

        $ids = ! empty($insertedIds) ? $insertedIds : $allIds;

        if (! empty($ids)) {
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));

            // Remove parts and binaries referencing these collections, then collections
            DB::statement(
                "DELETE FROM parts WHERE binaries_id IN (SELECT id FROM binaries WHERE collections_id IN ({$placeholders}))",
                $ids
            );
            DB::statement("DELETE FROM binaries WHERE collections_id IN ({$placeholders})", $ids);
            DB::statement("DELETE FROM collections WHERE id IN ({$placeholders})", $ids);
        } elseif (! empty($hashes)) {
            $placeholders = implode(',', array_fill(0, \count($hashes), '?'));

            DB::statement(
                "DELETE FROM parts WHERE binaries_id IN (SELECT id FROM binaries WHERE collections_id IN (SELECT id FROM collections WHERE collectionhash IN ({$placeholders})))",
                $hashes
            );
            DB::statement(
                "DELETE FROM binaries WHERE collections_id IN (SELECT id FROM collections WHERE collectionhash IN ({$placeholders}))",
                $hashes
            );
            DB::statement("DELETE FROM collections WHERE collectionhash IN ({$placeholders})", $hashes);
        } else {
            // Fallback by noise marker
            DB::statement(
                'DELETE FROM parts WHERE binaries_id IN (SELECT b.id FROM binaries b WHERE b.collections_id IN (SELECT c.id FROM collections c WHERE c.noise = ?))',
                [$this->batchNoise]
            );
            DB::statement(
                'DELETE FROM binaries WHERE collections_id IN (SELECT id FROM collections WHERE noise = ?)',
                [$this->batchNoise]
            );
            DB::statement('DELETE FROM collections WHERE noise = ?', [$this->batchNoise]);
        }
    }
}
