<?php

namespace App\Services\Tmux;

use Illuminate\Support\Facades\Process;

/**
 * Tmux output functions for printing monitor data.
 *
 *
 * Class TmuxOutput
 */
class TmuxOutput extends Tmux
{
    protected $_colourMasks;

    private $runVar;

    private $tmpMasks;

    /**
     * TmuxOutput constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->_setColourMasks();
    }

    public function updateMonitorPane(&$runVar): void
    {
        $this->runVar = $runVar;
        $this->tmpMasks = $this->_getFormatMasks(config('nntmux_nntp.compressed_headers'));

        $buffer = $this->_getHeader();

        if ($runVar['settings']['monitor'] > 0) {
            $buffer .= $this->_getMonitor();
        }

        if ((int) $runVar['settings']['show_query'] === 1) {
            $buffer .= $this->_getQueries();
        }

        // begin update display with screen clear
        passthru('clear');
        echo $buffer;
    }

    protected function _getBackfill(): string
    {
        $buffer = sprintf($this->tmpMasks[3], 'Groups', 'Active', 'Backfill');
        $buffer .= $this->_getSeparator();

        if ($this->runVar['settings']['backfilldays'] === '1') {
            $buffer .= sprintf(
                $this->tmpMasks[4],
                'Activated',
                sprintf(
                    '%d(%d)',
                    $this->runVar['counts']['now']['active_groups'],
                    $this->runVar['counts']['now']['all_groups']
                ),
                sprintf(
                    '%d(%d)',
                    $this->runVar['counts']['now']['backfill_groups_days'],
                    $this->runVar['counts']['now']['all_groups']
                )
            );
        } else {
            $buffer .= sprintf(
                $this->tmpMasks[4],
                'Activated',
                sprintf(
                    '%d(%d)',
                    $this->runVar['counts']['now']['active_groups'],
                    $this->runVar['counts']['now']['all_groups']
                ),
                sprintf(
                    '%d(%d)',
                    $this->runVar['counts']['now']['backfill_groups_date'],
                    $this->runVar['counts']['now']['all_groups']
                )
            );
        }

        return $buffer;
    }

    protected function _getFormatMasks($compressed): array
    {
        $index = ($compressed === true ? '2.1' : '2.0');

        return [
            1 => &$this->_colourMasks[1],
            2 => &$this->_colourMasks[$index],
            3 => &$this->_colourMasks[3],
            4 => &$this->_colourMasks[4],
            5 => &$this->_colourMasks[5],
        ];
    }

    protected function _getHeader(): string
    {
        $buffer = '';
        $state = ((int) $this->runVar['settings']['is_running'] === 1) ? 'Running' : 'Disabled';
        $version = str_replace(["\n", "\r"], '', Process::run('git describe --tags')->output());
        $branch = str_replace(["\n", "\r"], '', Process::run('git branch --show-current')->output());

        $buffer .= sprintf(
            $this->tmpMasks[2],
            "Monitor $state $version [".$branch.'] :',
            $this->relativeTime($this->runVar['timers']['timer1'])
        );

        $buffer .= sprintf(
            $this->tmpMasks[1],
            'USP Connections:',
            sprintf(
                '%d active (%d total) - %s:%d',
                $this->runVar['conncounts']['primary']['active'],
                $this->runVar['conncounts']['primary']['total'],
                $this->runVar['connections']['host'],
                $this->runVar['connections']['port']
            )
        );

        if ($this->runVar['constants']['alternate_nntp'] === '1') {
            $buffer .= sprintf(
                $this->tmpMasks[1],
                'USP Alternate:',
                sprintf(
                    '%d active (%d total) - %s:%s',
                    $this->runVar['conncounts']['alternate']['active'] ?? 0,
                    $this->runVar['conncounts']['alternate']['total'] ?? 0,
                    $this->runVar['connections']['host_a'] ?: 'N/A',
                    $this->runVar['connections']['port_a'] ?: 'N/A'
                )
            );
        }

        $buffer .= sprintf(
            $this->tmpMasks[1],
            'Newest Release:',
            $this->runVar['timers']['newOld']['newestrelname']
        );
        $buffer .= sprintf(
            $this->tmpMasks[1],
            'Release Added:',
            sprintf(
                '%s',
                (isset($this->runVar['timers']['newOld']['newestrelease'])
                    ? $this->relativeTime($this->runVar['timers']['newOld']['newestrelease'])
                    : 0)
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[1],
            'Predb Updated:',
            sprintf(
                '%s',
                (isset($this->runVar['timers']['newOld']['newestpre'])
                    ? $this->relativeTime($this->runVar['timers']['newOld']['newestpre'])
                    : 0)
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[1],
            sprintf(
                'Collection Age[%d]:',
                $this->runVar['constants']['delaytime']
            ),
            sprintf(
                '%s',
                (isset($this->runVar['timers']['newOld']['oldestcollection'])
                    ? $this->relativeTime($this->runVar['timers']['newOld']['oldestcollection'])
                    : 0)
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[1],
            'Parts in Repair:',
            number_format($this->runVar['counts']['now']['missed_parts_table'])
        );

        if (((int) $this->runVar['settings']['post'] === 1 || (int) $this->runVar['settings']['post'] === 3) && (int) $this->runVar['constants']['sequential'] !== 2) {
            $buffer .= sprintf(
                $this->tmpMasks[1],
                'Postprocess:',
                'stale for '.$this->relativeTime($this->runVar['timers']['timer3'])
            );
        }

        return $buffer.PHP_EOL;
    }

    protected function _getMonitor(): string
    {
        $buffer = $this->_getTableCounts();
        $buffer .= $this->_getPaths();

        $buffer .= sprintf($this->tmpMasks[3], 'PP Lists', 'Unmatched', 'Matched');
        $buffer .= $this->_getSeparator();

        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Nfo',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processnfo']),
                $this->runVar['counts']['diff']['processnfo']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['nfo']),
                $this->runVar['counts']['percent']['nfo']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'PreDB',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['predb'] -
                    $this->runVar['counts']['now']['distinct_predb_matched']),
                $this->runVar['counts']['diff']['distinct_predb_matched']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['predb_matched']),
                $this->runVar['counts']['percent']['predb_matched']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Renames',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processrenames']),
                $this->runVar['counts']['diff']['processrenames']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['renamed']),
                $this->runVar['counts']['percent']['renamed']
            )
        );

        $buffer .= PHP_EOL;
        $buffer .= sprintf($this->tmpMasks[3], 'Category', 'In Process', 'In Database');
        $buffer .= $this->_getSeparator();

        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Audio',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processmusic']),
                $this->runVar['counts']['diff']['processmusic']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['audio']),
                $this->runVar['counts']['percent']['audio']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Books',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processbooks']),
                $this->runVar['counts']['diff']['processbooks']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['books']),
                $this->runVar['counts']['percent']['books']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Console',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processconsole']),
                $this->runVar['counts']['diff']['processconsole']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['console']),
                $this->runVar['counts']['percent']['console']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Misc',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['work']),
                $this->runVar['counts']['diff']['work']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['misc']),
                $this->runVar['counts']['percent']['misc']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Movie',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processmovies']),
                $this->runVar['counts']['diff']['processmovies']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['movies']),
                $this->runVar['counts']['percent']['movies']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'PC',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processgames']),
                $this->runVar['counts']['diff']['processgames']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['pc']),
                $this->runVar['counts']['percent']['pc']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'TV',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processtv']),
                $this->runVar['counts']['diff']['processtv']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['tv']),
                $this->runVar['counts']['percent']['tv']
            )
        );
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'XXX',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['processxxx']),
                $this->runVar['counts']['diff']['processxxx']
            ),
            sprintf(
                '%s(%d%%)',
                number_format($this->runVar['counts']['now']['xxx']),
                $this->runVar['counts']['percent']['xxx']
            )
        );

        $buffer .= $this->_getSeparator();

        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Total',
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['total_work']),
                $this->runVar['counts']['diff']['total_work']
            ),
            sprintf(
                '%s(%s)',
                number_format($this->runVar['counts']['now']['releases']),
                $this->runVar['counts']['diff']['releases']
            )
        );
        $buffer .= PHP_EOL;

        $buffer .= $this->_getBackfill();

        return $buffer;
    }

    protected function _getPaths(): string
    {
        $buffer = '';

        // assign timers from tmux table
        $monitor_path = $this->runVar['settings']['monitor_path'];
        $monitor_path_a = $this->runVar['settings']['monitor_path_a'];
        $monitor_path_b = $this->runVar['settings']['monitor_path_b'];

        if (($monitor_path !== null && file_exists($monitor_path))
            || ($monitor_path_a !== null && file_exists($monitor_path_a))
            || ($monitor_path_b !== null && file_exists($monitor_path_b))) {
            $buffer .= "\n";
            $buffer .= sprintf($this->tmpMasks[3], 'File System', 'Used', 'Free');
            $buffer .= $this->_getSeparator();

            if (! empty($monitor_path) && file_exists($monitor_path)) {
                $disk_use = $this->decodeSize(disk_total_space($monitor_path) - disk_free_space($monitor_path));
                $disk_free = $this->decodeSize(disk_free_space($monitor_path));
                if (basename($monitor_path) === '') {
                    $show = '/';
                } else {
                    $show = basename($monitor_path);
                }
                $buffer .= sprintf($this->tmpMasks[4], $show, $disk_use, $disk_free);
            }

            if (! empty($monitor_path_a) && file_exists($monitor_path_a)) {
                $disk_use = $this->decodeSize(disk_total_space($monitor_path_a) - disk_free_space($monitor_path_a));
                $disk_free = $this->decodeSize(disk_free_space($monitor_path_a));
                if (basename($monitor_path_a) === '') {
                    $show = '/';
                } else {
                    $show = basename($monitor_path_a);
                }
                $buffer .= sprintf($this->tmpMasks[4], $show, $disk_use, $disk_free);
            }

            if (! empty($monitor_path_b) && file_exists($monitor_path_b)) {
                $disk_use = $this->decodeSize(disk_total_space($monitor_path_b) - disk_free_space($monitor_path_b));
                $disk_free = $this->decodeSize(disk_free_space($monitor_path_b));
                if (basename($monitor_path_b) === '') {
                    $show = '/';
                } else {
                    $show = basename($monitor_path_b);
                }
                $buffer .= sprintf($this->tmpMasks[4], $show, $disk_use, $disk_free);
            }
        }

        return $buffer.PHP_EOL;
    }

    protected function _getQueries(): string
    {
        $buffer = PHP_EOL;
        $buffer .= sprintf($this->tmpMasks[3], 'Query Block', 'Time', 'Cumulative');
        $buffer .= $this->_getSeparator();
        $buffer .= sprintf(
            $this->tmpMasks[4],
            'Combined',
            sprintf(
                '%d %d %d %d %d %d %d',
                $this->runVar['timers']['query']['tmux_time'],
                $this->runVar['timers']['query']['split_time'],
                $this->runVar['timers']['query']['init_time'],
                $this->runVar['timers']['query']['proc1_time'],
                $this->runVar['timers']['query']['proc2_time'],
                $this->runVar['timers']['query']['proc3_time'],
                $this->runVar['timers']['query']['tpg_time']
            ),
            sprintf(
                '%d %d %d %d %d %d %d',
                $this->runVar['timers']['query']['tmux_time'],
                $this->runVar['timers']['query']['split1_time'],
                $this->runVar['timers']['query']['init1_time'],
                $this->runVar['timers']['query']['proc11_time'],
                $this->runVar['timers']['query']['proc21_time'],
                $this->runVar['timers']['query']['proc31_time'],
                $this->runVar['timers']['query']['tpg1_time']
            )
        );

        $info = $this->pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
        $pieces = [];
        foreach ([
            ['Threads', '/.*\bThreads: (\d+)\b.*/'],
            ['Opens', '/.*\bOpens[^:]*?: (\d+)\b.*/'],
            ['Tables', '/.* tables: (\d+)\b.*/'],
            ['Slow', '/.*\bSlow[^:]*?: (\d+)\b.*/'],
            ['QPS', '/.*\bQueries[^:]*?: (\d+)\b.*/'],
        ] as $v) {
            $pieces[] = cli()->ansiString($v[0].' = ', 'green').
                cli()->ansiString(preg_replace($v[1], '$1', $info), 'yellow');
        }
        $buffer .= PHP_EOL.implode(', ', $pieces).PHP_EOL;

        return $buffer;
    }

    protected function _getSeparator(): string
    {
        return sprintf(
            $this->tmpMasks[3],
            '======================================',
            '=========================',
            '======================================'
        );
    }

    protected function _getTableCounts(): string
    {
        $buffer = sprintf($this->tmpMasks[3], 'Collections', 'Binaries', 'Parts');
        $buffer .= $this->_getSeparator();
        $buffer .= sprintf(
            $this->tmpMasks[5],
            number_format($this->runVar['counts']['now']['collections_table']),
            number_format($this->runVar['counts']['now']['binaries_table']),
            number_format($this->runVar['counts']['now']['parts_table'])
        );

        return $buffer;
    }

    protected function _setColourMasks(): void
    {
        $this->_colourMasks[1] = cli()->ansiString('%-18s', 'yellow').' '.cli()->ansiString('%-60.60s', 'yellow').PHP_EOL;
        $this->_colourMasks['2.0'] = cli()->ansiString('%-20s', 'magenta').' '.cli()->ansiString('%-33.33s', 'yellow').PHP_EOL;
        $this->_colourMasks['2.1'] = cli()->ansiString('%-20s', 'yellow').' '.cli()->ansiString('%-33.33s', 'yellow').PHP_EOL;
        $this->_colourMasks[3] = cli()->ansiString('%-16.16s %25.25s %25.25s', 'yellow').PHP_EOL;
        $this->_colourMasks[4] = cli()->ansiString('%-16.16s', 'green').' '.cli()->ansiString('%25.25s %25.25s', 'yellow').PHP_EOL;
        $this->_colourMasks[5] = cli()->ansiString('%-16.16s %25.25s %25.25s', 'yellow').PHP_EOL;
    }
}
