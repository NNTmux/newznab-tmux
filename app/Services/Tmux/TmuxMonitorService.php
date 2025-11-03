<?php

namespace App\Services\Tmux;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\Tmux;
use Illuminate\Support\Facades\DB;

/**
 * Service for monitoring tmux operations and collecting statistics
 */
class TmuxMonitorService
{
    protected Tmux $tmux;

    protected array $runVar = [];

    protected int $iterations = 1;

    protected bool $shouldContinue = true;

    public function __construct()
    {
        $this->tmux = new Tmux;
    }

    /**
     * Initialize monitor with default values
     */
    public function initializeMonitor(): array
    {
        $this->runVar['paths']['misc'] = base_path().'/misc/';
        $this->runVar['paths']['cli'] = base_path().'/cli/';

        $this->runVar['constants'] = $this->tmux->getConstantSettings();
        $this->runVar['settings'] = $this->tmux->getMonitorSettings();
        $this->runVar['connections'] = $this->tmux->getConnectionsInfo($this->runVar['constants']);

        // Initialize timers
        $this->runVar['timers'] = $this->initializeTimers();

        // Initialize counts
        $this->runVar['counts'] = [
            'iterations' => 1,
            'now' => [],
            'start' => [],
            'diff' => [],
            'percent' => [],
        ];

        // Parse fix_crap setting into an array
        $fixCrapSetting = $this->runVar['settings']['fix_crap'] ?? '';
        $fixCrapTypes = ! empty($fixCrapSetting)
            ? (is_array($fixCrapSetting) ? $fixCrapSetting : explode(',', $fixCrapSetting))
            : [];
        $fixCrapTypes = array_filter($fixCrapTypes);

        $this->runVar['modsettings'] = [
            'fix_crap' => $fixCrapTypes,
            'fc' => [
                'num' => 0,
                'max' => count($fixCrapTypes),
                'time' => 'full',
                'firstrun' => true,
            ],
        ];

        return $this->runVar;
    }

    /**
     * Initialize all timers
     */
    protected function initializeTimers(): array
    {
        $now = time();

        return [
            'timer1' => $now,
            'timer2' => $now,
            'timer3' => $now,
            'timer4' => $now,
            'timer5' => $now,
            'query' => [
                'tmux_time' => 0,
                'split_time' => 0,
                'init_time' => 0,
                'proc1_time' => 0,
                'proc2_time' => 0,
                'proc3_time' => 0,
                'split1_time' => 0,
                'init1_time' => 0,
                'proc11_time' => 0,
                'proc21_time' => 0,
                'proc31_time' => 0,
                'tpg_time' => 0,
                'tpg1_time' => 0,
            ],
            'newOld' => [
                'newestrelname' => '',
                'oldestcollection' => 0,
                'newestpre' => 0,
                'newestrelease' => 0,
            ],
        ];
    }

    /**
     * Collect current statistics
     */
    public function collectStatistics(): array
    {
        // Refresh settings periodically
        $monitorDelay = (int) ($this->runVar['settings']['monitor'] ?? 60);
        $timeSinceLastRefresh = time() - ($this->runVar['timers']['timer2'] ?? 0);

        if ($this->iterations === 1 || $timeSinceLastRefresh >= $monitorDelay) {
            $this->refreshStatistics();
            $this->runVar['timers']['timer2'] = time();
        }

        // Update connection counts
        $this->updateConnectionCounts();

        // Set killswitches
        $this->setKillswitches();

        return $this->runVar;
    }

    /**
     * Refresh all statistics from database
     */
    protected function refreshStatistics(): void
    {
        $timer = time();

        // Refresh settings
        $this->runVar['settings'] = $this->tmux->getMonitorSettings();
        $this->runVar['timers']['query']['tmux_time'] = time() - $timer;

        // Get category counts
        $this->getCategoryCounts();

        // Get process counts
        $this->getProcessCounts();

        // Get table counts
        $this->getTableCounts();

        // Calculate diffs and percentages
        $this->calculateStatistics();
    }

    /**
     * Get counts by category
     */
    protected function getCategoryCounts(): void
    {
        $timer = time();

        $this->runVar['counts']['now']['tv'] = Release::query()
            ->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])
            ->count('id');

        $this->runVar['counts']['now']['movies'] = Release::query()
            ->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])
            ->count('id');

        $this->runVar['counts']['now']['audio'] = Release::query()
            ->whereBetween('categories_id', [Category::MUSIC_ROOT, Category::MUSIC_OTHER])
            ->count('id');

        $this->runVar['counts']['now']['books'] = Release::query()
            ->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])
            ->count('id');

        $this->runVar['counts']['now']['console'] = Release::query()
            ->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])
            ->count('id');

        $this->runVar['counts']['now']['pc'] = Release::query()
            ->whereBetween('categories_id', [Category::PC_ROOT, Category::PC_PHONE_ANDROID])
            ->count('id');

        $this->runVar['counts']['now']['xxx'] = Release::query()
            ->whereBetween('categories_id', [Category::XXX_ROOT, Category::XXX_OTHER])
            ->count('id');

        $this->runVar['counts']['now']['misc'] = Release::query()
            ->whereBetween('categories_id', [Category::OTHER_ROOT, Category::OTHER_HASHED])
            ->count('id');

        $this->runVar['timers']['query']['init_time'] = time() - $timer;
    }

    /**
     * Get process-related counts
     */
    protected function getProcessCounts(): void
    {
        $timer = time();

        try {
            $bookReqIds = $this->runVar['settings']['book_reqids'] ?? Category::BOOKS_ROOT;
            $dbName = config('nntmux.db_name');

            $proc1Query = $this->tmux->proc_query(1, $bookReqIds, $dbName);
            $proc1Result = DB::selectOne($proc1Query);

            if ($proc1Result) {
                foreach ((array) $proc1Result as $key => $value) {
                    $this->runVar['counts']['now'][$key] = $value;
                }
            }

            $this->runVar['timers']['query']['proc1_time'] = time() - $timer;

            // Process 2
            $timer2 = time();
            $maxSize = $this->runVar['settings']['maxsize_pp'] ?? '';
            $minSize = $this->runVar['settings']['minsize_pp'] ?? '';

            $proc2Query = $this->tmux->proc_query(2, $bookReqIds, $dbName, $maxSize, $minSize);
            $proc2Result = DB::selectOne($proc2Query);

            if ($proc2Result) {
                foreach ((array) $proc2Result as $key => $value) {
                    $this->runVar['counts']['now'][$key] = $value;
                }
            }

            $this->runVar['timers']['query']['proc2_time'] = time() - $timer2;

        } catch (\Exception $e) {
            logger()->error('Error collecting process counts: '.$e->getMessage());
        }
    }

    /**
     * Get table row counts
     */
    protected function getTableCounts(): void
    {
        $timer = time();

        try {
            $this->runVar['counts']['now']['collections_table'] = Collection::query()->count();

            // Get binaries/parts counts from information_schema or use approximation
            $dbName = config('nntmux.db_name');
            $tables = $this->tmux->cbpmTableQuery();

            $this->runVar['counts']['now']['binaries_table'] = 0;
            $this->runVar['counts']['now']['parts_table'] = 0;
            $this->runVar['counts']['now']['missed_parts_table'] = 0;

            foreach ($tables as $table) {
                $tableName = $table->name;
                $count = $this->getTableRowCount($tableName);

                if (str_contains($tableName, 'binaries')) {
                    $this->runVar['counts']['now']['binaries_table'] += $count;
                } elseif (str_contains($tableName, 'missed_parts')) {
                    $this->runVar['counts']['now']['missed_parts_table'] += $count;
                } elseif (str_contains($tableName, 'parts')) {
                    $this->runVar['counts']['now']['parts_table'] += $count;
                }
            }

            $this->runVar['timers']['query']['tpg_time'] = time() - $timer;

            // Get additional table counts (query 4)
            $timer4 = time();
            $bookReqIds = $this->runVar['settings']['book_reqids'] ?? Category::BOOKS_ROOT;
            $proc4Query = $this->tmux->proc_query(4, $bookReqIds, $dbName);
            $proc4Result = DB::selectOne($proc4Query);

            if ($proc4Result) {
                foreach ((array) $proc4Result as $key => $value) {
                    $this->runVar['counts']['now'][$key] = $value;
                }
            }

            // Get newest/oldest data (query 6)
            $timer6 = time();
            $proc6Query = $this->tmux->proc_query(6, $bookReqIds, $dbName);
            $proc6Result = DB::selectOne($proc6Query);

            if ($proc6Result) {
                foreach ((array) $proc6Result as $key => $value) {
                    $this->runVar['timers']['newOld'][$key] = $value;
                }
            }

        } catch (\Exception $e) {
            logger()->error('Error collecting table counts: '.$e->getMessage());
        }
    }

    /**
     * Get row count for a table
     */
    protected function getTableRowCount(string $tableName): int
    {
        try {
            $result = DB::selectOne(
                'SELECT TABLE_ROWS AS count FROM information_schema.TABLES
                 WHERE TABLE_NAME = ? AND TABLE_SCHEMA = DATABASE()',
                [$tableName]
            );

            return (int) ($result->count ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate statistics (diffs, percentages, totals)
     */
    protected function calculateStatistics(): void
    {
        // Calculate total work
        $this->runVar['counts']['now']['total_work'] = 0;

        foreach ($this->runVar['counts']['now'] as $key => $value) {
            if (str_starts_with($key, 'process')) {
                $this->runVar['counts']['now']['total_work'] += $value;
            }
        }

        // Set initial start values on first iteration
        if ($this->iterations === 1) {
            $this->runVar['counts']['start'] = $this->runVar['counts']['now'];
        }

        // Calculate diffs and percentages
        $totalReleases = $this->runVar['counts']['now']['releases'] ?? 1;

        foreach ($this->runVar['counts']['now'] as $key => $value) {
            $startValue = $this->runVar['counts']['start'][$key] ?? 0;
            $this->runVar['counts']['diff'][$key] = number_format($value - $startValue);
            $this->runVar['counts']['percent'][$key] = $totalReleases > 0
                ? sprintf('%02d', floor(($value / $totalReleases) * 100))
                : 0;
        }
    }

    /**
     * Update connection counts
     */
    protected function updateConnectionCounts(): void
    {
        $this->runVar['conncounts'] = $this->tmux->getUSPConnections(
            'primary',
            $this->runVar['connections']
        );

        if ((int) ($this->runVar['constants']['alternate_nntp'] ?? 0) === 1) {
            $alternateConns = $this->tmux->getUSPConnections(
                'alternate',
                $this->runVar['connections']
            );
            $this->runVar['conncounts'] = array_merge($this->runVar['conncounts'], $alternateConns);
        }
    }

    /**
     * Set killswitches based on limits
     */
    protected function setKillswitches(): void
    {
        $ppKillLimit = (int) ($this->runVar['settings']['postprocess_kill'] ?? 0);
        $collKillLimit = (int) ($this->runVar['settings']['collections_kill'] ?? 0);

        $totalWork = (int) ($this->runVar['counts']['now']['total_work'] ?? 0);
        $collections = (int) ($this->runVar['counts']['now']['collections_table'] ?? 0);

        $this->runVar['killswitch']['pp'] = ($ppKillLimit > 0 && $ppKillLimit < $totalWork);
        $this->runVar['killswitch']['coll'] = ($collKillLimit > 0 && $collKillLimit < $collections);
    }

    /**
     * Update the monitor display
     */
    public function updateDisplay(): void
    {
        // This would output to terminal - implementation depends on TmuxOutput
        // For now, we'll just log basic info
        if ($this->iterations % 10 === 0) {
            logger()->info('Tmux Monitor', [
                'iteration' => $this->iterations,
                'total_work' => $this->runVar['counts']['now']['total_work'] ?? 0,
                'collections' => $this->runVar['counts']['now']['collections_table'] ?? 0,
            ]);
        }
    }

    /**
     * Increment iteration counter
     */
    public function incrementIteration(): void
    {
        $this->iterations++;
        $this->runVar['counts']['iterations'] = $this->iterations;
    }

    /**
     * Check if monitoring should continue
     */
    public function shouldContinue(): bool
    {
        $exitFlag = (int) Settings::settingValue('exit');

        if ($exitFlag === 0) {
            return true;
        }

        $this->shouldContinue = false;

        return false;
    }

    /**
     * Get current run variables
     */
    public function getRunVar(): array
    {
        return $this->runVar;
    }
}
