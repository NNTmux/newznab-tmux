<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Binaries\CbpOrphanPruner;
use App\Services\Configuration\ProcessingRuntimeStateRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('cbp:prune-orphans
    {--rebuild-collection-groups : Rebuild collection_groups from live collections instead of deleting stale rows (requires tmux to be stopped)}
    {--batch=50000 : Rows examined per DELETE}
    {--table=* : Limit to collection_groups, binaries and/or parts}')]
#[Description('Delete collection_groups, binaries and parts rows whose parent row no longer exists')]
final class PruneCbpOrphans extends Command
{
    public function handle(CbpOrphanPruner $pruner, ProcessingRuntimeStateRepository $runtimeState): int
    {
        $tables = $this->option('table') ?: array_keys(CbpOrphanPruner::TARGETS);
        $unknown = array_diff($tables, array_keys(CbpOrphanPruner::TARGETS));
        if ($unknown !== []) {
            $this->error('Unknown table(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        if ($this->option('rebuild-collection-groups')) {
            if (! \in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                $this->error('Rebuilding collection_groups requires MySQL or MariaDB.');

                return self::FAILURE;
            }
            if ($runtimeState->isTmuxRunning()) {
                $this->error('Refusing to rebuild collection_groups while tmux is running. Run `php artisan tmux:stop` first.');

                return self::FAILURE;
            }

            $result = $pruner->rebuildCollectionGroups();
            $this->info(sprintf(
                'collection_groups rebuilt: kept %s rows, removed %s stale rows.',
                number_format($result['kept']),
                number_format($result['removed'])
            ));
            $tables = array_values(array_diff($tables, ['collection_groups']));
        }

        $batchSize = max(1, (int) $this->option('batch'));
        foreach (CbpOrphanPruner::TARGETS as $table => $_target) {
            if (! \in_array($table, $tables, true)) {
                continue;
            }

            $this->line("Sweeping {$table}...");
            $result = $pruner->sweep(
                $table,
                $batchSize,
                onBatch: function (int $deleted, int $cursor) use ($table): void {
                    if ($this->output->isVerbose()) {
                        $this->line(sprintf('  %s: deleted %s up to key %s', $table, number_format($deleted), $cursor));
                    }
                }
            );
            $this->info(sprintf('%s: deleted %s orphan rows.', $table, number_format($result['deleted'])));
        }

        return self::SUCCESS;
    }
}
