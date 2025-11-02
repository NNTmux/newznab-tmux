<?php

namespace Blacklight\libraries\Runners;

use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Spatie\Async\Output\SerializableException;

class PostProcessRunner extends BaseRunner
{
    private function runPostProcess(array $releases, int $maxProcesses, string $type, string $desc): void
    {
        if (empty($releases)) {
            $this->headerNone();

            return;
        }

        // If streaming is enabled, run commands with real-time output
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($releases as $release) {
                // id may already be a single GUID bucket char; if not, take first char defensively
                $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
                // Use postprocess:guid command which accepts the GUID character
                $commands[] = PHP_BINARY.' artisan postprocess:guid '.$type.' '.$char;
            }
            $this->runStreamingCommands($commands, $maxProcesses, $desc);

            return;
        }

        $pool = $this->createPool($maxProcesses);
        $count = count($releases);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        foreach ($releases as $release) {
            $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
            $pool->add(function () use ($char, $type) {
                // Use postprocess:guid command which accepts the GUID character
                return $this->executeCommand(PHP_BINARY.' artisan postprocess:guid '.$type.' '.$char);
            }, self::ASYNC_BUFFER_SIZE)->then(function ($output) use (&$count, $desc) {
                echo $output;
                $this->colorCli->primary('Finished task #'.$count.' for '.$desc);
                $count--;
            })->catch(function (\Throwable $exception) {
                echo $exception->getMessage();
            })->catch(static function (SerializableException $serializableException) {
                // swallow
            })->timeout(function () use ($desc, &$count) {
                $this->colorCli->notice('Task #'.$count.' ('.$desc.'): Timeout occurred.');
            });
        }

        $pool->wait();
    }

    public function processAdditional(): void
    {
        $ppAddMinSize = Settings::settingValue('minsizetopostprocess') !== '' ? (int) Settings::settingValue('minsizetopostprocess') : 1;
        $ppAddMinSize = ($ppAddMinSize > 0 ? ('AND r.size > '.($ppAddMinSize * 1048576)) : '');
        $ppAddMaxSize = (Settings::settingValue('maxsizetopostprocess') !== '') ? (int) Settings::settingValue('maxsizetopostprocess') : 100;
        $ppAddMaxSize = ($ppAddMaxSize > 0 ? ('AND r.size < '.($ppAddMaxSize * 1073741824)) : '');

        $sql = '
            SELECT DISTINCT LEFT(r.leftguid, 1) AS id
            FROM releases r
            LEFT JOIN categories c ON c.id = r.categories_id
            WHERE r.passwordstatus = -1
            AND r.haspreview = -1
            AND c.disablepreview = 0
            '.$ppAddMaxSize.' '.$ppAddMinSize.'
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreads');
        $this->runPostProcess($queue, $maxProcesses, 'additional', 'additional postprocessing');
    }

    public function processNfo(): void
    {
        if ((int) Settings::settingValue('lookupnfo') !== 1) {
            $this->headerNone();

            return;
        }

        $nfoQuery = \Blacklight\Nfo::NfoQueryString();

        $checkSql = 'SELECT r.id FROM releases r WHERE 1=1 '.$nfoQuery.' LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $sql = '
            SELECT DISTINCT LEFT(r.leftguid, 1) AS id
            FROM releases r
            WHERE 1=1 '.$nfoQuery.'
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('nfothreads');
        $this->runPostProcess($queue, $maxProcesses, 'nfo', 'nfo postprocessing');
    }

    public function processMovies(bool $renamedOnly): void
    {
        if ((int) Settings::settingValue('lookupimdb') <= 0) {
            $this->headerNone();

            return;
        }

        $condLookup = ((int) Settings::settingValue('lookupimdb') === 2 ? 'AND isrenamed = 1' : '');
        $condRenamedOnly = ($renamedOnly ? 'AND isrenamed = 1' : '');

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 2000 AND 2999
            AND imdbid IS NULL
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $renamedFlag = ($renamedOnly ? 2 : 1);
        $sql = '
            SELECT DISTINCT LEFT(leftguid, 1) AS id, '.$renamedFlag.' AS renamed
            FROM releases
            WHERE categories_id BETWEEN 2000 AND 2999
            AND imdbid IS NULL
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'movie', 'movies postprocessing');
    }

    public function processTv(bool $renamedOnly): void
    {
        if ((int) Settings::settingValue('lookuptv') <= 0) {
            $this->headerNone();

            return;
        }

        $condLookup = ((int) Settings::settingValue('lookuptv') === 2 ? 'AND isrenamed = 1' : '');
        $condRenamedOnly = ($renamedOnly ? 'AND isrenamed = 1' : '');

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 5000 AND 5999
            AND categories_id != 5070
            AND videos_id = 0
            AND size > 1048576
            AND tv_episodes_id BETWEEN -3 AND 0
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $renamedFlag = ($renamedOnly ? 2 : 1);
        $sql = '
            SELECT DISTINCT LEFT(leftguid, 1) AS id, '.$renamedFlag.' AS renamed
            FROM releases
            WHERE categories_id BETWEEN 5000 AND 5999
            AND categories_id != 5070
            AND videos_id = 0
            AND tv_episodes_id BETWEEN -3 AND 0
            AND size > 1048576
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'tv', 'tv postprocessing');
    }

    /**
     * Lightweight check to determine if there is any TV work to process.
     */
    public function hasTvWork(bool $renamedOnly): bool
    {
        if ((int) Settings::settingValue('lookuptv') <= 0) {
            return false;
        }

        $condLookup = ((int) Settings::settingValue('lookuptv') === 2 ? 'AND isrenamed = 1' : '');
        $condRenamedOnly = ($renamedOnly ? 'AND isrenamed = 1' : '');

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 5000 AND 5999
            AND categories_id != 5070
            AND videos_id = 0
            AND size > 1048576
            AND tv_episodes_id BETWEEN -3 AND 0
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 1';

        return count(DB::select($checkSql)) > 0;
    }
}
