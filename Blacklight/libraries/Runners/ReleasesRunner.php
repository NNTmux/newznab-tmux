<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use App\Models\UsenetGroup;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleasesRunner extends BaseRunner
{
    public function releases(): void
    {
        $groups = DB::select('SELECT id, name FROM usenet_groups WHERE (active = 1 OR backfill = 1)');
        $maxProcesses = (int) Settings::settingValue('releasethreads');

        $uGroups = [];
        foreach ($groups as $group) {
            try {
                $query = DB::select(sprintf('SELECT id FROM collections WHERE groups_id = %d LIMIT 1', $group->id));
                if (! empty($query)) {
                    $uGroups[] = ['id' => $group->id, 'name' => $group->name];
                }
            } catch (\PDOException $e) {
                if (config('app.debug') === true) {
                    Log::debug($e->getMessage());
                }
            }
        }

        $count = count($uGroups);
        if ($count === 0) {
            $this->headerNone();

            return;
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($uGroups as $group) {
                $commands[] = $this->buildDnrCommand('releases  '.$group['id']);
            }
            $this->runStreamingCommands($commands, $maxProcesses, 'releases');

            return;
        }

        $this->headerStart('releases', $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($uGroups, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $group) {
                $command = $this->buildDnrCommand('releases  '.$group['id']);
                $tasks[$group['id']] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $groupId => $output) {
                    echo $output;
                    $this->colorCli->primary('Finished performing release processing for group ID: '.$groupId);
                }
            } catch (\Throwable $e) {
                Log::error('Release processing batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    public function updatePerGroup(): void
    {
        $groups = DB::select('SELECT id , name FROM usenet_groups WHERE (active = 1 OR backfill = 1)');
        $maxProcesses = (int) Settings::settingValue('releasethreads');

        $count = count($groups);
        if ($count === 0) {
            $this->headerNone();

            return;
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($groups as $group) {
                $commands[] = $this->buildDnrCommand('update_per_group  '.$group->id);
            }
            $this->runStreamingCommands($commands, $maxProcesses, 'update_per_group');

            return;
        }

        $this->headerStart('update_per_group', $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($groups, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $group) {
                $command = $this->buildDnrCommand('update_per_group  '.$group->id);
                $tasks[$group->id] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $groupId => $output) {
                    echo $output;
                    $name = UsenetGroup::getNameByID($groupId);
                    $this->colorCli->primary('Finished updating binaries, processing releases and additional postprocessing for group: '.$name);
                }
            } catch (\Throwable $e) {
                Log::error('Update per group batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    public function fixRelNames(string $mode, int $maxPerRun, int $maxThreads): void
    {
        $maxThreads = max(1, min(16, $maxThreads));

        $leftGuids = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];

        if ($mode === 'predbft') {
            $preCount = DB::select(
                "SELECT COUNT(p.id) AS num FROM predb p WHERE LENGTH(p.title) >= 15 AND p.title NOT REGEXP '[\"\<\> ]' AND p.searched = 0 AND p.predate < (NOW() - INTERVAL 1 DAY)"
            );
            if (! empty($preCount) && (int) $preCount[0]->num > 0 && $maxPerRun > 0) {
                $leftGuids = \array_slice($leftGuids, 0, (int) ceil($preCount[0]->num / $maxPerRun));
            } else {
                $leftGuids = [];
            }
        }

        $queues = [];
        $idx = 0;
        foreach ($leftGuids as $leftGuid) {
            if ($maxPerRun > 0) {
                $idx++;
                $queues[$idx] = sprintf('%s %s %s %s', $mode, $leftGuid, $maxPerRun, $idx);
            }
        }

        $count = count($queues);
        if ($count === 0) {
            $this->headerNone();

            return;
        }

        // Streaming mode
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($queues as $queue) {
                // Updated to use new script location (modernized)
                $commands[] = PHP_BINARY.' app/Services/Tmux/Scripts/groupfixrelnames.php "'.$queue.'" true';
            }
            $this->runStreamingCommands($commands, $maxThreads, 'fixRelNames_'.$mode);

            return;
        }

        $this->headerStart('fixRelNames_'.$mode, $count, $maxThreads);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($queues, max(1, $maxThreads), true);

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $queue) {
                // Updated to use new script location (modernized)
                $command = PHP_BINARY.' app/Services/Tmux/Scripts/groupfixrelnames.php "'.$queue.'" true';
                $tasks[$idx] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $taskIdx => $output) {
                    echo $output;
                    $this->colorCli->primary('Task #'.$taskIdx.' Finished fixing releases names');
                }
            } catch (\Throwable $e) {
                Log::error('Fix rel names batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }
}
