<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const array REMOVED_RELEASE_COLUMNS = [
        'updatetime',
        'gid',
        'source',
        'nzb_password',
        'nzb_creation_attempts',
        'nzb_creation_last_error',
        'proc_sorter',
        'audiostatus',
    ];

    /** @var list<string> */
    private const array REMOVED_COMMENT_COLUMNS = [
        'gid',
        'cid',
        'issynced',
        'shared',
        'shareid',
        'siteid',
        'sourceid',
    ];

    /** @var list<string> */
    private const array REMOVED_RELEASE_INDEXES = [
        'ix_releases_guid',
        'ix_releases_adddate_only',
        'ix_releases_videos_id',
        'ix_releases_movieinfo_id',
        'ix_releases_imdbid',
        'ix_releases_tv_search_covering',
        'ix_releases_passwordstatus',
        'ix_releases_haspreview_passwordstatus',
        'ix_releases_postdate_searchname',
        'ix_releases_predb_id_searchname',
        'ix_releases_size_cat',
        'ix_releases_add_pp_claim_queue',
        'ix_releases_nzb_creation_queue',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        $this->assertReleaseIdentifiersAreSafe();
        $this->assertEnoughFreeSpaceForRebuild();
        $this->createSparseTables();
        $this->backfillSparseTables();
        $this->recountVisibleComments();

        if ($this->isMariaDbOrMySql()) {
            $this->rebuildReleasesForMariaDb();
        } else {
            $this->normalizeReleasesForPortableDatabase();
        }

        $this->addSparseForeignKeys();

        if (Schema::hasTable('release_comments')) {
            Schema::table('release_comments', function (Blueprint $table): void {
                foreach (self::REMOVED_COMMENT_COLUMNS as $column) {
                    if (Schema::hasColumn('release_comments', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('releases')) {
            return;
        }

        $this->dropSparseForeignKeys();

        if (Schema::hasTable('release_comments')) {
            Schema::table('release_comments', function (Blueprint $table): void {
                $table->string('gid', 32)->nullable();
                $table->string('cid', 32)->nullable();
                $table->boolean('issynced')->default(false);
                $table->boolean('shared')->default(false);
                $table->string('shareid', 40)->default('');
                $table->string('siteid', 40)->default('');
                $table->unsignedBigInteger('sourceid')->nullable();
            });
        }

        if ($this->isMariaDbOrMySql()) {
            $this->restoreReleasesForMariaDb();
        } else {
            $this->restoreReleasesForPortableDatabase();
        }

        $this->restoreSparseDataToReleases();
        Schema::dropIfExists('release_nzb_creation_failures');
        Schema::dropIfExists('release_nzb_passwords');
    }

    private function createSparseTables(): void
    {
        if (! Schema::hasTable('release_nzb_passwords')) {
            Schema::create('release_nzb_passwords', function (Blueprint $table): void {
                $table->unsignedInteger('releases_id')->primary();
                $table->string('password', 255);
                if (! $this->isMariaDbOrMySql()) {
                    $table->foreign('releases_id', 'FK_rnp_releases')
                        ->references('id')->on('releases')
                        ->cascadeOnUpdate()->cascadeOnDelete();
                }
            });
        }

        if (! Schema::hasTable('release_nzb_creation_failures')) {
            Schema::create('release_nzb_creation_failures', function (Blueprint $table): void {
                $table->unsignedInteger('releases_id')->primary();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();
                if (! $this->isMariaDbOrMySql()) {
                    $table->foreign('releases_id', 'FK_rncf_releases')
                        ->references('id')->on('releases')
                        ->cascadeOnUpdate()->cascadeOnDelete();
                }
            });
        }
    }

    private function addSparseForeignKeys(): void
    {
        if (! $this->isMariaDbOrMySql()) {
            return;
        }

        DB::statement(
            'ALTER TABLE '.$this->table('release_nzb_passwords')
            .' ADD CONSTRAINT `FK_rnp_releases` FOREIGN KEY (`releases_id`) REFERENCES '
            .$this->table('releases').' (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );
        DB::statement(
            'ALTER TABLE '.$this->table('release_nzb_creation_failures')
            .' ADD CONSTRAINT `FK_rncf_releases` FOREIGN KEY (`releases_id`) REFERENCES '
            .$this->table('releases').' (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }

    private function dropSparseForeignKeys(): void
    {
        if (! $this->isMariaDbOrMySql()) {
            return;
        }

        if (Schema::hasTable('release_nzb_passwords')) {
            DB::statement('ALTER TABLE '.$this->table('release_nzb_passwords').' DROP FOREIGN KEY `FK_rnp_releases`');
        }
        if (Schema::hasTable('release_nzb_creation_failures')) {
            DB::statement('ALTER TABLE '.$this->table('release_nzb_creation_failures').' DROP FOREIGN KEY `FK_rncf_releases`');
        }
    }

    private function backfillSparseTables(): void
    {
        if (Schema::hasColumn('releases', 'nzb_password')) {
            DB::table('releases')
                ->select(['id', 'nzb_password'])
                ->whereNotNull('nzb_password')
                ->where('nzb_password', '<>', '')
                ->orderBy('id')
                ->chunkById(1000, function ($releases): void {
                    $rows = [];
                    foreach ($releases as $release) {
                        $rows[] = [
                            'releases_id' => (int) $release->id,
                            'password' => (string) $release->nzb_password,
                        ];
                    }
                    DB::table('release_nzb_passwords')->upsert($rows, ['releases_id'], ['password']);
                });
        }

        if (Schema::hasColumn('releases', 'nzb_creation_attempts')) {
            DB::table('releases')
                ->select(['id', 'nzb_creation_attempts', 'nzb_creation_last_error'])
                ->where(function ($query): void {
                    $query->where('nzb_creation_attempts', '>', 0)
                        ->orWhereNotNull('nzb_creation_last_error');
                })
                ->orderBy('id')
                ->chunkById(1000, function ($releases): void {
                    $now = now();
                    $rows = [];
                    foreach ($releases as $release) {
                        $rows[] = [
                            'releases_id' => (int) $release->id,
                            'attempts' => (int) $release->nzb_creation_attempts,
                            'last_error' => $release->nzb_creation_last_error,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('release_nzb_creation_failures')->upsert(
                        $rows,
                        ['releases_id'],
                        ['attempts', 'last_error', 'updated_at'],
                    );
                });
        }
    }

    private function recountVisibleComments(): void
    {
        if (! Schema::hasTable('release_comments') || ! Schema::hasColumn('releases', 'comments')) {
            return;
        }

        $chunkSize = $this->chunkSize();
        $releases = $this->table('releases');
        $comments = $this->table('release_comments');
        $lastId = 0;

        do {
            // Raw SQL with explicit prefixed names: Laravel also prefixes query
            // aliases, so `releases as r` would not match a raw `r.comments`.
            $mismatches = DB::select(
                "SELECT r.`id` AS id, COUNT(c.`id`) AS visible_comments
                 FROM {$releases} r LEFT JOIN {$comments} c
                   ON c.`releases_id` = r.`id` AND c.`isvisible` = 1
                 WHERE r.`id` > ?
                 GROUP BY r.`id`, r.`comments`
                 HAVING r.`comments` <> COUNT(c.`id`)
                 ORDER BY r.`id`
                 LIMIT {$chunkSize}",
                [$lastId],
            );

            if ($mismatches === []) {
                return;
            }

            $idsByCount = [];
            foreach ($mismatches as $mismatch) {
                $idsByCount[(int) $mismatch->visible_comments][] = (int) $mismatch->id;
                $lastId = max($lastId, (int) $mismatch->id);
            }

            foreach ($idsByCount as $count => $ids) {
                DB::table('releases')->whereIn('id', $ids)->update(['comments' => $count]);
            }
        } while (count($mismatches) === $chunkSize);
    }

    private function rebuildReleasesForMariaDb(): void
    {
        $specifications = ['DROP PRIMARY KEY', 'ADD PRIMARY KEY (`id`)'];
        foreach (self::REMOVED_RELEASE_INDEXES as $index) {
            if ($this->indexExists($index)) {
                $specifications[] = 'DROP INDEX `'.$index.'`';
            }
        }

        $specifications[] = 'MODIFY `guid` CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL';
        $specifications[] = "MODIFY `leftguid` CHAR(1) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'The first letter of the release guid'";
        foreach (self::REMOVED_RELEASE_COLUMNS as $column) {
            if (Schema::hasColumn('releases', $column)) {
                $specifications[] = 'DROP COLUMN `'.$column.'`';
            }
        }

        $specifications = [
            ...$specifications,
            'ADD UNIQUE INDEX `ux_releases_guid` (`guid`)',
            'ADD INDEX `ix_releases_predb_id` (`predb_id`)',
            'ADD INDEX `ix_releases_size` (`size`)',
            'ADD INDEX `ix_releases_add_pp_claim_queue` (`passwordstatus`, `haspreview`, `nzbstatus`, `leftguid`, `postdate` DESC, `id`, `additional_pp_claimed_at`)',
            'ADD INDEX `ix_releases_nzb_creation_group_queue` (`nzbstatus`, `groups_id`, `postdate` DESC, `id`, `nzb_creation_claimed_at`)',
            'ADD INDEX `ix_releases_nzb_creation_global_queue` (`nzbstatus`, `postdate` DESC, `id`, `nzb_creation_claimed_at`)',
        ];

        DB::statement('ALTER TABLE '.$this->table('releases').' '.implode(', ', $specifications));
    }

    private function normalizeReleasesForPortableDatabase(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            foreach (self::REMOVED_RELEASE_INDEXES as $index) {
                if ($this->indexExists($index)) {
                    $table->dropIndex($index);
                }
            }
            foreach (self::REMOVED_RELEASE_COLUMNS as $column) {
                if (Schema::hasColumn('releases', $column)) {
                    $table->dropColumn($column);
                }
            }
            $table->unique('guid', 'ux_releases_guid');
            $table->index('predb_id', 'ix_releases_predb_id');
            $table->index('size', 'ix_releases_size');
            $table->index(
                ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at'],
                'ix_releases_add_pp_claim_queue',
            );
            $table->index(
                ['nzbstatus', 'groups_id', 'postdate', 'id', 'nzb_creation_claimed_at'],
                'ix_releases_nzb_creation_group_queue',
            );
            $table->index(
                ['nzbstatus', 'postdate', 'id', 'nzb_creation_claimed_at'],
                'ix_releases_nzb_creation_global_queue',
            );
        });
    }

    private function restoreReleasesForMariaDb(): void
    {
        $specifications = ['DROP PRIMARY KEY', 'ADD PRIMARY KEY (`id`, `categories_id`)'];
        foreach ([
            'ux_releases_guid',
            'ix_releases_predb_id',
            'ix_releases_size',
            'ix_releases_add_pp_claim_queue',
            'ix_releases_nzb_creation_group_queue',
            'ix_releases_nzb_creation_global_queue',
        ] as $index) {
            if ($this->indexExists($index)) {
                $specifications[] = 'DROP INDEX `'.$index.'`';
            }
        }

        $specifications = [
            ...$specifications,
            'MODIFY `guid` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL',
            "MODIFY `leftguid` CHAR(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The first letter of the release guid'",
            'ADD COLUMN `updatetime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `adddate`',
            'ADD COLUMN `gid` VARCHAR(32) NULL AFTER `updatetime`',
            'ADD COLUMN `source` SMALLINT UNSIGNED NULL',
            'ADD COLUMN `nzb_password` VARCHAR(255) NULL',
            'ADD COLUMN `nzb_creation_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `nzb_creation_claim_token`',
            'ADD COLUMN `nzb_creation_last_error` TEXT NULL AFTER `nzb_creation_attempts`',
            'ADD COLUMN `proc_sorter` TINYINT(1) NOT NULL DEFAULT 0 AFTER `proc_pp`',
            'ADD COLUMN `audiostatus` TINYINT(1) NOT NULL DEFAULT 0 AFTER `videostatus`',
            'ADD INDEX `ix_releases_guid` (`guid`)',
            'ADD INDEX `ix_releases_adddate_only` (`adddate`)',
            'ADD INDEX `ix_releases_videos_id` (`videos_id`)',
            'ADD INDEX `ix_releases_movieinfo_id` (`movieinfo_id`)',
            'ADD INDEX `ix_releases_imdbid` (`imdbid`)',
            'ADD INDEX `ix_releases_tv_search_covering` (`passwordstatus`, `categories_id`, `postdate` DESC, `videos_id`, `tv_episodes_id`, `groups_id`)',
            'ADD INDEX `ix_releases_passwordstatus` (`passwordstatus`)',
            'ADD INDEX `ix_releases_haspreview_passwordstatus` (`haspreview`, `passwordstatus`)',
            'ADD INDEX `ix_releases_postdate_searchname` (`postdate`, `searchname`)',
            'ADD INDEX `ix_releases_predb_id_searchname` (`predb_id`, `searchname`)',
            'ADD INDEX `ix_releases_size_cat` (`size`, `categories_id`, `passwordstatus`)',
            'ADD INDEX `ix_releases_add_pp_claim_queue` (`passwordstatus`, `haspreview`, `nzbstatus`, `leftguid`, `additional_pp_claimed_at`, `postdate` DESC)',
            'ADD INDEX `ix_releases_nzb_creation_queue` (`nzbstatus`, `groups_id`, `leftguid`, `nzb_creation_claimed_at`, `postdate` DESC)',
        ];

        DB::statement('ALTER TABLE '.$this->table('releases').' '.implode(', ', $specifications));
    }

    private function restoreReleasesForPortableDatabase(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            foreach ([
                'ux_releases_guid',
                'ix_releases_predb_id',
                'ix_releases_size',
                'ix_releases_add_pp_claim_queue',
                'ix_releases_nzb_creation_group_queue',
                'ix_releases_nzb_creation_global_queue',
            ] as $index) {
                if ($this->indexExists($index)) {
                    $table->dropIndex($index);
                }
            }

            $table->timestamp('updatetime')->useCurrent();
            $table->string('gid', 32)->nullable();
            $table->unsignedSmallInteger('source')->nullable();
            $table->string('nzb_password')->nullable();
            $table->unsignedSmallInteger('nzb_creation_attempts')->default(0);
            $table->text('nzb_creation_last_error')->nullable();
            $table->boolean('proc_sorter')->default(false);
            $table->boolean('audiostatus')->default(false);
            $table->index('guid', 'ix_releases_guid');
            $table->index('adddate', 'ix_releases_adddate_only');
            $table->index('videos_id', 'ix_releases_videos_id');
            $table->index('movieinfo_id', 'ix_releases_movieinfo_id');
            $table->index('imdbid', 'ix_releases_imdbid');
            $table->index(
                ['passwordstatus', 'categories_id', 'postdate', 'videos_id', 'tv_episodes_id', 'groups_id'],
                'ix_releases_tv_search_covering',
            );
            $table->index('passwordstatus', 'ix_releases_passwordstatus');
            $table->index(['haspreview', 'passwordstatus'], 'ix_releases_haspreview_passwordstatus');
            $table->index(['postdate', 'searchname'], 'ix_releases_postdate_searchname');
            $table->index(['predb_id', 'searchname'], 'ix_releases_predb_id_searchname');
            $table->index(['size', 'categories_id', 'passwordstatus'], 'ix_releases_size_cat');
            $table->index(
                ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'additional_pp_claimed_at', 'postdate'],
                'ix_releases_add_pp_claim_queue',
            );
            $table->index(
                ['nzbstatus', 'groups_id', 'leftguid', 'nzb_creation_claimed_at', 'postdate'],
                'ix_releases_nzb_creation_queue',
            );
        });
    }

    private function restoreSparseDataToReleases(): void
    {
        $chunkSize = $this->chunkSize();

        if (Schema::hasTable('release_nzb_passwords')) {
            DB::table('release_nzb_passwords')->orderBy('releases_id')->chunkById(
                $chunkSize,
                fn ($rows) => $this->batchUpdateReleases($rows, ['nzb_password' => 'password']),
                'releases_id',
            );
        }

        if (Schema::hasTable('release_nzb_creation_failures')) {
            DB::table('release_nzb_creation_failures')->orderBy('releases_id')->chunkById(
                $chunkSize,
                fn ($rows) => $this->batchUpdateReleases($rows, [
                    'nzb_creation_attempts' => 'attempts',
                    'nzb_creation_last_error' => 'last_error',
                ]),
                'releases_id',
            );
        }
    }

    /**
     * Push one chunk of sparse rows back into `releases` with a single
     * `CASE`-driven statement instead of one `UPDATE` per row.
     *
     * @param  Collection<int, object>  $rows
     * @param  array<string, string>  $columnMap  Target `releases` column => source column
     */
    private function batchUpdateReleases($rows, array $columnMap): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $setClauses = [];
        $bindings = [];
        foreach ($columnMap as $target => $source) {
            $clause = '`'.$target.'` = CASE `id`';
            foreach ($rows as $row) {
                $clause .= ' WHEN ? THEN ?';
                $bindings[] = (int) $row->releases_id;
                $bindings[] = $row->{$source};
            }
            $setClauses[] = $clause.' ELSE `'.$target.'` END';
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row->releases_id;
        }

        DB::update(
            'UPDATE '.$this->table('releases').' SET '.implode(', ', $setClauses)
            .' WHERE `id` IN ('.implode(', ', array_fill(0, count($ids), '?')).')',
            [...$bindings, ...$ids],
        );
    }

    /**
     * Abort before the COPY-algorithm rebuild when the data volume cannot hold
     * a second copy of the table. Silently skipped when the server's datadir is
     * not visible to PHP (remote or containerised MySQL).
     */
    private function assertEnoughFreeSpaceForRebuild(): void
    {
        if (! $this->isMariaDbOrMySql() || (bool) config('nntmux.releases_optimize.skip_free_space_check', false)) {
            return;
        }

        $sizes = DB::selectOne(
            'SELECT DATA_LENGTH + INDEX_LENGTH AS total_bytes FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [DB::getTablePrefix().'releases'],
        );
        $required = (int) ($sizes->total_bytes ?? 0) * 2;
        if ($required <= 0) {
            return;
        }

        $dataDir = (string) (DB::selectOne('SELECT @@datadir AS dir')->dir ?? '');
        if ($dataDir === '' || ! is_dir($dataDir) || ! is_readable($dataDir)) {
            return;
        }

        $free = @disk_free_space($dataDir);
        if ($free === false || $free >= $required) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Releases rebuild needs about %d bytes free in %s but only %d are available. '
            .'Free up space, use gh-ost/pt-online-schema-change, or set RELEASES_OPTIMIZE_SKIP_FREE_SPACE_CHECK=true to override.',
            $required,
            $dataDir,
            (int) $free,
        ));
    }

    private function chunkSize(): int
    {
        return max(100, min(10000, (int) config('nntmux.releases_optimize.chunk_size', 5000)));
    }

    private function assertReleaseIdentifiersAreSafe(): void
    {
        if ((bool) config('nntmux.releases_optimize.skip_preflight', false)) {
            return;
        }

        $duplicateIds = DB::query()->fromSub(
            DB::table('releases')->select('id')->groupBy('id')->havingRaw('COUNT(*) > 1'),
            'duplicate_release_ids',
        )->count();

        if ($this->isMariaDbOrMySql()) {
            $invalidGuids = DB::table('releases')
                ->whereNull('guid')
                ->orWhereRaw("guid NOT REGEXP '^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$'")
                ->count();
        } else {
            $invalidGuids = DB::table('releases')->pluck('guid')->filter(
                static fn (mixed $guid): bool => ! is_string($guid)
                    || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $guid) !== 1,
            )->count();
        }

        $duplicateGuids = DB::query()->fromSub(
            DB::table('releases')->selectRaw('LOWER(guid) AS normalized_guid')->groupByRaw('LOWER(guid)')->havingRaw('COUNT(*) > 1'),
            'duplicate_release_guids',
        )->count();
        $leftGuidMismatches = DB::table('releases')
            ->whereRaw('LOWER(leftguid) <> LOWER(SUBSTR(guid, 1, 1))')
            ->count();

        $blockers = array_filter([
            'duplicate release IDs' => $duplicateIds,
            'invalid release GUIDs' => $invalidGuids,
            'case-insensitive duplicate release GUIDs' => $duplicateGuids,
            'mismatched release leftguid values' => $leftGuidMismatches,
        ]);

        if ($blockers !== []) {
            throw new RuntimeException(
                'Releases optimization preflight failed: '.json_encode($blockers, JSON_THROW_ON_ERROR)
                .'. Run `php artisan releases:normalize-guids --dry-run` to see how to resolve them.'
            );
        }
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
