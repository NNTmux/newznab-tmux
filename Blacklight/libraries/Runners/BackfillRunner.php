<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillRunner extends BaseRunner
{
    public function backfill(array $options = []): void
    {
        $select = 'SELECT name';
        if (($options[0] ?? false) !== false) {
            $select .= ', '.$options[0].' AS max';
        }
        $select .= ' FROM usenet_groups WHERE backfill = 1';
        $work = DB::select($select);

        $maxProcesses = (int) Settings::settingValue('backfillthreads');

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
            $this->runStreamingCommands($commands, $maxProcesses, 'backfill');

            return;
        }

        $this->headerStart('backfill', $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($work, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $group) {
                $command = PHP_BINARY.' artisan update:backfill '.$group->name.(isset($group->max) ? (' '.$group->max) : '');
                $tasks[$group->name] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $groupName => $output) {
                    echo $output;
                    $this->colorCli->primary('Backfilled group '.$groupName);
                }
            } catch (\Throwable $e) {
                Log::error('Backfill batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    public function safeBackfill(): void
    {
        // make sure short_groups is up-to-date - Updated to use new script location (modernized)
        $this->executeCommand(PHP_BINARY.' app/Services/Tmux/Scripts/update_groups.php');

        $backfill_qty = (int) Settings::settingValue('backfill_qty');
        $backfill_order = (int) Settings::settingValue('backfill_order');
        $backfill_days = (int) Settings::settingValue('backfill_days');
        $maxMessages = (int) Settings::settingValue('maxmssgs');
        $threads = (int) Settings::settingValue('backfillthreads');

        $orderby = 'ORDER BY a.last_record ASC';
        switch ($backfill_order) {
            case 1: $orderby = 'ORDER BY first_record_postdate DESC';
                break;
            case 2: $orderby = 'ORDER BY first_record_postdate ASC';
                break;
            case 3: $orderby = 'ORDER BY name ASC';
                break;
            case 4: $orderby = 'ORDER BY name DESC';
                break;
            case 5: $orderby = 'ORDER BY a.last_record DESC';
                break;
        }

        $backfilldays = '0';
        if ($backfill_days === 1) {
            $backfilldays = 'g.backfill_target';
        } elseif ($backfill_days === 2) {
            $backfilldays = (string) now()->diffInDays(Carbon::createFromFormat('Y-m-d', Settings::settingValue('safebackfilldate')), true);
        }

        $sql = 'SELECT g.name,
                g.first_record AS our_first,
                MAX(a.first_record) AS their_first,
                MAX(a.last_record) AS their_last
            FROM usenet_groups g
            INNER JOIN short_groups a ON g.name = a.name
            WHERE g.first_record IS NOT NULL
            AND g.first_record_postdate IS NOT NULL
            AND g.backfill = 1
            AND (NOW() - INTERVAL '.$backfilldays.' DAY ) < g.first_record_postdate
            GROUP BY a.name, a.last_record, g.name, g.first_record
            '.$orderby.' LIMIT 1';

        $data = DB::select($sql);

        $groupName = '';
        $count = 0;
        if (! empty($data) && isset($data[0]->name)) {
            $groupName = $data[0]->name;
            $count = ($data[0]->our_first - $data[0]->their_first);
        }

        if ($count <= 0) {
            $this->headerNone();
            if (config('nntmux.echocli') && $groupName !== '') {
                $this->colorCli->primary('No backfill needed for group '.$groupName);
            }

            return;
        }

        $getEach = ($count > ($backfill_qty * $threads))
            ? (int) ceil(($backfill_qty * $threads) / $maxMessages)
            : (int) ceil($count / $maxMessages);

        $queues = [];
        for ($i = 0; $i <= $getEach - 1; $i++) {
            $queues[$i] = sprintf('get_range  backfill  %s  %s  %s  %s', $groupName, $data[0]->our_first - $i * $maxMessages - $maxMessages, $data[0]->our_first - $i * $maxMessages - 1, $i + 1);
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($queues as $queue) {
                $commands[] = $this->buildDnrCommand($queue);
            }
            $this->runStreamingCommands($commands, $threads, 'safe_backfill');

            return;
        }

        $this->headerStart('safe_backfill', count($queues), $threads);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($queues, max(1, $threads), true);

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $queue) {
                $command = $this->buildDnrCommand($queue);
                $tasks[$idx] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $idx => $output) {
                    echo $output;
                    $this->colorCli->primary('Backfilled group '.$groupName);
                }
            } catch (\Throwable $e) {
                Log::error('Safe backfill batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }
}
