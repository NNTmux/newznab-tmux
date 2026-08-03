<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\XrefService;
use App\Support\Utf8;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OptimizeCbpStorage extends Command
{
    protected $signature = 'cbp:optimize-storage
                            {--execute : Apply the reported storage changes}
                            {--batch= : Override the bounded reconciliation batch size}';

    protected $description = 'Audit or resumably optimize collections, binaries, and parts storage';

    public function handle(XrefService $xrefService): int
    {
        if (! \in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('CBP storage optimization requires MySQL 8 or MariaDB. SQLite is test-only.');

            return self::FAILURE;
        }

        foreach (['collections', 'binaries', 'parts'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} does not exist.");

                return self::FAILURE;
            }
        }

        $this->reportPreflight();
        if (! (bool) $this->option('execute')) {
            $this->newLine();
            $this->warn('Dry-run only. Stop processing, verify a backup, then rerun with --execute.');

            return self::SUCCESS;
        }

        $batchSize = max(1, min(5000, (int) ($this->option('batch') ?: config('nntmux.cbp.reconcile_batch_size', 500))));
        $this->ensureCheckpointTable();

        try {
            $this->step('01_additive_schema', fn () => $this->prepareAdditiveSchema());
            $this->step('02_collection_groups', fn () => $this->backfillCollectionGroups($xrefService, $batchSize));
            $this->step('03_hash_columns', fn () => $this->prepareHashColumns());
            $this->step('04_binary_hashes', fn () => $this->populateBinaryHashes($batchSize));
            $this->step('05_binary_map', fn () => $this->buildBinaryMap());
            $this->step('06_parts_shadow', fn () => $this->buildPartsShadow());
            $this->step('07_swap_parts', fn () => $this->swapParts());
            $this->step('08_merge_binaries', fn () => $this->mergeBinaries());
            $this->step('09_collection_hash', fn () => $this->convertCollectionHash());
            $this->step('10_constraints', fn () => $this->installConstraintsAndIndexes());
            $this->step('11_aggregates', fn () => $this->rebuildAggregates($batchSize));
            $this->step('12_finalize', fn () => $this->finalizeOptimization());
        } catch (\Throwable $e) {
            $this->error('Optimization stopped: '.$e->getMessage());
            $this->warn('Completed checkpoints are retained; fix the cause and rerun the same command.');

            return self::FAILURE;
        }

        $this->info('CBP storage optimization completed. Validate the preflight again before restarting workers.');

        return self::SUCCESS;
    }

    private function reportPreflight(): void
    {
        $duplicateParts = (int) DB::scalar(
            'SELECT COALESCE(SUM(n - 1), 0) FROM (
                SELECT COUNT(*) AS n FROM parts GROUP BY binaries_id, partnumber HAVING COUNT(*) > 1
             ) duplicate_parts'
        );
        $duplicateBinaries = (int) DB::scalar(
            'SELECT COALESCE(SUM(n - 1), 0) FROM (
                SELECT COUNT(*) AS n FROM binaries GROUP BY collections_id, binaryhash HAVING COUNT(*) > 1
             ) duplicate_binaries'
        );
        $aggregateDrift = (int) DB::scalar(
            'SELECT COUNT(*) FROM binaries b
             LEFT JOIN (
                SELECT binaries_id, COUNT(*) AS currentparts, COALESCE(SUM(size), 0) AS partsize
                FROM parts GROUP BY binaries_id
             ) p ON p.binaries_id = b.id
             WHERE b.currentparts <> COALESCE(p.currentparts, 0)
                OR b.partsize <> COALESCE(p.partsize, 0)'
        );
        $orphanParts = (int) DB::scalar(
            'SELECT COUNT(*) FROM parts p LEFT JOIN binaries b ON b.id = p.binaries_id WHERE b.id IS NULL'
        );
        $orphanBinaries = (int) DB::scalar(
            'SELECT COUNT(*) FROM binaries b LEFT JOIN collections c ON c.id = b.collections_id WHERE c.id IS NULL'
        );
        $invalidMessageIds = (int) DB::scalar(
            "SELECT COUNT(*) FROM parts WHERE messageid = '' OR messageid REGEXP '[^ -~]'"
        );
        $bytes = (int) DB::scalar(
            "SELECT COALESCE(SUM(data_length + index_length), 0)
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE() AND table_name IN ('collections', 'binaries', 'parts')"
        );
        $freeBytes = (int) DB::scalar(
            "SELECT COALESCE(SUM(data_free), 0)
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE() AND table_name IN ('collections', 'binaries', 'parts')"
        );
        $cascadeForeignKeys = (int) DB::scalar(
            "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME IN ('binaries', 'parts')
               AND DELETE_RULE = 'CASCADE'"
        );

        $this->table(['Check', 'Result'], [
            ['Duplicate binary/part identities', number_format($duplicateBinaries)],
            ['Duplicate binary part numbers', number_format($duplicateParts)],
            ['Binary aggregate drift', number_format($aggregateDrift)],
            ['Orphan parts / binaries', number_format($orphanParts).' / '.number_format($orphanBinaries)],
            ['Required cascading foreign keys', $cascadeForeignKeys.'/2 healthy'],
            ['Empty or non-ASCII message IDs', number_format($invalidMessageIds)],
            ['Current CBP table+index bytes', number_format($bytes)],
            ['Reported reusable table bytes', number_format($freeBytes)],
            ['Conservative additional disk required', number_format(max($bytes, 1))],
        ]);
        $this->line('Proposed: normalized group table, compact hashes, corrected identities, deterministic part merge, authoritative aggregates, cascade FKs, and hot-path indexes.');
    }

    private function ensureCheckpointTable(): void
    {
        DB::statement(
            'CREATE TABLE IF NOT EXISTS cbp_optimization_checkpoints (
                step_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                completed_at DATETIME NOT NULL,
                PRIMARY KEY (step_name)
             ) ENGINE=InnoDB'
        );
    }

    private function step(string $name, callable $operation): void
    {
        if (DB::table('cbp_optimization_checkpoints')->where('step_name', $name)->exists()) {
            $this->line("Skipping completed step {$name}.");

            return;
        }

        $this->info("Running {$name}...");
        $operation();
        DB::table('cbp_optimization_checkpoints')->insert([
            'step_name' => $name,
            'completed_at' => now(),
        ]);
    }

    private function prepareAdditiveSchema(): void
    {
        if (! Schema::hasColumn('collections', 'last_seen_at')) {
            DB::statement('ALTER TABLE collections ADD last_seen_at DATETIME NULL AFTER dateadded');
        }
        DB::statement(
            'CREATE TABLE IF NOT EXISTS collection_groups (
                collections_id INT UNSIGNED NOT NULL,
                group_name VARCHAR(255) NOT NULL,
                PRIMARY KEY (collections_id, group_name)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci'
        );
    }

    private function backfillCollectionGroups(XrefService $xrefService, int $batchSize): void
    {
        $lastId = 0;
        do {
            $rows = DB::select(
                'SELECT c.id, c.xref, g.name AS group_name
                 FROM collections c
                 LEFT JOIN usenet_groups g ON g.id = c.groups_id
                 WHERE c.id > ? ORDER BY c.id LIMIT '.$batchSize,
                [$lastId]
            );
            $values = [];
            $bindings = [];
            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $groups = $xrefService->extractGroupNames((string) $row->xref);
                if ($groups === [] && (string) $row->group_name !== '') {
                    $groups = [(string) $row->group_name];
                }
                foreach ($groups as $group) {
                    $values[] = '(?, ?)';
                    $bindings[] = $lastId;
                    $bindings[] = $group;
                }
            }
            if ($values !== []) {
                DB::statement(
                    'INSERT IGNORE INTO collection_groups (collections_id, group_name) VALUES '.implode(',', $values),
                    $bindings
                );
            }
        } while (\count($rows) === $batchSize);
    }

    private function prepareHashColumns(): void
    {
        if (! Schema::hasColumn('binaries', 'cbp_hash')) {
            DB::statement('ALTER TABLE binaries ADD cbp_hash BINARY(16) NULL AFTER binaryhash');
        }
        if (! Schema::hasColumn('collections', 'cbp_hash')) {
            DB::statement('ALTER TABLE collections ADD cbp_hash BINARY(20) NULL AFTER collectionhash');
        }
    }

    private function populateBinaryHashes(int $batchSize): void
    {
        $lastId = 0;
        do {
            $rows = DB::select(
                'SELECT b.id, b.name, b.filenumber, c.fromname
                 FROM binaries b INNER JOIN collections c ON c.id = b.collections_id
                 WHERE b.id > ? AND b.cbp_hash IS NULL ORDER BY b.id LIMIT '.$batchSize,
                [$lastId]
            );
            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $hash = (int) $row->filenumber > 0
                    ? md5('file:'.(int) $row->filenumber, true)
                    : md5('subject:'.$this->normalizeIdentity((string) $row->name)."\0poster:".$this->normalizeIdentity((string) $row->fromname), true);
                DB::update('UPDATE binaries SET cbp_hash = ? WHERE id = ?', [$hash, $lastId]);
            }
        } while (\count($rows) === $batchSize);
    }

    private function buildBinaryMap(): void
    {
        DB::statement('DROP TABLE IF EXISTS cbp_binary_map');
        DB::statement(
            'CREATE TABLE cbp_binary_map (
                old_id BIGINT UNSIGNED NOT NULL,
                keep_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (old_id), KEY ix_cbp_binary_map_keep (keep_id)
             ) ENGINE=InnoDB'
        );
        DB::statement(
            'INSERT INTO cbp_binary_map (old_id, keep_id)
             SELECT id, MIN(id) OVER (PARTITION BY collections_id, cbp_hash) FROM binaries'
        );

        $binaryCount = (int) DB::table('binaries')->count();
        $mappedCount = (int) DB::table('cbp_binary_map')->count();
        if ($mappedCount !== $binaryCount) {
            throw new \RuntimeException("Binary map is incomplete ({$mappedCount}/{$binaryCount}); the source tables were not changed.");
        }
    }

    private function buildPartsShadow(): void
    {
        DB::statement('DROP TABLE IF EXISTS parts_cbp_new');
        DB::statement(
            'CREATE TABLE parts_cbp_new (
                binaries_id BIGINT UNSIGNED NOT NULL,
                messageid VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT \'\',
                number BIGINT UNSIGNED NOT NULL DEFAULT 0,
                partnumber INT UNSIGNED NOT NULL DEFAULT 0,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (binaries_id, partnumber),
                KEY ix_parts_number (number)
             ) ENGINE=InnoDB'
        );
        DB::statement(
            'INSERT INTO parts_cbp_new (binaries_id, messageid, number, partnumber, size)
             SELECT binaries_id, messageid, number, partnumber, size
             FROM (
                SELECT m.keep_id AS binaries_id, p.messageid, p.number, p.partnumber, p.size,
                       ROW_NUMBER() OVER (
                         PARTITION BY m.keep_id, p.partnumber
                         ORDER BY (p.messageid <> \'\') DESC, p.size DESC, p.number ASC
                       ) AS preference
                FROM parts p INNER JOIN cbp_binary_map m ON m.old_id = p.binaries_id
             ) ranked WHERE preference = 1'
        );

        $unmappedParts = (int) DB::scalar(
            'SELECT COUNT(*) FROM parts p
             LEFT JOIN cbp_binary_map m ON m.old_id = p.binaries_id
             WHERE m.old_id IS NULL'
        );
        if ($unmappedParts > 0) {
            throw new \RuntimeException("{$unmappedParts} parts are missing a binary-map entry; the source tables were not changed.");
        }

        $expectedParts = (int) DB::scalar(
            'SELECT COUNT(*) FROM (
                SELECT m.keep_id, p.partnumber
                FROM parts p INNER JOIN cbp_binary_map m ON m.old_id = p.binaries_id
                GROUP BY m.keep_id, p.partnumber
             ) retained_parts'
        );
        $retainedParts = (int) DB::table('parts_cbp_new')->count();
        if ($retainedParts !== $expectedParts) {
            throw new \RuntimeException("Parts shadow is incomplete ({$retainedParts}/{$expectedParts}); the source tables were not changed.");
        }
    }

    private function swapParts(): void
    {
        if (! Schema::hasTable('parts_cbp_new') && Schema::hasTable('parts_cbp_pre_optimize')) {
            return;
        }
        if (! Schema::hasTable('parts_cbp_new')) {
            throw new \RuntimeException('parts_cbp_new is missing; rerun the parts shadow step.');
        }
        $this->dropForeignKeyIfExists('parts', 'FK_binaries');
        DB::statement('DROP TABLE IF EXISTS parts_cbp_pre_optimize');
        DB::statement('RENAME TABLE parts TO parts_cbp_pre_optimize, parts_cbp_new TO parts');
    }

    private function mergeBinaries(): void
    {
        if (! Schema::hasColumn('binaries', 'cbp_hash')) {
            return;
        }
        DB::statement(
            'UPDATE binaries keep_binary
             INNER JOIN (
                SELECT m.keep_id, MAX(b.totalparts) AS totalparts
                FROM cbp_binary_map m INNER JOIN binaries b ON b.id = m.old_id
                GROUP BY m.keep_id
             ) totals ON totals.keep_id = keep_binary.id
             SET keep_binary.totalparts = totals.totalparts'
        );
        DB::statement(
            'DELETE b FROM binaries b INNER JOIN cbp_binary_map m ON m.old_id = b.id WHERE m.old_id <> m.keep_id'
        );
        DB::statement('UPDATE binaries SET binaryhash = cbp_hash');

        $this->dropIndexIfExists('binaries', 'ux_collection_id_filenumber');
        $this->dropIndexIfExists('binaries', 'ix_binaries_binaryhash');
        $this->dropIndexIfExists('binaries', 'ix_binaries_collection_hash');
        $this->dropIndexIfExists('binaries', 'ux_binaries_collection_hash');
        DB::statement('ALTER TABLE binaries MODIFY binaryhash BINARY(16) NOT NULL');
        DB::statement('ALTER TABLE binaries DROP COLUMN cbp_hash');
        DB::statement('CREATE UNIQUE INDEX ux_binaries_collection_hash ON binaries (collections_id, binaryhash)');
        if (! $this->indexExists('binaries', 'ix_binaries_collection_filenumber')) {
            DB::statement('CREATE INDEX ix_binaries_collection_filenumber ON binaries (collections_id, filenumber)');
        }
    }

    private function convertCollectionHash(): void
    {
        if (! Schema::hasColumn('collections', 'cbp_hash')) {
            return;
        }
        DB::statement(
            "UPDATE collections SET cbp_hash = CASE
                WHEN OCTET_LENGTH(collectionhash) = 20 THEN CAST(collectionhash AS BINARY)
                WHEN OCTET_LENGTH(collectionhash) = 40 AND collectionhash REGEXP '^[0-9A-Fa-f]{40}$' THEN UNHEX(collectionhash)
                ELSE UNHEX(SHA1(collectionhash)) END
             WHERE cbp_hash IS NULL"
        );
        $this->dropIndexIfExists('collections', 'ix_collection_collectionhash');
        DB::statement('ALTER TABLE collections DROP COLUMN collectionhash');
        DB::statement('ALTER TABLE collections CHANGE cbp_hash collectionhash BINARY(20) NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ix_collection_collectionhash ON collections (collectionhash)');
    }

    private function installConstraintsAndIndexes(): void
    {
        if (! $this->indexExists('collections', 'ix_collections_group_filecheck_seen_id')) {
            DB::statement('CREATE INDEX ix_collections_group_filecheck_seen_id ON collections (groups_id, filecheck, last_seen_at, id)');
        }
        $this->dropForeignKeyIfExists('collection_groups', 'fk_collection_groups_collection');
        DB::statement(
            'ALTER TABLE collection_groups ADD CONSTRAINT fk_collection_groups_collection
             FOREIGN KEY (collections_id) REFERENCES collections(id) ON DELETE CASCADE ON UPDATE CASCADE'
        );
        $this->dropForeignKeyIfExists('parts', 'FK_binaries');
        DB::statement(
            'ALTER TABLE parts ADD CONSTRAINT FK_binaries
             FOREIGN KEY (binaries_id) REFERENCES binaries(id) ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }

    private function rebuildAggregates(int $batchSize): void
    {
        $lastId = 0;
        do {
            $ids = DB::table('binaries')->where('id', '>', $lastId)->orderBy('id')->limit($batchSize)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                break;
            }
            $lastId = (int) end($ids);
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));
            DB::update(
                "UPDATE binaries b LEFT JOIN (
                    SELECT binaries_id, COUNT(*) currentparts, COALESCE(SUM(size), 0) partsize
                    FROM parts WHERE binaries_id IN ({$placeholders}) GROUP BY binaries_id
                 ) p ON p.binaries_id = b.id
                 SET b.currentparts = COALESCE(p.currentparts, 0),
                     b.partsize = COALESCE(p.partsize, 0),
                     b.partcheck = CASE WHEN COALESCE(p.currentparts, 0) >= b.totalparts THEN 1 ELSE 0 END
                 WHERE b.id IN ({$placeholders})",
                [...$ids, ...$ids]
            );
        } while (\count($ids) === $batchSize);

        DB::update(
            'UPDATE collections c LEFT JOIN (
                SELECT collections_id, COALESCE(SUM(partsize), 0) filesize FROM binaries GROUP BY collections_id
             ) b ON b.collections_id = c.id SET c.filesize = COALESCE(b.filesize, 0)'
        );
    }

    private function finalizeOptimization(): void
    {
        DB::statement('DROP TABLE IF EXISTS parts_cbp_pre_optimize');
        DB::statement('DROP TABLE IF EXISTS cbp_binary_map');
        DB::statement('ANALYZE TABLE collections, binaries, parts, collection_groups');
    }

    private function normalizeIdentity(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', Utf8::clean($value)) ?? ''));
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        $exists = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [$table, $constraint]
        );
        if ($exists !== []) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
}
