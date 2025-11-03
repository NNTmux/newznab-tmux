<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Spatie\Async\Output\SerializableException;

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

        $pool = $this->createPool($maxProcesses);

        $this->headerStart('binaries', $count, $maxProcesses);

        $taskNum = $count;
        foreach ($work as $group) {
            $pool->add(function () use ($group) {
                return $this->executeCommand(PHP_BINARY.' artisan update:binaries '.$group->name.' '.$group->max);
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use ($group, &$taskNum) {
                echo $output;
                $this->colorCli->primary('Task #'.$taskNum.' Updated group '.$group->name);
                $taskNum--;
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            });
        }

        $pool->wait();
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

        $pool = $this->createPool($maxProcesses);
        $this->headerStart('safe_binaries', count($queues), $maxProcesses);

        foreach ($queues as $queue) {
            preg_match('/alt\..+/i', $queue, $hit);
            $pool->add(function () use ($queue) {
                return $this->executeCommand($this->buildDnrCommand($queue));
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use ($hit) {
                if (! empty($hit)) {
                    echo $output;
                    $this->colorCli->primary('Updated group '.$hit[0]);
                }
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            });
        }

        $pool->wait();
    }
}
