<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Facades\Search;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class NntmuxSearchRepair extends Command
{
    protected $signature = 'nntmux:search-repair
                            {--limit=100 : Maximum failed releases to retry}
                            {--dry-run : Report due failures without writing to Manticore}';

    protected $description = 'Retry failed release search-index updates';

    public function handle(): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));
        $query = DB::table('search_index_failures')
            ->whereNull('resolved_at')
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit);

        $rows = $query->get(['release_id', 'operation']);
        if ($rows->isEmpty()) {
            $this->info('No failed release index updates are due for repair.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $releaseId = (int) $row->release_id;
            if ((bool) $this->option('dry-run')) {
                $this->line((string) $releaseId);

                continue;
            }

            if ($row->operation === 'delete') {
                Search::deleteRelease($releaseId);
            } else {
                Search::updateRelease($releaseId);
            }
        }

        $this->info(sprintf('%s release index failure(s) %s.', $rows->count(), $this->option('dry-run') ? 'reported' : 'processed'));

        return self::SUCCESS;
    }
}
