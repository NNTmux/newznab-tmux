<?php

declare(strict_types=1);

namespace App\Services\Runners;

use App\Models\Settings;
use App\Services\Backfill\SafeBackfillPlanner;
use App\Services\BackgroundWorkPressureGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillRunner extends BaseRunner
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function backfill(array $options = []): void
    {
        $select = 'SELECT name';
        if (($options[0] ?? false) !== false) {
            $select .= ', '.$options[0].' AS max';
        }
        $select .= ' FROM usenet_groups WHERE backfill = 1';
        $work = DB::select($select);

        $maxProcesses = max(1, min(8, (int) Settings::settingValue('backfillthreads')));

        $count = count($work);
        if ($count === 0) {
            $this->headerNone();

            return;
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($work as $group) {
                $commands[] = PHP_BINARY.' artisan update:backfill '.$group->name.(isset($group->max) ? (' '.$group->max) : '');
            }
            $this->runStreamingCommands($commands, $maxProcesses, 'backfill'); // @phpstan-ignore argument.type

            return;
        }

        $this->headerStart('backfill', $count, $maxProcesses);

        // Build commands array for parallel execution
        $commands = [];
        foreach ($work as $group) {
            $commands[$group->name] = PHP_BINARY.' artisan update:backfill '.$group->name.(isset($group->max) ? (' '.$group->max) : '');
        }

        // Process using parallel commands with configurable timeout
        $results = $this->runParallelCommands($commands, $maxProcesses);

        foreach ($results as $groupName => $output) {
            echo $output;
            cli()->primary('Backfilled group '.$groupName);
        }
    }

    public function safeBackfill(): void
    {
        // make sure short_groups is up-to-date - Updated to use new script location (modernized)
        $this->executeCommand(PHP_BINARY.' app/Services/Tmux/Scripts/update_groups.php');

        $articleLimit = max(1, min(1_000_000, (int) Settings::settingValue('backfill_qty')));
        $groupLimit = max(1, min(16, (int) Settings::settingValue('backfill_groups')));
        $targetMode = (int) Settings::settingValue('backfill_days');
        $globalTargetDays = $targetMode === 2
            ? (int) now()->diffInDays(Carbon::createFromFormat('Y-m-d', Settings::settingValue('safebackfilldate')), true)
            : 0;

        $query = DB::table('usenet_groups as g')
            ->join('short_groups as a', 'g.name', '=', 'a.name')
            ->whereNotNull('g.first_record')
            ->whereNotNull('g.first_record_postdate')
            ->where('g.backfill', 1)
            ->selectRaw('g.name, g.first_record AS our_first, g.first_record_postdate, g.backfill_target, MAX(a.first_record) AS their_first, MAX(a.last_record) AS their_last')
            ->groupBy('g.id', 'g.name', 'g.first_record', 'g.first_record_postdate', 'g.backfill_target');

        if ($targetMode === 1) {
            $query->whereRaw('g.first_record_postdate > DATE_SUB(NOW(), INTERVAL g.backfill_target DAY)');
        } elseif ($targetMode === 2) {
            $query->where('g.first_record_postdate', '>', now()->subDays($globalTargetDays));
        }

        match ((int) Settings::settingValue('backfill_order')) {
            1 => $query->orderByDesc('g.first_record_postdate'),
            2 => $query->orderBy('g.first_record_postdate'),
            3 => $query->orderBy('g.name'),
            4 => $query->orderByDesc('g.name'),
            5 => $query->orderByDesc('their_last'),
            default => $query->orderBy('their_last'),
        };

        $candidates = $query->limit($groupLimit)->get()->all();
        $batches = app(SafeBackfillPlanner::class)->plan(
            $candidates,
            $articleLimit,
            $groupLimit,
            $targetMode,
            $globalTargetDays,
        );

        if ($batches === []) {
            $this->headerNone();

            return;
        }

        app(BackgroundWorkPressureGate::class)->awaitPermission(static function (string $reason): void {
            if (config('nntmux.echocli')) {
                cli()->warning('Safe backfill paused: '.$reason);
            }
        });

        $commands = [];
        foreach ($batches as $batch) {
            $commands[$batch['name']] = sprintf(
                '%s artisan backfill:group-batch %s %d %d',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($batch['name']),
                $batch['articles'],
                $batch['target_days'],
            );
        }

        $threads = max(1, min(8, (int) Settings::settingValue('backfillthreads'), count($commands)));

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $this->runStreamingCommands($commands, $threads, 'safe_backfill');

            return;
        }

        $this->headerStart('safe_backfill', count($commands), $threads);

        // Process using parallel commands with configurable timeout
        $results = $this->runParallelCommands($commands, $threads);

        foreach ($results as $groupName => $output) {
            echo $output;
            cli()->primary('Backfilled group '.$groupName);
        }
    }
}
