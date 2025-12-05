<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
use Illuminate\Support\Facades\DB;

$consoletools = new ConsoleTools;
$colorCli = new ColorCLI;
$ran = false;

if (isset($argv[1]) && $argv[1] === 'all' && isset($argv[2]) && $argv[2] === 'true') {
    $ran = true;
    $where = '';
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        echo 'Truncating tables';
        DB::select('TRUNCATE TABLE consoleinfo');
        DB::select('TRUNCATE TABLE gamesinfo');
        DB::select('TRUNCATE TABLE movieinfo');
        DB::select('TRUNCATE TABLE video_data');
        DB::select('TRUNCATE TABLE musicinfo');
        DB::select('TRUNCATE TABLE bookinfo');
        DB::select('TRUNCATE TABLE release_nfos');
        DB::select('TRUNCATE TABLE xxxinfo');
        DB::select('TRUNCATE TABLE videos');
        DB::select('TRUNCATE TABLE videos_aliases');
        DB::select('TRUNCATE TABLE tv_info');
        DB::select('TRUNCATE TABLE tv_episodes');
        DB::select('TRUNCATE TABLE anidb_info');
    }
    $colorCli->header('Resetting all postprocessing');
    $qry = DB::select('SELECT id FROM releases');
    $affected = 0;
    $total = \count($qry);
    foreach ($qry as $releases) {
        DB::update(
            sprintf(
                '
						UPDATE releases
						SET consoleinfo_id = NULL, gamesinfo_id = 0, imdbid = NULL, musicinfo_id = NULL,
							bookinfo_id = NULL, videos_id = 0, tv_episodes_id = 0, xxxinfo_id = 0, passwordstatus = -1, haspreview = -1,
							jpgstatus = 0, videostatus = 0, audiostatus = 0, nfostatus = -1
						WHERE id = %d',
                $releases->id
            )
        );
        $consoletools->overWritePrimary('Resetting Releases:  '.$consoletools->percentString(++$affected, $total));
    }
}
if (isset($argv[1]) && ($argv[1] === 'consoles' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE consoleinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Console postprocessing');
        $where = ' WHERE consoleinfo_id IS NOT NULL AND categories_id BETWEEN '.Category::GAME_ROOT.' AND '.Category::GAME_OTHER;
    } else {
        $colorCli->header('Resetting all failed Console postprocessing');
        $where = ' WHERE consoleinfo_id IN (-2, 0) AND categories_id BETWEEN '.Category::GAME_ROOT.' AND '.Category::GAME_OTHER;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    if (! empty($qry)) {
        $total = \count($qry);
    } else {
        $total = 0;
    }
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET consoleinfo_id = NULL WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Console Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' consoleinfoIDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'games' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE gamesinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Games postprocessing');
        $where = ' WHERE gamesinfo_id != 0 AND categories_id = 4050';
    } else {
        $colorCli->header('Resetting all failed Games postprocessing');
        $where = ' WHERE gamesinfo_id IN (-2, 0) AND categories_id = 4050';
    }

    $qry = DB::select('SELECT id FROM releases'.$where);

    $total = 0;
    if (! empty($qry)) {
        $total = \count($qry);
    }

    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET gamesinfo_id = 0 WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Games Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' gameinfo_IDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'movies' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE movieinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Movie postprocessing');
        $where = ' WHERE imdbid IS NOT NULL AND categories_id BETWEEN '.Category::MOVIE_ROOT.' AND '.Category::MOVIE_OTHER;
    } else {
        $colorCli->header('Resetting all failed Movie postprocessing');
        $where = ' WHERE imdbid IN (-2, 0) AND categories_id BETWEEN '.Category::MOVIE_ROOT.' AND '.Category::MOVIE_OTHER;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    if (! empty($qry)) {
        $total = \count($qry);
    } else {
        $total = 0;
    }
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET imdbid = NULL WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Movie Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' imdbIDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'music' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE musicinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Music postprocessing');
        $where = ' WHERE musicinfo_id IS NOT NULL AND categories_id BETWEEN '.Category::MUSIC_ROOT.' AND '.Category::MUSIC_OTHER;
    } else {
        $colorCli->header('Resetting all failed Music postprocessing');
        $where = ' WHERE musicinfo_id IN (-2, 0) AND categories_id BETWEEN '.Category::MUSIC_ROOT.' AND '.Category::MUSIC_OTHER;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    $total = \count($qry);
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update(sprintf('UPDATE releases SET musicinfo_id = NULL WHERE id = %s ', $releases->id));
        $consoletools->overWritePrimary('Resetting Music Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' musicinfo_ids reset.');
}
if (isset($argv[1]) && ($argv[1] === 'misc' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Additional postprocessing');
        $where = ' WHERE ((haspreview != -1 AND haspreview != 0) OR (passwordstatus != -1 AND passwordstatus != 0) OR jpgstatus != 0 OR videostatus != 0 OR audiostatus != 0)';
    } else {
        $colorCli->header('Resetting all failed Additional postprocessing');
        $where = ' WHERE ((haspreview < -1 OR haspreview = 0) OR (passwordstatus < -1 OR passwordstatus = 0) OR jpgstatus < 0 OR videostatus < 0 OR audiostatus < 0)';
    }

    $where .= ' AND categories_id IN ('.Category::OTHER_MISC.','.Category::OTHER_HASHED.')';

    $colorCli->primary('SELECT id FROM releases'.$where);
    $qry = DB::select('SELECT id FROM releases'.$where);
    if (! empty($qry)) {
        $total = \count($qry);
    } else {
        $total = 0;
    }
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET passwordstatus = -1, haspreview = -1, jpgstatus = 0, videostatus = 0, audiostatus = 0 WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' Releases reset.');
}
if (isset($argv[1]) && ($argv[1] === 'tv' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::delete('DELETE v, va FROM videos v INNER JOIN videos_aliases va ON v.id = va.videos_id WHERE type = 0');
        DB::select('TRUNCATE TABLE tv_info');
        DB::select('TRUNCATE TABLE tv_episodes');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all TV postprocessing');
        $where = ' WHERE videos_id != 0 AND tv_episodes_id != 0 AND categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER;
    } else {
        $colorCli->header('Resetting all failed TV postprocessing');
        $where = ' WHERE tv_episodes_id < 0 AND categories_id BETWEEN '.Category::TV_ROOT.' AND '.Category::TV_OTHER;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    if (! empty($qry)) {
        $total = \count($qry);
    } else {
        $total = 0;
    }
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET videos_id = 0, tv_episodes_id = 0 WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting TV Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' Video IDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'anime' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE anidb_info');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Anime postprocessing');
        $where = ' WHERE categories_id = '.Category::TV_ANIME;
    } else {
        $colorCli->header('Resetting all failed Anime postprocessing');
        $where = ' WHERE anidbid BETWEEN -2 AND -1 AND categories_id = '.Category::TV_ANIME;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    if (! empty($qry)) {
        $total = \count($qry);
    } else {
        $total = 0;
    }
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET anidbid = NULL WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Anime Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' anidbIDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'books' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE bookinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all Book postprocessing');
        $where = ' WHERE bookinfo_id IS NOT NULL AND categories_id BETWEEN '.Category::BOOKS_ROOT.' AND '.Category::BOOKS_UNKNOWN;
    } else {
        $colorCli->header('Resetting all failed Book postprocessing');
        $where = ' WHERE bookinfo_id IN (-2, 0) AND categories_id BETWEEN '.Category::BOOKS_ROOT.' AND '.Category::BOOKS_UNKNOWN;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    $total = \count($qry);
    $concount = 0;
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET bookinfo_id = NULL WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting Book Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' bookinfoIDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'xxx' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE xxxinfo');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all XXX postprocessing');
        $where = ' WHERE xxxinfo_id != 0 AND categories_id BETWEEN '.Category::XXX_ROOT.' AND '.Category::XXX_X264;
    } else {
        $colorCli->header('Resetting all failed XXX postprocessing');
        $where = ' WHERE xxxinfo_id IN (-2, 0) AND categories_id BETWEEN '.Category::XXX_ROOT.' AND '.Category::XXX_X264;
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    $concount = 0;
    $total = \count($qry);
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET xxxinfo_id = 0 WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting XXX Releases:  '.$consoletools->percentString(
            ++$concount,
            $total
        ));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' xxxinfo_IDs reset.');
}
if (isset($argv[1]) && ($argv[1] === 'nfos' || $argv[1] === 'all')) {
    $ran = true;
    if (isset($argv[3]) && $argv[3] === 'truncate') {
        DB::select('TRUNCATE TABLE release_nfos');
    }
    if (isset($argv[2]) && $argv[2] === 'true') {
        $colorCli->header('Resetting all NFO postprocessing');
        $where = ' WHERE nfostatus != -1';
    } else {
        $colorCli->header('Resetting all failed NFO postprocessing');
        $where = ' WHERE nfostatus < -1';
    }

    $qry = DB::select('SELECT id FROM releases'.$where);
    $concount = 0;
    $total = \count($qry);
    foreach ($qry as $releases) {
        DB::update('UPDATE releases SET nfostatus = -1 WHERE id = '.$releases->id);
        $consoletools->overWritePrimary('Resetting NFO Releases:  '.$consoletools->percentString(++$concount, $total));
    }
    $colorCli->header(PHP_EOL.number_format($concount).' NFOs reset.');
}

if ($ran === false) {
    $colorCli->error(
        'This script will reset postprocessing per category.'.PHP_EOL
        .'It can also truncate the associated tables.'.PHP_EOL
            .'To reset only those that have previously failed, those without covers, samples, previews, etc. use the second argument false.'.PHP_EOL
            .'To reset even those previously post processed, use the second argument true.'.PHP_EOL
            .'To truncate the associated table, use the third argument truncate.'.PHP_EOL.PHP_EOL
            .'php reset_postprocessing.php consoles true    ...: To reset all consoles.'.PHP_EOL
            .'php reset_postprocessing.php games true       ...: To reset all games.'.PHP_EOL
            .'php reset_postprocessing.php movies true      ...: To reset all movies.'.PHP_EOL
            .'php reset_postprocessing.php music true       ...: To reset all music.'.PHP_EOL
            .'php reset_postprocessing.php misc true        ...: To reset all misc.'.PHP_EOL
            .'php reset_postprocessing.php tv true          ...: To reset all tv.'.PHP_EOL
            .'php reset_postprocessing.php anime true       ...: To reset all anime.'.PHP_EOL
            .'php reset_postprocessing.php books true       ...: To reset all books.'.PHP_EOL
            .'php reset_postprocessing.php xxx true         ...: To reset all xxx.'.PHP_EOL
            .'php reset_postprocessing.php nfos true        ...: To reset all nfos.'.PHP_EOL
            .'php reset_postprocessing.php all true         ...: To reset everything.'.PHP_EOL
    );
    exit();
}
echo PHP_EOL;
