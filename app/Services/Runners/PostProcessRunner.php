<?php

declare(strict_types=1);

namespace App\Services\Runners;

use App\Models\Category;
use App\Models\Settings;
use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use App\Services\NfoService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostProcessRunner extends BaseRunner
{
    private function guidBucketExpression(string $column = 'leftguid'): string
    {
        return DB::getDriverName() === 'sqlite'
            ? 'substr('.$column.', 1, 1)'
            : 'LEFT('.$column.', 1)';
    }

    /**
     * @param  array<int, object{id?: mixed}>  $releases
     */
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
            $this->runStreamingCommands($commands, $maxProcesses, $desc); // @phpstan-ignore argument.type

            return;
        }

        $count = count($releases);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        if ($count <= 1 || $maxProcesses <= 1) {
            foreach ($releases as $release) {
                $char = isset($release->id) ? substr((string) $release->id, 0, 1) : '';
                $command = PHP_BINARY.' artisan postprocess:guid '.$type.' '.$char;
                echo $this->executeCommand($command);
                cli()->primary('Finished task for '.$desc);
            }

            return;
        }

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
                $results = Concurrency::run($tasks, $this->concurrencyTimeout());

                foreach ($results as $taskIdx => $output) {
                    echo $output;
                    cli()->primary('Finished task for '.$desc);
                }
            } catch (\Throwable $e) {
                Log::error('Postprocess batch failed: '.$e->getMessage());
                cli()->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  array<int, object{type: string, id: string}>  $tasks
     */
    private function runPostProcessMixed(array $tasks, int $maxProcesses, string $desc): void
    {
        if ($tasks === []) {
            $this->headerNone();

            return;
        }

        if ((bool) config('nntmux.stream_fork_output', false) === true) {
            $commands = [];
            foreach ($tasks as $task) {
                $char = substr((string) $task->id, 0, 1);
                $commands[] = PHP_BINARY.' artisan postprocess:guid '.$task->type.' '.$char;
            }
            $this->runStreamingCommands($commands, $maxProcesses, $desc); // @phpstan-ignore argument.type

            return;
        }

        $count = count($tasks);
        $this->headerStart('postprocess: '.$desc, $count, $maxProcesses);

        if ($count <= 1 || $maxProcesses <= 1) {
            foreach ($tasks as $task) {
                $char = substr((string) $task->id, 0, 1);
                $command = PHP_BINARY.' artisan postprocess:guid '.$task->type.' '.$char;
                echo $this->executeCommand($command);
                cli()->primary('Finished task for '.$desc);
            }

            return;
        }

        $batches = array_chunk($tasks, max(1, $maxProcesses));

        foreach ($batches as $batchIndex => $batch) {
            $runTasks = [];
            foreach ($batch as $idx => $task) {
                $char = substr((string) $task->id, 0, 1);
                $command = PHP_BINARY.' artisan postprocess:guid '.$task->type.' '.$char;
                $runTasks[$idx] = fn () => $this->executeCommand($command);
            }

            try {
                $results = Concurrency::run($runTasks, $this->concurrencyTimeout());

                foreach ($results as $output) {
                    echo $output;
                    cli()->primary('Finished task for '.$desc);
                }
            } catch (\Throwable $e) {
                Log::error('Postprocess mixed batch failed: '.$e->getMessage());
                cli()->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
            }
        }
    }

    /**
     * @return array<int, object{id: string}>
     */
    private function getBooksBuckets(): array
    {
        $bucketExpr = $this->guidBucketExpression();

        return DB::select('
            SELECT DISTINCT '.$bucketExpr.' AS id
            FROM releases
            WHERE (
                categories_id BETWEEN '.Category::BOOKS_ROOT.' AND '.Category::BOOKS_UNKNOWN.'
                OR categories_id = '.Category::MUSIC_AUDIOBOOK.'
            )
            AND (
                bookinfo_id IS NULL
                OR searchname LIKE "N:/NZB%"
                OR searchname LIKE "N_NZB_%"
                OR name LIKE "N:/NZB%"
                OR name LIKE "N_NZB_%"
            )
            LIMIT 16');
    }

    /**
     * @return array<int, object{id: string}>
     */
    private function getMusicBuckets(): array
    {
        $bucketExpr = $this->guidBucketExpression();

        return DB::select('
            SELECT DISTINCT '.$bucketExpr.' AS id
            FROM releases
            WHERE categories_id IN ('.Category::MUSIC_MP3.', '.Category::MUSIC_LOSSLESS.', '.Category::MUSIC_OTHER.')
            AND musicinfo_id IS NULL
            LIMIT 16');
    }

    /**
     * @return array<int, object{id: string}>
     */
    private function getConsoleBuckets(): array
    {
        $bucketExpr = $this->guidBucketExpression();
        $renamedFilter = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';

        return DB::select('
            SELECT DISTINCT '.$bucketExpr.' AS id
            FROM releases
            WHERE categories_id BETWEEN '.Category::GAME_ROOT.' AND '.Category::GAME_OTHER.'
            AND consoleinfo_id IS NULL
            '.$renamedFilter.'
            LIMIT 16');
    }

    /**
     * @return array<int, object{id: string}>
     */
    private function getGamesBuckets(): array
    {
        $bucketExpr = $this->guidBucketExpression();
        $renamedFilter = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';

        return DB::select('
            SELECT DISTINCT '.$bucketExpr.' AS id
            FROM releases
            WHERE categories_id = '.Category::PC_GAMES.'
            AND gamesinfo_id = 0
            '.$renamedFilter.'
            LIMIT 16');
    }

    public function processAdditional(): void
    {
        // Bucket-selection predicates and size filters are owned by
        // AdditionalCandidateQuery so they cannot drift away from the
        // per-worker fetch in AdditionalProcessingOrchestrator::fetchReleases().
        $chars = AdditionalCandidateQuery::bucketChars();

        // Normalize to the shape the rest of runPostProcess() expects:
        // an array of objects with an `id` (first GUID char) property.
        $queue = array_map(static fn (string $c): object => (object) ['id' => $c], $chars);

        $maxProcesses = (int) Settings::settingValue('postthreads');
        $this->runPostProcess($queue, $maxProcesses, 'additional', 'additional postprocessing');
    }

    public function processNfo(): void
    {
        if ((int) Settings::settingValue('lookupnfo') !== 1) {
            $this->headerNone();

            return;
        }

        $nfoQuery = NfoService::NfoQueryString();

        $checkSql = 'SELECT r.id FROM releases r WHERE 1=1 '.$nfoQuery.' LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $bucketExpr = $this->guidBucketExpression('r.leftguid');
        $sql = '
            SELECT DISTINCT '.$bucketExpr.' AS id
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
            AND '.imdb_id_needs_lookup_sql('imdbid').'
            '.$condLookup.' '.$condRenamedOnly.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $renamedFlag = ($renamedOnly ? 2 : 1);
        $bucketExpr = $this->guidBucketExpression();
        $sql = '
            SELECT DISTINCT '.$bucketExpr.' AS id, '.$renamedFlag.' AS renamed
            FROM releases
            WHERE categories_id BETWEEN 2000 AND 2999
            AND '.imdb_id_needs_lookup_sql('imdbid').'
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
        $bucketExpr = $this->guidBucketExpression();
        $sql = '
            SELECT DISTINCT '.$bucketExpr.' AS id, '.$renamedFlag.' AS renamed
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
     *
     * @param  array<string, mixed>  $releases
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
            $this->runStreamingCommands($commands, $maxProcesses, $desc); // @phpstan-ignore argument.type

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
                $results = Concurrency::run($tasks, $this->concurrencyTimeout());

                foreach ($results as $taskIdx => $output) {
                    echo $output;
                    cli()->primary('Finished task for '.$desc);
                }
            } catch (\Throwable $e) {
                Log::error('TV pipeline batch failed: '.$e->getMessage());
                cli()->error('Batch '.($batchIndex + 1).' failed: '.$e->getMessage());
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

        $bucketExpr = $this->guidBucketExpression();
        $sql = '
            SELECT DISTINCT '.$bucketExpr.' AS id
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
            WHERE (
                categories_id BETWEEN 7000 AND 7999
                OR categories_id = 3030
            )
            AND (
                bookinfo_id IS NULL
                OR searchname LIKE "N:/NZB%"
                OR searchname LIKE "N_NZB_%"
                OR name LIKE "N:/NZB%"
                OR name LIKE "N_NZB_%"
            )
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $bucketExpr = $this->guidBucketExpression();
        $sql = '
            SELECT DISTINCT '.$bucketExpr.' AS id
            FROM releases
            WHERE (
                categories_id BETWEEN 7000 AND 7999
                OR categories_id = 3030
            )
            AND (
                bookinfo_id IS NULL
                OR searchname LIKE "N:/NZB%"
                OR searchname LIKE "N_NZB_%"
                OR name LIKE "N:/NZB%"
                OR name LIKE "N_NZB_%"
            )
            LIMIT 16';
        $queue = DB::select($sql);

        $maxProcesses = (int) Settings::settingValue('postthreadsamazon');
        $this->runPostProcess($queue, $maxProcesses, 'books', 'books postprocessing');
    }

    public function processMusic(): void
    {
        if ((int) Settings::settingValue('lookupmusic') <= 0) {
            $this->headerNone();

            return;
        }

        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id IN ('.Category::MUSIC_MP3.', '.Category::MUSIC_LOSSLESS.', '.Category::MUSIC_OTHER.')
            AND musicinfo_id IS NULL
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $queue = $this->getMusicBuckets();
        $maxProcesses = (int) Settings::settingValue('postthreadsamazon');
        $this->runPostProcess($queue, $maxProcesses, 'music', 'music postprocessing');
    }

    public function processConsoles(): void
    {
        if ((int) Settings::settingValue('lookupgames') <= 0) {
            $this->headerNone();

            return;
        }

        $renamedFilter = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id BETWEEN '.Category::GAME_ROOT.' AND '.Category::GAME_OTHER.'
            AND consoleinfo_id IS NULL
            '.$renamedFilter.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $queue = $this->getConsoleBuckets();
        $maxProcesses = (int) Settings::settingValue('postthreadsamazon');
        $this->runPostProcess($queue, $maxProcesses, 'console', 'console postprocessing');
    }

    public function processGames(): void
    {
        if ((int) Settings::settingValue('lookupgames') <= 0) {
            $this->headerNone();

            return;
        }

        $renamedFilter = (int) Settings::settingValue('lookupgames') === 2 ? 'AND isrenamed = 1' : '';
        $checkSql = '
            SELECT id
            FROM releases
            WHERE categories_id = '.Category::PC_GAMES.'
            AND gamesinfo_id = 0
            '.$renamedFilter.'
            LIMIT 1';
        if (count(DB::select($checkSql)) === 0) {
            $this->headerNone();

            return;
        }

        $queue = $this->getGamesBuckets();
        $maxProcesses = (int) Settings::settingValue('postthreadsamazon');
        $this->runPostProcess($queue, $maxProcesses, 'games', 'games postprocessing');
    }

    public function processAmazon(): void
    {
        $maxProcesses = (int) Settings::settingValue('postthreadsamazon');
        $tasks = [];

        if ((int) Settings::settingValue('lookupbooks') > 0) {
            foreach ($this->getBooksBuckets() as $row) {
                $tasks[] = (object) ['type' => 'books', 'id' => (string) $row->id];
            }
        }

        if ((int) Settings::settingValue('lookupmusic') > 0) {
            foreach ($this->getMusicBuckets() as $row) {
                $tasks[] = (object) ['type' => 'music', 'id' => (string) $row->id];
            }
        }

        if ((int) Settings::settingValue('lookupgames') > 0) {
            foreach ($this->getConsoleBuckets() as $row) {
                $tasks[] = (object) ['type' => 'console', 'id' => (string) $row->id];
            }

            foreach ($this->getGamesBuckets() as $row) {
                $tasks[] = (object) ['type' => 'games', 'id' => (string) $row->id];
            }
        }

        $this->runPostProcessMixed($tasks, $maxProcesses, 'amazon (books+music+console+games)');
    }
}
