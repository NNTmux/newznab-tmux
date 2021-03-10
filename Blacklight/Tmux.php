<?php

namespace Blacklight;

use App\Models\Category;
use App\Models\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Tmux.
 */
class Tmux
{
    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var
     */
    public $tmux_session;

    /**
     * @var \Blacklight\ColorCLI
     */
    protected $colorCli;

    /**
     * Tmux constructor.
     */
    public function __construct()
    {
        $this->pdo = DB::connection()->getPdo();
        $this->colorCli = new ColorCLI();
    }

    /**
     * @param $constants
     *
     * @return mixed
     */
    public function getConnectionsInfo($constants)
    {
        $runVar['connections']['port_a'] = $runVar['connections']['host_a'] = $runVar['connections']['ip_a'] = false;
        $runVar['connections']['port'] = config('nntmux_nntp.port');
        $runVar['connections']['host'] = config('nntmux_nntp.server');
        $runVar['connections']['ip'] = gethostbyname($runVar['connections']['host']);
        if ($constants['alternate_nntp'] === '1') {
            $runVar['connections']['port_a'] = config('nntmux_nntp.alternate_server_port');
            $runVar['connections']['host_a'] = config('nntmux_nntp.alternate_server');
            $runVar['connections']['ip_a'] = gethostbyname($runVar['connections']['host_a']);
        }

        return $runVar['connections'];
    }

    /**
     * @param $which
     * @param $connections
     *
     * @return mixed
     */
    public function getUSPConnections($which, $connections)
    {
        switch ($which) {
            case 'alternate':
                $ip = 'ip_a';
                $port = 'port_a';
                break;
            case 'primary':
            default:
                $ip = 'ip';
                $port = 'port';
                break;
        }

        $runVar['conncounts'][$which]['active'] = str_replace("\n", '', shell_exec('ss -n | grep '.$connections[$ip].':'.$connections[$port].' | grep -c ESTAB'));
        $runVar['conncounts'][$which]['total'] = str_replace("\n", '', shell_exec('ss -n | grep -c '.$connections[$ip].':'.$connections[$port]));

        if ((int) $runVar['conncounts'][$which]['active'] === 0 && (int) $runVar['conncounts'][$which]['total'] === 0) {
            $runVar['conncounts'][$which]['active'] = str_replace("\n", '', shell_exec('ss -n | grep '.$connections[$ip].':https | grep -c ESTAB'));
            $runVar['conncounts'][$which]['total'] = str_replace("\n", '', shell_exec('ss -n | grep -c '.$connections[$ip].':https'));
        }
        if ((int) $runVar['conncounts'][$which]['active'] === 0 && (int) $runVar['conncounts'][$which]['total'] === 0) {
            $runVar['conncounts'][$which]['active'] = str_replace("\n", '', shell_exec('ss -n | grep '.$connections[$port].' | grep -c ESTAB'));
            $runVar['conncounts'][$which]['total'] = str_replace("\n", '', shell_exec('ss -n | grep -c '.$connections[$port]));
        }
        if ((int) $runVar['conncounts'][$which]['active'] === 0 && (int) $runVar['conncounts'][$which]['total'] === 0) {
            $runVar['conncounts'][$which]['active'] = str_replace("\n", '', shell_exec('ss -n | grep '.$connections[$ip].' | grep -c ESTAB'));
            $runVar['conncounts'][$which]['total'] = str_replace("\n", '', shell_exec('ss -n | grep -c '.$connections[$ip]));
        }

        return $runVar['conncounts'];
    }

    /**
     * @param $constants
     *
     * @return array
     */
    public function getListOfPanes($constants): array
    {
        $panes = ['zero' => '', 'one' => '', 'two' => ''];
        switch ($constants['sequential']) {
            case 0:
            case 1:
                $panes_win_1 = shell_exec("echo `tmux list-panes -t {$constants['tmux_session']}:0 -F '#{pane_title}'`");
                $panes['zero'] = str_replace("\n", '', explode(' ', $panes_win_1));
                $panes_win_2 = shell_exec("echo `tmux list-panes -t {$constants['tmux_session']}:1 -F '#{pane_title}'`");
                $panes['one'] = str_replace("\n", '', explode(' ', $panes_win_2));
                $panes_win_3 = shell_exec("echo `tmux list-panes -t {$constants['tmux_session']}:2 -F '#{pane_title}'`");
                $panes['two'] = str_replace("\n", '', explode(' ', $panes_win_3));
                break;
            case 2:
                $panes_win_1 = shell_exec("echo `tmux list-panes -t {$constants['tmux_session']}:0 -F '#{pane_title}'`");
                $panes['zero'] = str_replace("\n", '', explode(' ', $panes_win_1));
                $panes_win_2 = shell_exec("echo `tmux list-panes -t {$constants['tmux_session']}:1 -F '#{pane_title}'`");
                $panes['one'] = str_replace("\n", '', explode(' ', $panes_win_2));
                break;
        }

        return $panes;
    }

    /**
     * @return string
     */
    public function getConstantSettings(): string
    {
        $settstr = 'SELECT value FROM settings WHERE setting =';

        $sql = sprintf(
            "SELECT
					(%1\$s 'sequential') AS sequential,
					(%1\$s 'tmux_session') AS tmux_session,
					(%1\$s 'run_ircscraper') AS run_ircscraper,
					(%1\$s 'alternate_nntp') AS alternate_nntp,
					(%1\$s 'delaytime') AS delaytime",
            $settstr
        );

        return $sql;
    }

    /**
     * @return string
     */
    public function getMonitorSettings(): string
    {
        $settstr = 'SELECT value FROM settings WHERE setting =';

        $sql = sprintf(
            "SELECT
					(%1\$s 'monitor_delay') AS monitor,
					(%1\$s 'binaries') AS binaries_run,
					(%1\$s 'backfill') AS backfill,
					(%1\$s 'backfill_qty') AS backfill_qty,
					(%1\$s 'import') AS import,
					(%1\$s 'nzbs') AS nzbs,
					(%1\$s 'post') AS post,
					(%1\$s 'releases') AS releases_run,
					(%1\$s 'releases_threaded') AS releases_threaded,
					(%1\$s 'fix_names') AS fix_names,
					(%1\$s 'seq_timer') AS seq_timer,
					(%1\$s 'bins_timer') AS bins_timer,
					(%1\$s 'back_timer') AS back_timer,
					(%1\$s 'import_count') AS import_count,
					(%1\$s 'import_timer') AS import_timer,
					(%1\$s 'rel_timer') AS rel_timer,
					(%1\$s 'fix_timer') AS fix_timer,
					(%1\$s 'post_timer') AS post_timer,
					(%1\$s 'collections_kill') AS collections_kill,
					(%1\$s 'postprocess_kill') AS postprocess_kill,
					(%1\$s 'crap_timer') AS crap_timer,
					(%1\$s 'fix_crap') AS fix_crap,
					(%1\$s 'fix_crap_opt') AS fix_crap_opt,
					(%1\$s 'tv_timer') AS tv_timer,
					(%1\$s 'update_tv') AS update_tv,
					(%1\$s 'post_kill_timer') AS post_kill_timer,
					(%1\$s 'monitor_path') AS monitor_path,
					(%1\$s 'monitor_path_a') AS monitor_path_a,
					(%1\$s 'monitor_path_b') AS monitor_path_b,
					(%1\$s 'progressive') AS progressive,
					(%1\$s 'dehash') AS dehash,
					(%1\$s 'dehash_timer') AS dehash_timer,
					(%1\$s 'backfill_days') AS backfilldays,
					(%1\$s 'post_amazon') AS post_amazon,
					(%1\$s 'post_timer_amazon') AS post_timer_amazon,
					(%1\$s 'post_non') AS post_non,
					(%1\$s 'post_timer_non') AS post_timer_non,
					(%1\$s 'colors_start') AS colors_start,
					(%1\$s 'colors_end') AS colors_end,
					(%1\$s 'colors_exc') AS colors_exc,
					(%1\$s 'showquery') AS show_query,
					(%1\$s 'running') AS is_running,
					(%1\$s 'run_sharing') AS run_sharing,
					(%1\$s 'sharing_timer') AS sharing_timer,
					(%1\$s 'lookupbooks') AS processbooks,
					(%1\$s 'lookupmusic') AS processmusic,
					(%1\$s 'lookupgames') AS processgames,
					(%1\$s 'lookupxxx') AS processxxx,
					(%1\$s 'lookupimdb') AS processmovies,
					(%1\$s 'lookuptvrage') AS processtvrage,
					(%1\$s 'lookupanidb') AS processanime,
					(%1\$s 'lookupnfo') AS processnfo,
					(%1\$s 'lookuppar2') AS processpar2,
					(%1\$s 'nzbthreads') AS nzbthreads,
					(%1\$s 'tmpunrarpath') AS tmpunrar,
					(%1\$s 'compressedheaders') AS compressed,
					(%1\$s 'maxsizetopostprocess') AS maxsize_pp,
					(%1\$s 'minsizetopostprocess') AS minsize_pp",
            $settstr
        );

        return $sql;
    }

    /**
     * @param $setting
     * @param $value
     *
     * @return int
     */
    public function updateItem($setting, $value)
    {
        return Settings::query()->where('setting', '=', $setting)->update(['value' => $value]);
    }

    /**
     * @return float
     */
    public function microtime_float(): float
    {
        [$usec, $sec] = explode(' ', microtime());

        return (float) $usec + (float) $sec;
    }

    /**
     * @param float $bytes
     *
     * @return string
     */
    public function decodeSize($bytes): string
    {
        $types = ['B', 'KB', 'MB', 'GB', 'TB'];
        $suffix = 'B';
        foreach ($types as $type) {
            if ($bytes < 1024.0) {
                $suffix = $type;
                break;
            }
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$suffix;
    }

    /**
     * @param $pane
     *
     * @return string
     */
    public function writelog($pane): ?string
    {
        $path = NN_LOGS;
        $getdate = gmdate('Ymd');
        $logs = Settings::settingValue('site.tmux.write_logs') ?? 0;
        if ($logs === 1) {
            return "2>&1 | tee -a $path/$pane-$getdate.log";
        }

        return '';
    }

    /**
     * @param $colors_start
     * @param $colors_end
     * @param $colors_exc
     *
     * @return int
     * @throws \Exception
     */
    public function get_color($colors_start, $colors_end, $colors_exc): int
    {
        $exception = str_replace('.', '.', $colors_exc);
        $exceptions = explode(',', $exception);
        sort($exceptions);
        $number = random_int($colors_start, $colors_end - \count($exceptions));
        foreach ($exceptions as $exception) {
            if ($number >= $exception) {
                $number++;
            } else {
                break;
            }
        }

        return $number;
    }

    /**
     * Returns random bool, weighted by $chance.
     *
     *
     * @param     $loop
     * @param int $chance
     *
     * @return bool
     * @throws \Exception
     */
    public function rand_bool($loop, $chance = 60): bool
    {
        $usecache = Settings::settingValue('site.tmux.usecache') ?? 0;
        if ($loop === 1 || $usecache === 0) {
            return false;
        }

        return random_int(1, 100) <= $chance;
    }

    /**
     * @param $_time
     *
     * @return string
     */
    public function relativeTime($_time): string
    {
        $time = Carbon::createFromTimestamp($_time);

        return $time->ago();
    }

    /**
     * @param $cmd
     *
     * @return bool
     */
    public function command_exist($cmd): bool
    {
        $returnVal = shell_exec("which $cmd 2>/dev/null");

        return empty($returnVal) ? false : true;
    }

    /**
     * @param        $qry
     * @param        $bookreqids
     * @param string $db_name
     * @param string $ppmax
     * @param string $ppmin
     *
     * @return bool|string
     * @throws \Exception
     */
    public function proc_query($qry, $bookreqids, $db_name, $ppmax = '', $ppmin = '')
    {
        switch ((int) $qry) {
            case 1:
                return sprintf(
                    '
					SELECT
					(SELECT count(*) FROM releases WHERE nzbstatus = %d AND categories_id BETWEEN %d AND %d AND categories_id != %d AND videos_id = 0 AND tv_episodes_id BETWEEN -3 AND 0 AND size > 1048576) AS processtv,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id = %d AND anidbid IS NULL) AS processanime,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id BETWEEN %d AND %d AND imdbid IS NULL) AS processmovies,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id IN (%d, %d, %d) AND musicinfo_id IS NULL) AS processmusic,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id BETWEEN %d AND %d AND consoleinfo_id IS NULL) AS processconsole,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id IN (%s) AND bookinfo_id IS NULL) AS processbooks,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id = %d AND gamesinfo_id = 0) AS processgames,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND categories_id BETWEEN %d AND %d AND xxxinfo_id = 0) AS processxxx,
					(SELECT count(*) FROM releases r WHERE 1=1 %s) AS processnfo,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND isrenamed = %d AND predb_id = 0 AND passwordstatus >= 0 AND nfostatus > %d
						AND ((nfostatus = %d AND proc_nfo = %d) OR proc_files = %d OR proc_par2 = %d
							OR (ishashed = 1 AND dehashstatus BETWEEN -6 AND 0)) AND categories_id IN (%s)) AS processrenames,
					(SELECT count(*) FROM releases WHERE isrenamed = %d) AS renamed,
					(SELECT count(*) FROM releases WHERE nzbstatus = %1$d AND nfostatus = %20$d) AS nfo,
					(SELECT count(*) FROM releases WHERE predb_id > 0) AS predb_matched,
					(SELECT COUNT(DISTINCT(predb_id)) FROM releases) AS distinct_predb_matched',
                    NZB::NZB_ADDED,
                    Category::TV_ROOT,
                    Category::TV_OTHER,
                    Category::TV_ANIME,
                    Category::TV_ANIME,
                    Category::MOVIE_ROOT,
                    Category::MOVIE_OTHER,
                    Category::MUSIC_MP3,
                    Category::MUSIC_LOSSLESS,
                    Category::MUSIC_OTHER,
                    Category::GAME_ROOT,
                    Category::GAME_OTHER,
                    $bookreqids,
                    Category::PC_GAMES,
                    Category::XXX_ROOT,
                    Category::XXX_X264,
                    Nfo::NfoQueryString(),
                    NameFixer::IS_RENAMED_NONE,
                    Nfo::NFO_UNPROC,
                    Nfo::NFO_FOUND,
                    NameFixer::PROC_NFO_NONE,
                    NameFixer::PROC_FILES_NONE,
                    NameFixer::PROC_PAR2_NONE,
                    Category::getCategoryOthersGroup(),
                    NameFixer::IS_RENAMED_DONE
                );

            case 2:
                $ppminString = $ppmaxString = '';
                if (is_numeric($ppmax) && ! empty($ppmax)) {
                    $ppmax *= 1073741824;
                    $ppmaxString = "AND r.size < {$ppmax}";
                }
                if (is_numeric($ppmin) && ! empty($ppmin)) {
                    $ppmin *= 1048576;
                    $ppminString = "AND r.size > {$ppmin}";
                }

                return "SELECT
					(SELECT COUNT(r.id) FROM releases r
						LEFT JOIN categories c ON c.id = r.categories_id
						WHERE r.nzbstatus = 1
						AND r.passwordstatus = -1
						AND r.haspreview = -1
						{$ppminString}
						{$ppmaxString}
						AND c.disablepreview = 0
					) AS work,
					(SELECT COUNT(*) FROM usenet_groups WHERE active = 1) AS active_groups,
					(SELECT COUNT(*) FROM usenet_groups WHERE name IS NOT NULL) AS all_groups";

            case 4:
                return sprintf(
                    "
					SELECT
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'predb' AND TABLE_SCHEMA = %1\$s) AS predb,
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'missed_parts' AND TABLE_SCHEMA = %1\$s) AS missed_parts_table,
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'parts' AND TABLE_SCHEMA = %1\$s) AS parts_table,
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'binaries' AND TABLE_SCHEMA = %1\$s) AS binaries_table,
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'collections' AND TABLE_SCHEMA = %1\$s) AS collections_table,
					(SELECT TABLE_ROWS FROM information_schema.TABLES WHERE table_name = 'releases' AND TABLE_SCHEMA = %1\$s) AS releases,
					(SELECT COUNT(id) FROM usenet_groups WHERE first_record IS NOT NULL AND backfill = 1
						AND (now() - INTERVAL backfill_target DAY) < first_record_postdate
					) AS backfill_groups_days,
					(SELECT COUNT(id) FROM usenet_groups WHERE first_record IS NOT NULL AND backfill = 1 AND (now() - INTERVAL datediff(curdate(),
					(SELECT VALUE FROM settings WHERE setting = 'safebackfilldate')) DAY) < first_record_postdate) AS backfill_groups_date",
                    escapeString($db_name)
                );
            case 6:
                return 'SELECT
					(SELECT searchname FROM releases ORDER BY id DESC LIMIT 1) AS newestrelname,
					(SELECT UNIX_TIMESTAMP(MIN(dateadded)) FROM collections) AS oldestcollection,
					(SELECT UNIX_TIMESTAMP(MAX(predate)) FROM predb) AS newestpre,
					(SELECT UNIX_TIMESTAMP(adddate) FROM releases ORDER BY id DESC LIMIT 1) AS newestrelease';
            default:
                return false;
        }
    }

    /**
     * @return bool true if tmux is running, false otherwise.
     * @throws \RuntimeException
     */
    public function isRunning(): bool
    {
        $running = Settings::query()->where(['section' => 'site', 'subsection' => 'tmux', 'setting' => 'running'])->first(['value']);
        if ($running === null) {
            throw new \RuntimeException('Tmux\\\'s running flag was not found in the database.'.PHP_EOL.'Please check the tables are correctly setup.'.PHP_EOL);
        }

        return ! ((int) $running->value === 0);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function stopIfRunning(): bool
    {
        if ($this->isRunning() === true) {
            Settings::query()->where(['section' => 'site', 'subsection' => 'tmux', 'setting' => 'running'])->update(['value' => 0]);
            $sleep = Settings::settingValue('site.tmux.monitor_delay');
            $this->colorCli->header('Stopping tmux scripts and waiting '.$sleep.' seconds for all panes to shutdown');
            sleep($sleep);

            return true;
        }
        $this->colorCli->info('Tmux scripts are not running!');

        return false;
    }

    /**
     * @throws \RuntimeException
     */
    public function startRunning(): void
    {
        if ($this->isRunning() === false) {
            Settings::query()->where(['section' => 'site', 'subsection' => 'tmux', 'setting' => 'running'])->update(['value' => 1]);
        }
    }

    /**
     * @return array
     */
    public function cbpmTableQuery()
    {
        return DB::select(
            "
			SELECT TABLE_NAME AS name
      		FROM information_schema.TABLES
      		WHERE TABLE_SCHEMA = (SELECT DATABASE())
			AND TABLE_NAME REGEXP {escapeString('^(multigroup_)?(collections|binaries|parts|missed_parts)(_[0-9]+)?$')}
			ORDER BY TABLE_NAME ASC"
        );
    }
}
