<?php

declare(strict_types=1);

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catch-up migration for installs that already ran
 * `2026_08_13_001652_normalize_and_optimize_releases_table` before it was
 * extended to cover these two changes:
 *
 * 1. `size` appended to `ix_releases_add_pp_claim_queue`. Every additional
 *    post-processing candidate query filters `size > min AND size < max`
 *    ({@see AdditionalCandidateQuery::applyPredicates()}), but `size` was not in
 *    the index, so each candidate that later failed the size filter still cost a
 *    primary key lookup. Appending it after the existing columns keeps the
 *    equality prefix and the `postdate DESC` ordering intact while enabling
 *    index condition pushdown.
 * 2. Both claim tokens narrowed to `CHAR(32) ascii`. They only ever hold
 *    `bin2hex(random_bytes(16))`, so `VARCHAR(64)` utf8mb4 reserved four bytes
 *    per character for hex digits.
 *
 * Both now happen inside the normalization rebuild's single `ALTER`, because the
 * claim token type change is `ALGORITHM=COPY` only and would otherwise force a
 * second full table rebuild. This migration therefore no-ops on any install that
 * ran the merged version, and `down()` is intentionally empty: the normalization
 * migration's own `down()` restores the legacy column types and index shape.
 *
 * `releases.imdbid` is deliberately left alone: it joins `movieinfo.imdbid`, and
 * changing the charset on one side only would force an implicit conversion that
 * disables index usage on that join.
 */
return new class extends Migration
{
    private const string PP_CLAIM_INDEX = 'ix_releases_add_pp_claim_queue';

    /** @var list<string> */
    private const array CLAIM_TOKEN_COLUMNS = [
        'nzb_creation_claim_token',
        'additional_pp_claim_token',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        $indexNeedsSize = ! $this->ppClaimIndexCoversSize();
        $columnsToNarrow = $this->claimTokenColumnsToNarrow();

        if (! $indexNeedsSize && $columnsToNarrow === []) {
            return;
        }

        if ($this->isMariaDbOrMySql()) {
            $specifications = [];
            if ($indexNeedsSize) {
                if ($this->indexExists(self::PP_CLAIM_INDEX)) {
                    $specifications[] = 'DROP INDEX `'.self::PP_CLAIM_INDEX.'`';
                }
                $specifications[] = 'ADD INDEX `'.self::PP_CLAIM_INDEX.'` (`passwordstatus`, `haspreview`, `nzbstatus`,'
                    .' `leftguid`, `postdate` DESC, `id`, `additional_pp_claimed_at`, `size`)';
            }
            foreach ($columnsToNarrow as $column) {
                $specifications[] = 'MODIFY `'.$column.'` CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NULL';
            }

            DB::statement('ALTER TABLE '.$this->table('releases').' '.implode(', ', $specifications));

            return;
        }

        if (! $indexNeedsSize) {
            return;
        }

        Schema::table('releases', function (Blueprint $table): void {
            if ($this->indexExists(self::PP_CLAIM_INDEX)) {
                $table->dropIndex(self::PP_CLAIM_INDEX);
            }
            $table->index(
                ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at', 'size'],
                self::PP_CLAIM_INDEX,
            );
        });
    }

    /**
     * Intentionally empty. The normalization migration owns both changes now, and
     * its `down()` restores the legacy column types and index definition. Undoing
     * them here as well would trigger a redundant full table rebuild.
     */
    public function down(): void {}

    private function ppClaimIndexCoversSize(): bool
    {
        foreach (Schema::getIndexes('releases') as $index) {
            if (($index['name'] ?? null) === self::PP_CLAIM_INDEX) {
                return in_array('size', $index['columns'] ?? [], true);
            }
        }

        return false;
    }

    /** @return list<string> */
    private function claimTokenColumnsToNarrow(): array
    {
        if (! $this->isMariaDbOrMySql()) {
            return [];
        }

        $types = [];
        foreach (Schema::getColumns('releases') as $column) {
            $types[$column['name']] = strtolower((string) ($column['type'] ?? ''));
        }

        $pending = [];
        foreach (self::CLAIM_TOKEN_COLUMNS as $column) {
            if (isset($types[$column]) && ! str_starts_with($types[$column], 'char(32)')) {
                $pending[] = $column;
            }
        }

        return $pending;
    }

    private function indexExists(string $name): bool
    {
        foreach (Schema::getIndexes('releases') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    private function isMariaDbOrMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mariadb', 'mysql'], true);
    }

    private function table(string $name): string
    {
        return '`'.DB::getTablePrefix().$name.'`';
    }
};
