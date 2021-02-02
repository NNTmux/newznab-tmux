<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use App\Models\Release;
use Blacklight\Categorize;
use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
use Illuminate\Support\Facades\DB;

$colorCli = new ColorCLI();

if (! (isset($argv[1]) && ($argv[1] === 'all' || $argv[1] === 'misc' || preg_match('/\([\d, ]+\)/', $argv[1]) || is_numeric($argv[1])))) {
    $colorCli->warning(PHP_EOL.'This script will attempt to re-categorize releases and is useful if changes have been made to Category.php.');
    $colorCli->error(PHP_EOL
        .'Argument 1: groups - (all, misc, groupid -- 155 or groupids "(155, 140)")'.PHP_EOL
        .'Argument 2: (optional) test or notest - if test, will display the number of category changes that would be made but will not update the database'.PHP_EOL
        .'Argument 3: (optional) offset - What starting release *number* to begin recategorizing. Releases are sorted by id DESC'.PHP_EOL
        .'Argument 4: (optional) limit - How many releases to process before stopping'.PHP_EOL.PHP_EOL
        .'php '.$argv[0].' all                     ...: To process all releases.'.PHP_EOL
        .'php '.$argv[0].' misc                    ...: To process all releases in misc categories.'.PHP_EOL
        .'php '.$argv[0].' 155                     ...: To process all releases in groupid 155.'.PHP_EOL
        .'php '.$argv[0].' "(155, 140)"            ...: To process all releases in groupids 155 and 140.'.PHP_EOL
        .'php '.$argv[0].' all notest 1000 100     ...: To process all releases from the most recent 1000th release. Stop after processing 100 releases'.PHP_EOL
    );
    exit();
}

reCategorize($argv);

function reCategorize($argv)
{
    [$path, $groups, $test, $offset, $limit] = array_pad($argv, 5, null);
    $colorCli = new ColorCLI();
    $where = '';
    $otherCats = implode(',', Category::OTHERS_GROUP);
    $update = true;

    if (isset($groups) && is_numeric($groups)) {
        $where = ' AND groups_id = '.$groups;
    } elseif (isset($groups) && preg_match('/\([\d, ]+\)/', $groups)) {
        $where = ' AND groups_id IN '.$groups;
    } elseif (isset($groups) && $groups === 'misc') {
        $where = sprintf(' AND categories_id IN (%s)', $otherCats);
    }

    if ($test === 'test') {
        $update = false;
    }

    if (isset($groups) && (is_numeric($groups) || preg_match('/\([\d, ]+\)/', $groups))) {
        $colorCli->header('Categorizing all releases in '.$groups.' using searchname. This can take a while, be patient.');
    } elseif (isset($groups) && $groups === 'misc') {
        $colorCli->header('Categorizing all releases in misc categories using searchname. This can take a while, be patient.');
    } else {
        $colorCli->header('Categorizing all releases using searchname. This can take a while, be patient.');
    }
    $timeStart = now();
    if (isset($groups) && (is_numeric($groups) || preg_match('/\([\d, ]+\)/', $groups) || $groups === 'misc')) {
        $chgCount = categorizeRelease(str_replace(' AND', 'WHERE', $where).' ORDER BY id DESC', $update, true);
    } else {
        $where = $offset !== null ? 'ORDER BY id DESC LIMIT '.$offset.', '.$limit : 'ORDER BY id DESC';
        $chgCount = categorizeRelease($where, $update, true);
    }
    $time = now()->diffInSeconds($timeStart);
    if ($update === true) {
        $colorCli->header('Finished re-categorizing '.number_format($chgCount).' releases in '.$time.' seconds, using the searchname.'.PHP_EOL);
    } else {
        $colorCli->header('Finished re-categorizing in '.$time.' seconds , using the searchname.'.PHP_EOL
            .'This would have changed '.number_format($chgCount).' releases but no updates were done.'.PHP_EOL);
    }
}

// Categorizes releases.
// Returns the quantity of categorized releases.
function categorizeRelease($where, $update = true, $echoOutput = false)
{
    global $colorCli;
    if ($echoOutput) {
        $colorCli->tmuxOrange('Memory limit is '.ini_get('memory_limit'), true);
    }

    $memoryLimit = str_replace(['G', 'M', 'K'], ['000000000', '000000', '000'], ini_get('memory_limit'));
    $cat = new Categorize();
    $consoleTools = new ConsoleTools();
    $relCount = $chgCount = 0;
    $consoleTools->primary($query = 'SELECT id, searchname, fromname, groups_id, categories_id FROM releases '.$where);
    $resRel = DB::select($query);
    $total = \count($resRel);
    if ($total > 0) {
        foreach ($resRel as $rowRel) {
            $catId = $cat->determineCategory($rowRel->groups_id, $rowRel->searchname, $rowRel->fromname);
            if ((int) $rowRel->categories_id !== $catId['categories_id']) {
                if ($update === true) {
                    DB::update(
                        sprintf(
                            '
							UPDATE releases
							SET iscategorized = 1,
								videos_id = 0,
								tv_episodes_id = 0,
								imdbid = NULL,
								musicinfo_id = NULL,
								consoleinfo_id = NULL,
								gamesinfo_id = 0,
								bookinfo_id = NULL,
								anidbid = NULL,
								xxxinfo_id = 0,
								categories_id = %d
							WHERE id = %d',
                            $catId['categories_id'],
                            $rowRel->id
                        )
                    );
                    $release = Release::find($rowRel->id);
                    if (! empty($release)) {
                        $release->retag($catId['tags']);
                    }
                    unset($release);
                }
                $chgCount++;
            }
            $relCount++;

            unset($rowRel);

            if ($echoOutput) {
                $consoleTools->overWritePrimary(
                    'Re-Categorized: ['.number_format($chgCount).'] '.$consoleTools->percentString($relCount, $total).
                    ' | MEM: '.$consoleTools->percentString(memory_get_usage(true) / 1000000, $memoryLimit / 1000000)
                );
            }

            if ($memoryLimit - memory_get_usage(true) < 40 * 1000000) {
                if ($echoOutput) {
                    $colorCli->alternate('Breaking due to less than 40M available free memory.', true);
                }

                exit(27);
            }
        }
    }
    if ($echoOutput !== false && $relCount > 0) {
        echo PHP_EOL;
    }

    return $chgCount;
}
