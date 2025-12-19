<?php

namespace App\Services\Runners;

use App\Models\Settings;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BinariesRunner extends BaseRunner
{
    public function binaries(int $maxPerGroup): void
    {
        $work = DB::select(
            sprintf(
                'SELECT name, %d AS max FROM usenet_groups WHERE active = 1',
                $maxPerGroup
            )
        );

        $maxProcesses = (int) Settings::settingValue('binarythreads');

        $count = count($work);
        if ($count === 0) {
            $this->headerNone();

            return;
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($work as $group) {
                $commands[] = PHP_BINARY.' artisan update:binaries '.$group->name.' '.$group->max;
            }
            $this->runStreamingCommands($commands, $maxProcesses, 'binaries');

            return;
        }

        $this->headerStart('binaries', $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($work, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $group) {
                $command = PHP_BINARY.' artisan update:binaries '.$group->name.' '.$group->max;
                $tasks[$group->name] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $groupName => $output) {
                    echo $output;
                    $this->colorCli->primary('Updated group '.$groupName);
                }
            } catch (\Throwable $e) {
                Log::error('Binaries batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    public function safeBinaries(): void
    {
        // update group stats - Updated to use new script location (modernized)
        $this->executeCommand(PHP_BINARY.' app/Services/Tmux/Scripts/update_groups.php');

        $maxHeaders = (int) Settings::settingValue('max_headers_iteration') ?: 1000000;
        $maxMessages = (int) Settings::settingValue('maxmssgs');

        // Prevent division by zero - ensure maxmssgs is at least 1
        if ($maxMessages < 1) {
            $defaultMaxMessages = 20000;
            $this->colorCli->warning('maxmssgs setting is invalid or not set, using default of '.$defaultMaxMessages);
            $maxMessages = $defaultMaxMessages;
        }

        $maxProcesses = (int) Settings::settingValue('binarythreads');

        $groups = DB::select(
            '
            SELECT g.name AS groupname, g.last_record AS our_last,
                a.last_record AS their_last
            FROM usenet_groups g
            INNER JOIN short_groups a ON g.active = 1 AND g.name = a.name
            ORDER BY a.last_record DESC'
        );

        if (empty($groups)) {
            $this->headerNone();

            return;
        }

        $i = 1;
        $queues = [];
        foreach ($groups as $group) {
            if ((int) $group->our_last === 0) {
                $queues[$i] = sprintf('update_group_headers  %s', $group->groupname);
                $i++;
            } else {
                $count = $group->their_last - $group->our_last - 20000; // skip first 20k
                if ($count <= $maxMessages * 2) {
                    $queues[$i] = sprintf('update_group_headers  %s', $group->groupname);
                    $i++;
                } else {
                    $queues[$i] = sprintf('part_repair  %s', $group->groupname);
                    $i++;
                    $getEach = (int) floor(min($count, $maxHeaders) / $maxMessages);
                    $remaining = (int) (min($count, $maxHeaders) - $getEach * $maxMessages);
                    for ($j = 0; $j < $getEach; $j++) {
                        $queues[$i] = sprintf('get_range  binaries  %s  %s  %s  %s', $group->groupname, $group->our_last + $j * $maxMessages + 1, $group->our_last + $j * $maxMessages + $maxMessages, $i);
                        $i++;
                    }
                    if ($remaining > 0) {
                        $start = $group->our_last + $getEach * $maxMessages + 1;
                        $end = $start + $remaining;
                        $queues[$i] = sprintf('get_range  binaries  %s  %s  %s  %s', $group->groupname, $start, $end, $i);
                        $i++;
                    }
                }
            }
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($queues as $queue) {
                $commands[] = $this->buildDnrCommand($queue);
            }
            $this->runStreamingCommands($commands, $maxProcesses, 'safe_binaries');

            return;
        }

        $this->headerStart('safe_binaries', count($queues), $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($queues, max(1, $maxProcesses), true);

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $queue) {
                preg_match('/alt\..+/i', $queue, $hit);
                $command = $this->buildDnrCommand($queue);
                $tasks[$idx] = fn () => ['output' => $this->executeCommand($command), 'group' => $hit[0] ?? ''];
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $result) {
                    if (! empty($result['group'])) {
                        echo $result['output'];
                        $this->colorCli->primary('Updated group '.$result['group']);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Safe binaries batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }
}

