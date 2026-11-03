<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops releases indexes that another index already covers (InnoDB secondary
 * indexes implicitly end with the primary key) and narrows movieinfo_cat to the
 * only column queries use.
 *
 * Installs have drifted: several indexes exist only on older databases or under
 * legacy names. An index is dropped only when every index that covers it exists,
 * so fresh installs keep the indexes they rely on.
 */
return new class extends Migration
{
    private const string TABLE = 'releases';

    private const string MOVIEINFO_INDEX = 'ix_releases_movieinfo_id';

    /**
     * Redundant index => indexes that must all exist before it is dropped.
     *
     * @var array<string, list<string>>
     */
    private const array REDUNDANT_INDEXES = [
        'ix_releases_reqidstatus' => ['ix_releases_adddate'],
        'ix_releases_adddate_id' => ['ix_releases_adddate'],
        'ix_releases_categories_postdate_admin' => ['ix_releases_categories_postdate_password'],
        'ix_releases_bookinfoid' => ['ix_releases_book_retry'],
        'ix_releases_bookinfo_id' => ['ix_releases_book_retry'],
        'ix_releases_preid_searchname' => ['ix_releases_predb_id', 'ix_releases_searchname'],
        'ix_releases_imdbid_postdate_passwordstatus' => ['ix_releases_imdbid_password_cat_postdate'],
        'ix_releases_movieinfo_cat' => [self::MOVIEINFO_INDEX],
    ];

    /**
     * Indexes that repository migrations created, restored by down() when missing.
     * Legacy indexes that only exist on older installs are not restored.
     *
     * @var array<string, list<string>>
     */
    private const array RESTORABLE_INDEXES = [
        'ix_releases_adddate_id' => ['adddate', 'id'],
        'ix_releases_categories_postdate_admin' => ['categories_id', 'postdate'],
        'ix_releases_bookinfo_id' => ['bookinfo_id'],
        'ix_releases_movieinfo_cat' => ['movieinfo_id', 'categories_id', 'passwordstatus', 'postdate'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $addMovieinfoIndex = Schema::hasIndex(self::TABLE, 'ix_releases_movieinfo_cat')
            && ! Schema::hasIndex(self::TABLE, self::MOVIEINFO_INDEX);

        $indexesToDrop = array_keys(array_filter(
            self::REDUNDANT_INDEXES,
            fn (array $coveringIndexes, string $index): bool => Schema::hasIndex(self::TABLE, $index)
                && $this->allIndexesAvailable($coveringIndexes, $addMovieinfoIndex),
            ARRAY_FILTER_USE_BOTH,
        ));

        if (! $addMovieinfoIndex && $indexesToDrop === []) {
            return;
        }

        if ($this->isMysqlFamily()) {
            $clauses = $addMovieinfoIndex
                ? [sprintf('ADD INDEX `%s` (`movieinfo_id`)', self::MOVIEINFO_INDEX)]
                : [];

            foreach ($indexesToDrop as $index) {
                $clauses[] = sprintf('DROP INDEX `%s`', $index);
            }

            // One in-place ALTER; LOCK=NONE makes MariaDB refuse rather than block writes.
            DB::statement(sprintf(
                'ALTER TABLE `%s` %s, ALGORITHM=INPLACE, LOCK=NONE',
                $this->qualifiedTable(),
                implode(', ', $clauses),
            ));

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($addMovieinfoIndex, $indexesToDrop): void {
            if ($addMovieinfoIndex) {
                $table->index('movieinfo_id', self::MOVIEINFO_INDEX);
            }

            foreach ($indexesToDrop as $index) {
                $table->dropIndex($index);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $missing = array_filter(
            self::RESTORABLE_INDEXES,
            fn (string $index): bool => ! Schema::hasIndex(self::TABLE, $index),
            ARRAY_FILTER_USE_KEY,
        );

        if ($missing === []) {
            return;
        }

        if ($this->isMysqlFamily()) {
            $clauses = [];
            foreach ($missing as $index => $columns) {
                $clauses[] = sprintf(
                    'ADD INDEX `%s` (%s)',
                    $index,
                    implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns)),
                );
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` %s, ALGORITHM=INPLACE, LOCK=NONE',
                $this->qualifiedTable(),
                implode(', ', $clauses),
            ));

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $index => $columns) {
                $table->index($columns, $index);
            }
        });
    }

    /**
     * @param  list<string>  $indexes
     */
    private function allIndexesAvailable(array $indexes, bool $addMovieinfoIndex): bool
    {
        foreach ($indexes as $index) {
            $willBeAdded = $index === self::MOVIEINFO_INDEX && $addMovieinfoIndex;

            if (! $willBeAdded && ! Schema::hasIndex(self::TABLE, $index)) {
                return false;
            }
        }

        return true;
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mariadb', 'mysql'], true);
    }

    private function qualifiedTable(): string
    {
        return str_replace('`', '``', DB::getTablePrefix().self::TABLE);
    }
};
