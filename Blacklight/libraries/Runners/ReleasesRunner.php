<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use App\Models\UsenetGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Async\Output\SerializableException;

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

        $pool = $this->createPool($maxProcesses);

        $this->headerStart('releases', $count, $maxProcesses);

        $taskNum = $count;
        foreach ($uGroups as $group) {
            $pool->add(function () use ($group) {
                return $this->executeCommand($this->buildDnrCommand('releases  '.$group['id']));
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use (&$taskNum) {
                echo $output;
                $this->colorCli->primary('Task #'.$taskNum.' Finished performing release processing');
                $taskNum--;
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            });
        }

        $pool->wait();
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

        $pool = $this->createPool($maxProcesses);
        $this->headerStart('update_per_group', $count, $maxProcesses);

        foreach ($groups as $group) {
            $pool->add(function () use ($group) {
                return $this->executeCommand($this->buildDnrCommand('update_per_group  '.$group->id));
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use ($group) {
                echo $output;
                $name = UsenetGroup::getNameByID($group->id);
                $this->colorCli->primary('Finished updating binaries, processing releases and additional postprocessing for group:'.$name);
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                echo $serializableException->asThrowable()->getMessage();
            });
        }

        $pool->wait();
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
                $commands[] = PHP_BINARY.' misc/update/tmux/bin/groupfixrelnames.php "'.$queue.'" true';
            }
            $this->runStreamingCommands($commands, $maxThreads, 'fixRelNames_'.$mode);

            return;
        }

        $pool = $this->createPool($maxThreads);

        $this->headerStart('fixRelNames_'.$mode, $count, $maxThreads);

        $taskNum = $count;
        foreach ($queues as $queue) {
            $pool->add(function () use ($queue) {
                return $this->executeCommand(PHP_BINARY.' misc/update/tmux/bin/groupfixrelnames.php "'.$queue.'" true');
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use (&$taskNum) {
                echo $output;
                $this->colorCli->primary('Task #'.$taskNum.' Finished fixing releases names');
                $taskNum--;
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            });
        }

        $pool->wait();
    }
}
