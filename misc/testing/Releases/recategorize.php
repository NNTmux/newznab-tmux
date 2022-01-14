<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use App\Models\Release;
use Blacklight\Categorize;
use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;

$colorCli = new ColorCLI();

if (! (isset($argv[1]) && ($argv[1] === 'all' || $argv[1] === 'misc' || preg_match('/\([\d, ]+\)/', $argv[1]) || is_numeric($argv[1])))) {
    $colorCli->error(
        "\nThis script will attempt to re-categorize releases and is useful if changes have been made to Category.php.\n"
        ."No updates will be done unless the category changes\n"
        ."An optional last argument, test, will display the number of category changes that would be made\n"
        ."but will not update the database.\n\n"
        ."php $argv[0] all                     ...: To process all releases.\n"
        ."php $argv[0] misc                    ...: To process all releases in misc categories.\n"
        ."php $argv[0] 155                     ...: To process all releases in groupid 155.\n"
        ."php $argv[0] '(155, 140)'            ...: To process all releases in groupids 155 and 140.\n"
    );
    exit();
}

reCategorize($argv);

function reCategorize($argv)
{
    $colorCli = new ColorCLI();
    $update = true;

    if (isset($argv[1]) && (is_numeric($argv[1]) || preg_match('/\([\d, ]+\)/', $argv[1]))) {
        $colorCli->header('Categorizing all releases in '.$argv[1].' using searchname. This can take a while, be patient.');
    } elseif (isset($argv[1]) && $argv[1] === 'misc') {
        $colorCli->header('Categorizing all releases in misc categories using searchname. This can take a while, be patient.');
    } else {
        $colorCli->header('Categorizing all releases using searchname. This can take a while, be patient.');
    }
    $timeStart = now();
    if (isset($argv[1]) && (is_numeric($argv[1]) || preg_match('/\([\d, ]+\)/', $argv[1]) || $argv[1] === 'misc')) {
        $chgCount = categorizeRelease($update, true, $argv);
    } else {
        $chgCount = categorizeRelease($update, true, $argv);
    }
    $time = now()->diffInSeconds($timeStart);
    if ($update === true) {
        $colorCli->header('Finished re-categorizing '.number_format($chgCount).' releases in '.$time.' seconds, using the searchname.').PHP_EOL;
    } else {
        $colorCli->header('Finished re-categorizing in '.$time.' seconds , using the searchname.'.PHP_EOL
            .'This would have changed '.number_format($chgCount).' releases but no updates were done.').PHP_EOL;
    }
}

function categorizeRelease($update = true, $echoOutput = false, $argv): int
{
    $otherCats = implode(',', Category::OTHERS_GROUP);
    $query = Release::query()->select(['id', 'searchname', 'fromname', 'groups_id', 'categories_id']);
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $query->where('groups_id', $argv[1]);
    } elseif (isset($argv[1]) && preg_match('/\([\d, ]+\)/', $argv[1])) {
        $query->whereIn('groups_id', $argv[1]);
    } elseif (isset($argv[1]) && $argv[1] === 'misc') {
        $query->whereIn('categories_id', [$otherCats]);
    }
    if (isset($argv[2]) && $argv[2] === 'test') {
        $update = false;
    }
    $total = $query->count();
    $colorCli = new ColorCLI();
    $colorCli->header('Categorizing ['.$total.'] releases. This can take a while, be patient.');
    $consoleTools = new ConsoleTools();
    $relCount = $chgCount = 0;
    if ($total > 0) {
        $query->chunk('100', function ($results) use ($update, $relCount, $chgCount) {
            $cat = new Categorize();
            foreach ($results as $result) {
                $catId = $cat->determineCategory($result->groups_id, $result->searchname, $result->fromname);
                if ((int) $result->categories_id !== (int) $catId['categories_id']) {
                    if ($update === true) {
                        Release::query()->where('id', $result->id)->update([
                            'iscategorized' => 1,
                            'videos_id' => 0,
                            'tv_episodes_id' => 0,
                            'imdbid' => null,
                            'musicinfo_id' => null,
                            'consoleinfo_id' => null,
                            'gamesinfo_id' => 0,
                            'bookinfo_id' => 0,
                            'anidbid' => null,
                            'xxxinfo_id' => 0,
                            'categories_id' => $catId['categories_id'],
                        ]);
                        $release = Release::find($result->id);
                        if (! empty($release)) {
                            $release->retag($catId['tags']);
                        }
                        \Blacklight\NameFixer::echoChangedReleaseName([
                            'new_name' => $result->searchname,
                            'old_name' => $result->searchname,
                            'new_category' => $catId['categories_id'],
                            'old_category' => $result->categories_id,
                            'group' => $result->group->name,
                            'releases_id' => $result->id,
                            'method' => 'Recategorize',
                        ]);
                    }
                    $chgCount++;
                }
                $relCount++;
                echo '*';
            }
        });

        if ($echoOutput) {
            $consoleTools->overWritePrimary('Re-Categorized: ['.number_format($chgCount).'] '.$consoleTools->percentString($relCount, $total).PHP_EOL);
        }
    }

    return $chgCount;
}
