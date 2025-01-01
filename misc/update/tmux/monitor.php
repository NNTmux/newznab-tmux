<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\Tmux;
use Blacklight\TmuxOutput;
use Blacklight\TmuxRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$tMain = new Tmux;
$colorCli = new ColorCLI;
try {
    $tRun = new TmuxRun;
} catch (Exception $e) {
    echo $e;
}
try {
    $tOut = new TmuxOutput;
} catch (Exception $e) {
    echo $e;
}

$runVar['paths']['misc'] = base_path().'/misc/';
$runVar['paths']['cli'] = base_path().'/cli/';
$runVar['paths']['scraper'] = base_path().'/misc/IRCScraper/scrape.php';

$db_name = config('nntmux.db_name');

$tmux_niceness = Settings::settingValue('niceness') ?? 2;

$runVar['constants'] = (array) Arr::first(DB::select($tRun->getConstantSettings()));

// assign shell commands
$runVar['commands']['_php'] = " nice -n{$tmux_niceness} php";
$runVar['commands']['_phpn'] = "nice -n{$tmux_niceness} php";
$runVar['commands']['_sleep'] = "{$runVar['commands']['_phpn']} {$runVar['paths']['misc']}update/tmux/bin/showsleep.php";

// spawn IRCScraper as soon as possible
try {
    $tRun->runPane('scraper', $runVar);
} catch (Exception $e) {
    echo $e;
}

// get list of panes by name
$runVar['panes'] = $tRun->getListOfPanes($runVar['constants']);

// totals per category in db, results by parentID
$catCountQuery = 'SELECT c.root_categories_id AS parentid, COUNT(r.id) AS count FROM categories c, releases r WHERE r.categories_id = c.id GROUP BY c.root_categories_id';

// create timers and set to now
$runVar['timers']['timer1'] = $runVar['timers']['timer2'] = $runVar['timers']['timer3'] =
$runVar['timers']['timer4'] = $runVar['timers']['timer5'] = time();

$runVar['timers']['query']['tmux_time'] = $runVar['timers']['query']['split_time'] = $runVar['timers']['query']['init_time'] = $runVar['timers']['query']['proc1_time'] =
$runVar['timers']['query']['proc2_time'] = $runVar['timers']['query']['proc3_time'] = $runVar['timers']['query']['split1_time'] = $runVar['timers']['query']['init1_time'] =
$runVar['timers']['query']['proc11_time'] = $runVar['timers']['query']['proc21_time'] = $runVar['timers']['query']['proc31_time'] = $runVar['timers']['query']['tpg_time'] =
$runVar['timers']['query']['tpg1_time'] = 0;

$runVar['settings']['monitor'] = 0;
$runVar['counts']['iterations'] = 1;
$runVar['modsettings']['fc']['firstrun'] = true;
$runVar['modsettings']['fc']['num'] = 0;

$tblCount = 'SELECT TABLE_ROWS AS count FROM information_schema.TABLES WHERE TABLE_NAME = :table AND TABLE_SCHEMA = '.escapeString($db_name);
$psTableRowCount = $pdo->prepare($tblCount);

while ($runVar['counts']['iterations'] > 0) {
    $timer01 = time();
    // These queries are very fast, run every loop -- tmux and site settings
    $runVar['settings'] = (array) Arr::first(DB::select($tRun->getMonitorSettings()));
    $runVar['timers']['query']['tmux_time'] = (time() - $timer01);

    $runVar['settings']['book_reqids'] = (! empty($runVar['settings']['book_reqids'])
        ? $runVar['settings']['book_reqids'] : Category::BOOKS_ROOT);

    // get usenet connection info
    $runVar['connections'] = $tOut->getConnectionsInfo($runVar['constants']);

    $runVar['constants']['pre_lim'] = ($runVar['counts']['iterations'] > 1 ? '7' : '');

    // assign scripts
    $runVar['scripts']['releases'] = "{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/releases.php";

    $runVar['scripts']['binaries'] = match ((int) $runVar['settings']['binaries_run']) {
        1 => "{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/safe.php binaries",
        default => 0,
    };

    switch ((int) $runVar['settings']['backfill']) {
        case 1:
            $runVar['scripts']['backfill'] = "{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/backfill.php";
            break;
        case 4:
            $runVar['scripts']['backfill'] = "{$runVar['commands']['_php']} {$runVar['paths']['misc']}update/multiprocessing/safe.php backfill";
    }

    // get usenet connection counts
    unset($runVar['conncounts']);
    $runVar['conncounts'] = $tOut->getUSPConnections('primary', $runVar['connections']);

    if ((int) $runVar['constants']['alternate_nntp'] === 1) {
        $runVar['conncounts'] += $tOut->getUSPConnections('alternate', $runVar['connections']);
    }

    // run queries only after time exceeded, these queries can take awhile
    if ((int) $runVar['counts']['iterations'] === 1 || (time() - $runVar['timers']['timer2'] >= $runVar['settings']['monitor'] && (int) $runVar['settings']['is_running'] === 1)) {
        $runVar['counts']['proc1'] = $runVar['counts']['proc2'] = $runVar['counts']['proc3'] = $splitQry = $newOldQry = false;
        $runVar['counts']['now']['total_work'] = 0;
        $runVar['modsettings']['fix_crap'] = explode(', ', $runVar['settings']['fix_crap']);

        $colorCli->info("\nThe numbers(queries) above are currently being refreshed. \nNo pane(script) can be (re)started until these have completed.\n");
        $timer02 = time();

        try {
            $splitQry = $tRun->proc_query(4, null, $db_name);
        } catch (Exception $e) {
            echo $e;
        }
        try {
            $newOldQry = $tRun->proc_query(6, null, $db_name);
        } catch (Exception $e) {
            echo $e;
        }

        $splitRes = (array) Arr::first(DB::select($splitQry));
        $runVar['timers']['newOld'] = (array) Arr::first(DB::select($newOldQry));

        // assign split query results to main var
        foreach ($splitRes as $splitKey => $split) {
            $runVar['counts']['now'][$splitKey] = $split;
        }

        $runVar['timers']['query']['split_time'] = (time() - $timer02);
        $runVar['timers']['query']['split1_time'] = (time() - $timer01);

        $timer03 = time();

        $tvCount = Release::query()->whereBetween('categories_id', [Category::TV_ROOT, Category::TV_OTHER])->count(['id']);
        $movieCount = Release::query()->whereBetween('categories_id', [Category::MOVIE_ROOT, Category::MOVIE_OTHER])->count(['id']);
        $audioCount = Release::query()->whereBetween('categories_id', [Category::MUSIC_ROOT, Category::MUSIC_OTHER])->count(['id']);
        $bookCount = Release::query()->whereBetween('categories_id', [Category::BOOKS_ROOT, Category::BOOKS_UNKNOWN])->count(['id']);
        $consoleCount = Release::query()->whereBetween('categories_id', [Category::GAME_ROOT, Category::GAME_OTHER])->count(['id']);
        $pcCount = Release::query()->whereBetween('categories_id', [Category::PC_ROOT, Category::PC_PHONE_ANDROID])->count(['id']);
        $xxxCount = Release::query()->whereBetween('categories_id', [Category::XXX_ROOT, Category::XXX_OTHER])->count(['id']);
        $miscCount = Release::query()->whereBetween('categories_id', [Category::OTHER_ROOT, Category::OTHER_HASHED])->count(['id']);

        $runVar['counts']['now']['audio'] = $audioCount;
        $runVar['counts']['now']['books'] = $bookCount;
        $runVar['counts']['now']['console'] = $consoleCount;
        $runVar['counts']['now']['misc'] = $miscCount;
        $runVar['counts']['now']['movies'] = $movieCount;
        $runVar['counts']['now']['pc'] = $pcCount;
        $runVar['counts']['now']['tv'] = $tvCount;
        $runVar['counts']['now']['xxx'] = $xxxCount;

        $runVar['timers']['query']['init_time'] = (time() - $timer03);
        $runVar['timers']['query']['init1_time'] = (time() - $timer01);

        $timer04 = time();
        try {
            $proc1qry = $tRun->proc_query(1, $runVar['settings']['book_reqids'], $db_name);
        } catch (Exception $e) {
            echo $e;
        }
        $proc1res = (array) Arr::first(DB::select($proc1qry));
        $runVar['timers']['query']['proc1_time'] = (time() - $timer04);
        $runVar['timers']['query']['proc11_time'] = (time() - $timer01);

        $timer05 = time();
        try {
            $proc2qry = $tRun->proc_query(2, $runVar['settings']['book_reqids'], $db_name, $runVar['settings']['maxsize_pp'], $runVar['settings']['minsize_pp']);
        } catch (Exception $e) {
            echo $e;
        }
        $proc2res = (array) Arr::first(DB::select($proc2qry));
        $runVar['timers']['query']['proc2_time'] = (time() - $timer05);
        $runVar['timers']['query']['proc21_time'] = (time() - $timer01);

        // Need to remove this
        $timer06 = time();
        $runVar['timers']['query']['proc3_time'] = (time() - $timer06);
        $runVar['timers']['query']['proc31_time'] = (time() - $timer01);

        $timer07 = time();
        $tables = $tMain->cbpmTableQuery();
        $age = time();

        $runVar['counts']['now']['collections_table'] = $runVar['counts']['now']['binaries_table'] = 0;
        $runVar['counts']['now']['parts_table'] = $runVar['counts']['now']['parterpair_table'] = 0;

        if ($psTableRowCount === false) {
            echo 'Unable to prepare statement, skipping monitor updates!';
        } else {
            foreach ($tables as $row) {
                $tbl = $row->name;
                $stamp = 'UNIX_TIMESTAMP(MIN(dateadded))';

                switch (true) {
                    case str_contains($tbl, 'collections'):
                        $runVar['counts']['now']['collections_table'] +=
                            getTableRowCount($psTableRowCount, $tbl);
                        $added = DB::select(sprintf('SELECT %s AS dateadded FROM %s', $stamp, $tbl));
                        if (isset($added['dateadded']) && is_numeric($added['dateadded']) &&
                            $added['dateadded'] < $age
                        ) {
                            $age = $added['dateadded'];
                        }
                        break;
                    case str_contains($tbl, 'binaries'):
                        $runVar['counts']['now']['binaries_table'] +=
                            getTableRowCount($psTableRowCount, $tbl);
                        break;
                        // This case must come before the 'parts_' one.
                    case str_contains($tbl, 'missed_parts'):
                        $runVar['counts']['now']['missed_parts_table'] +=
                            getTableRowCount($psTableRowCount, $tbl);

                        break;
                    case str_contains($tbl, 'parts'):
                        $runVar['counts']['now']['parts_table'] +=
                            getTableRowCount($psTableRowCount, $tbl);
                        break;
                    default:
                }
            }
            $runVar['timers']['newOld']['oldestcollection'] = $age;

            // free up memory used by now stale data
            unset($age, $added, $tables);

            $runVar['timers']['query']['tpg_time'] = (time() - $timer07);
            $runVar['timers']['query']['tpg1_time'] = (time() - $timer01);
        }
        $runVar['timers']['timer2'] = time();

        // assign postprocess values from $proc
        if (is_array($proc1res)) {
            foreach ($proc1res as $proc1key => $proc1) {
                $runVar['counts']['now'][$proc1key] = $proc1;
            }
        } else {
            errorOnSQL();
        }

        if (is_array($proc2res)) {
            foreach ($proc2res as $proc2key => $proc2) {
                $runVar['counts']['now'][$proc2key] = $proc2;
            }
        } else {
            errorOnSQL();
        }

        // now that we have merged our query data we can unset these to free up memory
        unset($proc1res, $proc2res, $splitRes);

        // Zero out any post proc counts when that type of pp has been turned off
        foreach ($runVar['settings'] as $settingKey => $setting) {
            if ((int) $setting === 0 && (int) strpos($settingKey, 'process') === 0) {
                $runVar['counts']['now'][$settingKey] = $runVar['counts']['start'][$settingKey] = 0;
            }
            if ($settingKey === 'fix_names' && (int) $setting === 0) {
                $runVar['counts']['now']['processrenames'] = $runVar['counts']['start']['processrenames'] = 0;
            }
        }

        // set initial start postproc values from work queries -- this is used to determine diff variables
        if ((int) $runVar['counts']['iterations'] === 1) {
            $runVar['counts']['start'] = $runVar['counts']['now'];
        }

        foreach ($runVar['counts']['now'] as $key => $proc) {
            // if key is a process type, add it to total_work
            if (str_starts_with($key, 'process')) {
                $runVar['counts']['now']['total_work'] += $proc;
            }

            // calculate diffs
            $runVar['counts']['diff'][$key] = number_format($proc - $runVar['counts']['start'][$key]);

            // calculate percentages -- if user has no releases, set 0 for each key or this will fail on divide by zero
            $runVar['counts']['percent'][$key] = $runVar['counts']['now']['releases'] > 0
                ? sprintf('%02s', floor(($proc / $runVar['counts']['now']['releases']) * 100)) : 0;
        }

        $runVar['counts']['now']['total_work'] += $runVar['counts']['now']['work'];

        // Set initial total work count for diff
        if ((int) $runVar['counts']['iterations'] === 1) {
            $runVar['counts']['start']['total_work'] = $runVar['counts']['now']['total_work'];
        }

        // Set diff total work count
        $runVar['counts']['diff']['total_work'] = number_format($runVar['counts']['now']['total_work'] - $runVar['counts']['start']['total_work']);
    }

    // set kill switches
    $runVar['killswitch']['pp'] = (($runVar['settings']['postprocess_kill'] < $runVar['counts']['now']['total_work']) && ((int) $runVar['settings']['postprocess_kill'] !== 0));
    $runVar['killswitch']['coll'] = (($runVar['settings']['collections_kill'] < $runVar['counts']['now']['collections_table']) && ((int) $runVar['settings']['collections_kill'] !== 0));

    $tOut->updateMonitorPane($runVar);

    // begin pane run execution
    if ($runVar['settings']['is_running'] === '1') {
        // run main updating function(s)
        try {
            $tRun->runPane('main', $runVar);
        } catch (Exception $e) {
            echo $e;
        }

        // run nzb-import
        try {
            $tRun->runPane('import', $runVar);
        } catch (Exception $e) {
            echo $e;
        }

        // run postprocess_releases amazon
        try {
            $tRun->runPane('amazon', $runVar);
        } catch (Exception $e) {
            echo $e;
        }

        // respawn IRCScraper if it has been killed
        try {
            $tRun->runPane('scraper', $runVar);
        } catch (Exception $e) {
            echo $e;
        }

        // update tv and theaters
        try {
            $tRun->runPane('updatetv', $runVar);
        } catch (Exception $e) {
            echo $e;
        }

        // run these if complete sequential not set
        if ((int) $runVar['constants']['sequential'] !== 2) {
            // fix names
            try {
                $tRun->runPane('fixnames', $runVar);
            } catch (Exception $e) {
                echo $e;
            }

            // dehash releases
            try {
                $tRun->runPane('dehash', $runVar);
            } catch (Exception $e) {
                echo $e;
            }

            // Remove crap releases.
            try {
                $tRun->runPane('removecrap', $runVar);
            } catch (Exception $e) {
                echo $e;
            }

            // run postprocess_releases additional
            try {
                $tRun->runPane('ppadditional', $runVar);
            } catch (Exception $e) {
                echo $e;
            }

            // run postprocess_releases non amazon
            try {
                $tRun->runPane('nonamazon', $runVar);
            } catch (Exception $e) {
                echo $e;
            }
        }
    } elseif ((int) $runVar['settings']['is_running'] === 0) {
        try {
            $tRun->runPane('notrunning', $runVar);
        } catch (Exception $e) {
            echo $e;
        }
    }

    $exit = Settings::settingValue('exit');
    if ((int) $exit === 0) {
        $runVar['counts']['iterations']++;
        sleep(10);
    } else {
        // Set counter to less than one so the loop will exit.
        $runVar['counts']['iterations'] = ($exit < 0) ? $exit : 0;
    }
}

function errorOnSQL()
{
    (new ColorCLI)->error(PHP_EOL.'Monitor encountered severe errors retrieving process data from MySQL. Please diagnose and try running again.'.PHP_EOL);
}

/**
 * @return bool|int|string
 */
function getTableRowCount(PDOStatement $ps, $table)
{
    if ($ps->execute([':table' => $table])) {
        $result = $ps->fetch();

        return is_numeric($result['count']) ? $result['count'] : 0;
    }

    return false;
}
