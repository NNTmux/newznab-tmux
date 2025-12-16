<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Contracts;

use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for source-specific name fixers.
 *
 * Each implementation handles a specific source for name fixing
 * (NFO, Files, CRC32, SRR, PAR2, Media, Hash).
 */
interface NameSourceFixerInterface
{
    /**
     * Get the source type identifier.
     */
    public function getSourceType(): string;

    /**
     * Get the display name for this source.
     */
    public function getDisplayName(): string;

    /**
     * Build the query for fetching releases to process.
     *
     * @param int $cats Category filter (2=misc/hashed, 3=predb)
     * @param bool $preId Whether processing for PreDB
     * @return string SQL query string
     */
    public function buildQuery(int $cats, bool &$preId): string;

    /**
     * Process a single release.
     *
     * @param object $release The release to process
     * @param bool $echo Whether to actually update the database
     * @param bool $nameStatus Whether to update status columns
     * @param bool $show Whether to show output
     * @param bool $preId Whether processing for PreDB
     * @return bool True if a name was found
     */
    public function processRelease(object $release, bool $echo, bool $nameStatus, bool $show, bool $preId): bool;

    /**
     * Get the processing flag column name for this source.
     */
    public function getProcessingColumn(): string;

    /**
     * Get the "done" value for the processing flag.
     */
    public function getProcessingDoneValue(): int;
}

