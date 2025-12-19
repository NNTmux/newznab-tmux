<?php

namespace App\Services\Runners;

use App\Models\Settings;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $count = count($releases);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($releases, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $release) {
                $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
                // Use postprocess:guid command which accepts the GUID character
                $command = PHP_BINARY.' artisan postprocess:guid '.$type.' '.$char;
                $tasks[$idx] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $taskIdx => $output) {
                    echo $output;
                    $this->colorCli->primary('Finished task for '.$desc);
                }
            } catch (\Throwable $e) {
                Log::error('Postprocess batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
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

        // Use pipelined TV processing for better efficiency
        $this->runPostProcessTvPipeline($queue, $maxProcesses, 'tv postprocessing (pipelined)', $renamedOnly);
    }

    /**
     * Run pipelined TV post-processing across multiple GUID buckets in parallel.
     * Each parallel process runs the full provider pipeline sequentially.
     */
    private function runPostProcessTvPipeline(array $releases, int $maxProcesses, string $desc, bool $renamedOnly): void
    {
        if (empty($releases)) {
            $this->headerNone();

            return;
        }

        // If streaming is enabled, run commands with real-time output
        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($releases as $release) {
                $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
                $renamed = isset($release->renamed) ? $release->renamed : '';
                // Use the pipelined TV command
                $commands[] = PHP_BINARY.' artisan postprocess:tv-pipeline '.$char.($renamed ? ' '.$renamed : '').' --mode=pipeline';
            }
            $this->runStreamingCommands($commands, $maxProcesses, $desc);

            return;
        }

        $count = count($releases);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        // Process in batches using Laravel's native Concurrency facade
        $batches = array_chunk($releases, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $tasks = [];
            foreach ($batch as $idx => $release) {
                $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
                $renamed = isset($release->renamed) ? $release->renamed : '';
                // Use the pipelined TV command for each GUID bucket
                $command = PHP_BINARY.' artisan postprocess:tv-pipeline '.$char.($renamed ? ' '.$renamed : '').' --mode=pipeline';
                $tasks[$idx] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($tasks);

                foreach ($results as $taskIdx => $output) {
                    echo $output;
                    $this->colorCli->primary('Finished task for '.$desc);
                }
            } catch (\Throwable $e) {
                Log::error('TV pipeline batch failed: '.$e->getMessage());
                $this->colorCli->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
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

    public function processAnime(): void
    {
        if ((int) Settings::settingValue('lookupanidb') <= 0) {
            $this->headerNone();

            return;
        }

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id = 5070
            AND anidbid IS NULL
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $sql = '
            SELECT DISTINCT LEFT(leftguid, 1) AS id
            FROM releases
            WHERE categories_id = 5070
            AND anidbid IS NULL
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'anime', 'anime postprocessing');
    }

    public function processBooks(): void
    {
        if ((int) Settings::settingValue('lookupbooks') <= 0) {
            $this->headerNone();

            return;
        }

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN 7000 AND 7999
            AND bookinfo_id IS NULL
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $sql = '
            SELECT DISTINCT LEFT(leftguid, 1) AS id
            FROM releases
            WHERE categories_id BETWEEN 7000 AND 7999
            AND bookinfo_id IS NULL
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsnon');
        $this->runPostProcess($queue, $maxProcesses, 'books', 'books postprocessing');
    }
}

