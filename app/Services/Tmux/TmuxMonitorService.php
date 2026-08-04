<?php

declare(strict_types=1);

namespace App\Services\Tmux;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Release;
use App\Models\Settings;
use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Support\Facades\DB;

/**
 * Service for monitoring tmux operations and collecting statistics
 */
class TmuxMonitorService
{
    protected Tmux $tmux;

    /**
     * @var array<string, mixed>
     */
    protected array $runVar = [];

    protected int $iterations = 1;

    protected bool $shouldContinue = true;

    protected float $lastOperationalRefreshAt = 0.0;

    protected float $lastSlowRefreshAt = 0.0;

    public function __construct()
    {
        $this->tmux = new Tmux;
    }

    /**
     * Initialize monitor with default values
     *
     * @return array<string, mixed>
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
            'now' => $this->defaultCountValues(),
            'start' => $this->defaultCountValues(),
            'diff' => $this->defaultDiffValues(),
            'percent' => $this->defaultPercentValues(),
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
     *
     * @return array<string, mixed>
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
     *
     * @return array<string, mixed>
     */
    public function collectStatistics(): array
    {
        $now = microtime(true);
        $monitorDelay = max(1, (int) ($this->runVar['settings']['monitor'] ?? 60));
        $slowRefreshDelay = max($monitorDelay, (int) config('tmux.monitor.refresh_interval', 60));
        $statisticsChanged = false;

        if ($this->refreshIsDue($this->lastOperationalRefreshAt, $monitorDelay, $now)) {
            $this->refreshOperationalStatistics();
            $this->lastOperationalRefreshAt = $now;
            $this->runVar['timers']['timer2'] = (int) $now;
            $statisticsChanged = true;
        }

        if ($this->refreshIsDue($this->lastSlowRefreshAt, $slowRefreshDelay, $now)) {
            $this->refreshSlowStatistics();
            $this->lastSlowRefreshAt = $now;
            $statisticsChanged = true;
        }

        if ($statisticsChanged) {
            $this->calculateStatistics();
        }

        $this->updateConnectionCounts();
        $this->setKillswitches();

        return $this->runVar;
    }

    protected function refreshOperationalStatistics(): void
    {
        $timer = microtime(true);
        $this->runVar['settings'] = $this->tmux->getMonitorSettings();
        $this->runVar['timers']['query']['tmux_time'] = microtime(true) - $timer;

        $this->getProcessCounts();
    }

    protected function refreshSlowStatistics(): void
    {
        $this->getCategoryCounts();
        $this->getTableCounts();
    }

    protected function refreshIsDue(float $lastRefreshAt, int $interval, float $now): bool
    {
        return $lastRefreshAt === 0.0 || ($now - $lastRefreshAt) >= $interval;
    }

    /**
     * Get counts by category
     */
    protected function getCategoryCounts(): void
    {
        $timer = microtime(true);
        $bindings = [];
        $aggregates = [];

        foreach ($this->categoryRanges() as $name => [$minimum, $maximum]) {
            $aggregates[] = "SUM(CASE WHEN categories_id BETWEEN ? AND ? THEN 1 ELSE 0 END) AS {$name}";
            $bindings[] = $minimum;
            $bindings[] = $maximum;
        }

        try {
            $counts = Release::query()->selectRaw(implode(', ', $aggregates), $bindings)->first();

            if ($counts !== null) {
                foreach (array_keys($this->categoryRanges()) as $name) {
                    $this->runVar['counts']['now'][$name] = (int) $counts->getAttribute($name);
                }
            }
        } catch (\Exception $e) {
            logger()->error('Error collecting category counts: '.$e->getMessage());
        }

        $this->runVar['timers']['query']['init_time'] = microtime(true) - $timer;
    }

    /**
     * @return array<string, array{int, int}>
     */
    protected function categoryRanges(): array
    {
        return [
            'tv' => [Category::TV_ROOT, Category::TV_OTHER],
            'movies' => [Category::MOVIE_ROOT, Category::MOVIE_OTHER],
            'audio' => [Category::MUSIC_ROOT, Category::MUSIC_OTHER],
            'books' => [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN],
            'console' => [Category::GAME_ROOT, Category::GAME_OTHER],
            'pc' => [Category::PC_ROOT, Category::PC_PHONE_ANDROID],
            'xxx' => [Category::XXX_ROOT, Category::XXX_OTHER],
            'misc' => [Category::OTHER_ROOT, Category::OTHER_HASHED],
        ];
    }

    /**
     * Get process-related counts
     */
    protected function getProcessCounts(): void
    {
        $timer = microtime(true);
        $this->runVar['counts']['now']['work'] = $this->runVar['counts']['now']['work'] ?? 0;
        $this->runVar['counts']['now']['work_available'] = $this->runVar['counts']['now']['work_available'] ?? 0;

        try {
            $dbName = config('nntmux.db_name');

            $proc1Query = $this->tmux->proc_query(1, $dbName, '');
            $proc1Result = DB::selectOne($proc1Query);

            if ($proc1Result) {
                foreach ((array) $proc1Result as $key => $value) {
                    $this->runVar['counts']['now'][$key] = $value;
                }
            }

            $this->runVar['timers']['query']['proc1_time'] = microtime(true) - $timer;

            // Process 2
            $timer2 = microtime(true);
            $maxSize = $this->runVar['settings']['maxsize_pp'] ?? '';
            $minSize = $this->runVar['settings']['minsize_pp'] ?? '';

            $proc2Query = $this->tmux->proc_query(2, $dbName, (string) $maxSize, (string) $minSize);
            $proc2Result = DB::selectOne($proc2Query);

            if ($proc2Result) {
                foreach ((array) $proc2Result as $key => $value) {
                    $this->runVar['counts']['now'][$key] = $value;
                }
            }

            // `work` remains the visible additional-processing backlog, including
            // rows claimed by active workers. `work_available` is the scheduler
            // gate, excluding fresh claims so tmux does not respawn duplicate
            // additional workers while a claimed batch is still running.
            $additionalBacklog = AdditionalCandidateQuery::backlogCounts();
            $this->runVar['counts']['now']['work'] = $additionalBacklog['total'];
            $this->runVar['counts']['now']['work_available'] = $additionalBacklog['available'];

            $this->runVar['timers']['query']['proc2_time'] = microtime(true) - $timer2;

        } catch (\Exception $e) {
            logger()->error('Error collecting process counts: '.$e->getMessage());
        }
    }

    /**
     * Get table row counts
     */
    protected function getTableCounts(): void
    {
        $timer = microtime(true);

        try {
            $this->runVar['counts']['now']['collections_table'] = Collection::query()->count();
            $this->runVar['counts']['now']['releases'] = Release::query()->count();

            foreach ($this->aggregateTableRowEstimates($this->tmux->cbpmTableQuery()) as $key => $count) {
                $this->runVar['counts']['now'][$key] = $count;
            }

            $this->runVar['timers']['query']['tpg_time'] = microtime(true) - $timer;

            foreach ([4, 6] as $queryNumber) {
                $result = DB::selectOne($this->tmux->proc_query($queryNumber, (string) config('nntmux.db_name'), ''));
                if ($result === null) {
                    continue;
                }

                $target = $queryNumber === 4 ? 'counts' : 'timers';
                $section = $queryNumber === 4 ? 'now' : 'newOld';
                foreach ((array) $result as $key => $value) {
                    $this->runVar[$target][$section][$key] = $value;
                }
            }
        } catch (\Exception $e) {
            logger()->error('Error collecting table counts: '.$e->getMessage());
        }
    }

    /**
     * @param  array<array-key, mixed>  $tables
     * @return array{binaries_table: int, parts_table: int, missed_parts_table: int}
     */
    protected function aggregateTableRowEstimates(array $tables): array
    {
        $counts = [
            'binaries_table' => 0,
            'parts_table' => 0,
            'missed_parts_table' => 0,
        ];

        foreach ($tables as $table) {
            if (! is_object($table)) {
                continue;
            }

            $tableName = (string) ($table->name ?? '');
            $count = (int) ($table->row_count ?? 0);

            if (str_contains($tableName, 'binaries')) {
                $counts['binaries_table'] += $count;
            } elseif (str_contains($tableName, 'missed_parts')) {
                $counts['missed_parts_table'] += $count;
            } elseif (str_contains($tableName, 'parts')) {
                $counts['parts_table'] += $count;
            }
        }

        return $counts;
    }

    /**
     * Calculate statistics (diffs, percentages, totals)
     */
    protected function calculateStatistics(): void
    {
        $this->runVar['counts']['now'] = array_replace($this->defaultCountValues(), $this->runVar['counts']['now'] ?? []);
        $this->runVar['counts']['start'] = array_replace($this->defaultCountValues(), $this->runVar['counts']['start'] ?? []);
        $this->runVar['counts']['diff'] = array_replace($this->defaultDiffValues(), $this->runVar['counts']['diff'] ?? []);
        $this->runVar['counts']['percent'] = array_replace($this->defaultPercentValues(), $this->runVar['counts']['percent'] ?? []);

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

        // Calculate diffs
        foreach ($this->runVar['counts']['now'] as $key => $value) {
            $startValue = (int) ($this->runVar['counts']['start'][$key] ?? 0);
            $this->runVar['counts']['diff'][$key] = number_format((int) $value - $startValue);
        }

        // Calculate percentages for category counts (as % of total categorized releases)
        $categoryKeys = ['tv', 'movies', 'audio', 'books', 'console', 'pc', 'xxx', 'misc'];

        // Sum all category counts to get the true total
        $totalCategorized = 0;
        foreach ($categoryKeys as $key) {
            $totalCategorized += (int) ($this->runVar['counts']['now'][$key] ?? 0);
        }

        foreach ($categoryKeys as $key) {
            $value = (int) ($this->runVar['counts']['now'][$key] ?? 0);
            $this->runVar['counts']['percent'][$key] = $totalCategorized > 0
                ? sprintf('%02d', (int) floor(($value / $totalCategorized) * 100))
                : 0;
        }

        // Calculate percentages for PP Lists (matched / total for each type)
        // NFO: nfo / (nfo + processnfo) * 100
        $nfoMatched = $this->runVar['counts']['now']['nfo'] ?? 0;
        $nfoUnmatched = $this->runVar['counts']['now']['processnfo'] ?? 0;
        $nfoTotal = $nfoMatched + $nfoUnmatched;
        $this->runVar['counts']['percent']['nfo'] = $nfoTotal > 0
            ? sprintf('%02d', floor(($nfoMatched / $nfoTotal) * 100))
            : 0;

        // PreDB: predb_matched / predb * 100
        $predbMatched = $this->runVar['counts']['now']['predb_matched'] ?? 0;
        $predbTotal = $this->runVar['counts']['now']['predb'] ?? 1;
        $this->runVar['counts']['percent']['predb_matched'] = $predbTotal > 0
            ? sprintf('%02d', floor(($predbMatched / $predbTotal) * 100))
            : 0;

        // Renames: renamed / (renamed + processrenames) * 100
        $renamed = $this->runVar['counts']['now']['renamed'] ?? 0;
        $processrenames = $this->runVar['counts']['now']['processrenames'] ?? 0;
        $renameTotal = $renamed + $processrenames;
        $this->runVar['counts']['percent']['renamed'] = $renameTotal > 0
            ? sprintf('%02d', floor(($renamed / $renameTotal) * 100))
            : 0;
    }

    /**
     * @return array<string, int>
     */
    protected function defaultCountValues(): array
    {
        return [
            'active_groups' => 0,
            'all_groups' => 0,
            'audio' => 0,
            'backfill_groups_date' => 0,
            'backfill_groups_days' => 0,
            'binaries_table' => 0,
            'books' => 0,
            'collections_table' => 0,
            'console' => 0,
            'distinct_predb_matched' => 0,
            'misc' => 0,
            'missed_parts_table' => 0,
            'movies' => 0,
            'nfo' => 0,
            'parts_table' => 0,
            'pc' => 0,
            'predb' => 0,
            'predb_matched' => 0,
            'processanime' => 0,
            'processbooks' => 0,
            'processconsole' => 0,
            'processgames' => 0,
            'processmovies' => 0,
            'processmusic' => 0,
            'processnfo' => 0,
            'processrenames' => 0,
            'processtv' => 0,
            'releases' => 0,
            'renamed' => 0,
            'total_work' => 0,
            'tv' => 0,
            'work' => 0,
            'work_available' => 0,
            'xxx' => 0,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultDiffValues(): array
    {
        return array_fill_keys(array_keys($this->defaultCountValues()), '0');
    }

    /**
     * @return array<string, int|string>
     */
    protected function defaultPercentValues(): array
    {
        return [
            'audio' => 0,
            'books' => 0,
            'console' => 0,
            'misc' => 0,
            'movies' => 0,
            'nfo' => 0,
            'pc' => 0,
            'predb_matched' => 0,
            'renamed' => 0,
            'tv' => 0,
            'xxx' => 0,
        ];
    }

    /**
     * Update connection counts
     */
    protected function updateConnectionCounts(): void
    {
        $socketSnapshot = $this->tmux->getSocketSnapshot();
        $this->runVar['conncounts'] = $this->tmux->getUSPConnections(
            'primary',
            $this->runVar['connections'],
            $socketSnapshot,
        );

        if ((int) ($this->runVar['constants']['alternate_nntp'] ?? 0) === 1) {
            $alternateConns = $this->tmux->getUSPConnections(
                'alternate',
                $this->runVar['connections'],
                $socketSnapshot,
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
     *
     * @return array<string, mixed>
     */
    public function getRunVar(): array
    {
        return $this->runVar;
    }
}
