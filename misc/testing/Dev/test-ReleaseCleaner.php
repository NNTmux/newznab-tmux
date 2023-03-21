<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Release;
use App\Models\UsenetGroup;
use Blacklight\ManticoreSearch;
use Blacklight\ReleaseCleaning;

$message =
    'Shows old searchname vs new searchname for releases in a group using the releaseCleaning class. (Good for testing new regex)'.
    PHP_EOL.
    'Argument 1 is the group name.'.PHP_EOL.
    'Argument 2 is how many releases to limit this to, must be a number.'.PHP_EOL.
    'Argument 3 (true|false) true renames the releases, false only displays what could be changed.'.PHP_EOL.
    'Argument 4 is a categoryID, pass 0 to do all, 0010 to do misc, etc.'.PHP_EOL.
    'php '.$argv[0].' alt.binaries.comics.dcp 1000 false'.PHP_EOL;

if ($argc < 5) {
    exit($message);
}
if (! is_numeric($argv[2]) || ! is_numeric($argv[4])) {
    exit($message);
}
if (! in_array($argv[3], ['true', 'false'], false)) {
    exit($message);
}

$category = '';

$rename = false;
if ($argv[3] === 'true') {
    $rename = true;
}

$group = UsenetGroup::query()->where('name', '=', $argv[1])->select(['id'])->first();

if ($group === null) {
    exit('No group with name '.$argv[1].' found in the database.');
}

$releasesQuery = Release::query()->where('groups_id', $group->id);
if ((int) $argv[4] !== 0 && is_numeric($argv[4])) {
    $releasesQuery->where('categories_id', '=', $argv[4]);
}
$releasesQuery->select(['name', 'searchname', 'fromname', 'size', 'id', 'postdate'])->orderBy('postdate')->limit($argv[2]);

$releases = $releasesQuery->get();

if (\count($releases) === 0) {
    exit('No releases found in your database for group '.$argv[1].PHP_EOL);
}

$releaseCleaner = new ReleaseCleaning();
$manticore = new ManticoreSearch();

foreach ($releases as $release) {
    echo '.';
    $newName = $releaseCleaner->releaseCleaner($release->name, $release->fromname, $argv[1]);
    if (is_array($newName)) {
        $newName = $newName['cleansubject'];
    }
    if ($newName !== $release['searchname']) {
        echo 'Old name: '.$release->searchname.PHP_EOL;
        echo 'New name: '.$newName.PHP_EOL.PHP_EOL;

        if ($rename === true) {
            Release::query()->where('id', '=', $release->id)->update(['searchname' => $newName]);
            $manticore->updateRelease($release->id);
        }
    }
}
