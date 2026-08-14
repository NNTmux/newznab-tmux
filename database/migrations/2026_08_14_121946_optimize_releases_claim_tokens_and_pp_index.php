<?php

declare(strict_types=1);

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to the releases normalization rebuild.
 *
 * 1. Appends `size` to `ix_releases_add_pp_claim_queue`. Every additional
 *    post-processing candidate query filters `size > min AND size < max`
 *    ({@see AdditionalCandidateQuery::applyPredicates()}),
 *    but `size` was not in the index, so each candidate that later failed the
 *    size filter still cost a primary key lookup. Appending it after the
 *    existing columns keeps the equality prefix and the `postdate DESC`
 *    ordering intact while enabling index condition pushdown.
 * 2. Narrows both claim tokens to `CHAR(32) ascii`. They only ever hold
 *    `bin2hex(random_bytes(16))`, so `VARCHAR(64)` utf8mb4 reserved four bytes
 *    per character for hex digits.
 *
 * `releases.imdbid` is deliberately left alone: it joins `movieinfo.imdbid`,
 * and changing the charset on one side only would force an implicit conversion
 * that disables index usage on that join.
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

        if ($this->isMariaDbOrMySql()) {
            $specifications = [];
            if ($this->indexExists(self::PP_CLAIM_INDEX)) {
                $specifications[] = 'DROP INDEX `'.self::PP_CLAIM_INDEX.'`';
            }
            $specifications[] = 'ADD INDEX `'.self::PP_CLAIM_INDEX.'` (`passwordstatus`, `haspreview`, `nzbstatus`,'
                .' `leftguid`, `postdate` DESC, `id`, `additional_pp_claimed_at`, `size`)';
            foreach (self::CLAIM_TOKEN_COLUMNS as $column) {
                if (Schema::hasColumn('releases', $column)) {
                    $specifications[] = 'MODIFY `'.$column.'` CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NULL';
                }
            }

            DB::statement('ALTER TABLE '.$this->table('releases').' '.implode(', ', $specifications));

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

    public function down(): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        if ($this->isMariaDbOrMySql()) {
            $specifications = [];
            if ($this->indexExists(self::PP_CLAIM_INDEX)) {
                $specifications[] = 'DROP INDEX `'.self::PP_CLAIM_INDEX.'`';
            }
            $specifications[] = 'ADD INDEX `'.self::PP_CLAIM_INDEX.'` (`passwordstatus`, `haspreview`, `nzbstatus`,'
                .' `leftguid`, `postdate` DESC, `id`, `additional_pp_claimed_at`)';
            foreach (self::CLAIM_TOKEN_COLUMNS as $column) {
                if (Schema::hasColumn('releases', $column)) {
                    $specifications[] = 'MODIFY `'.$column.'` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL';
                }
            }

            DB::statement('ALTER TABLE '.$this->table('releases').' '.implode(', ', $specifications));

            return;
        }

        Schema::table('releases', function (Blueprint $table): void {
            if ($this->indexExists(self::PP_CLAIM_INDEX)) {
                $table->dropIndex(self::PP_CLAIM_INDEX);
            }
            $table->index(
                ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at'],
                self::PP_CLAIM_INDEX,
            );
        });
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
