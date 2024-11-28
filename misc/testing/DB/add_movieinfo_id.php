<?php

/**
 * This script will convert releases table imdbid column
 * from zerofilled int to varchar(15).
 */
use App\Models\MovieInfo;
use App\Models\Release;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$sql = Release::query()->whereNotNull('imdbid')->where('imdbid', '<>', '0000000')->get(['imdbid', 'id']);

DB::statement('
    DROP TABLE IF EXISTS movie_temp;
    CREATE TABLE movie_temp (
        releases_id INT(11),
        imdbid VARCHAR(15)
    )
    ENGINE=InnoDB
    DEFAULT CHARACTER SET utf8
    COLLATE utf8_unicode_ci;
    ');

$count = $sql->count();

echo 'Copying '.$count.' imdbid values'.PHP_EOL;

foreach ($sql as $movie) {
    DB::table('movie_temp')->insert(['releases_id' => $movie['id'], 'imdbid' => $movie['imdbid']]);
    echo '.';
}

echo PHP_EOL.'Finished copying '.$sql->count().' imdbid values'.PHP_EOL;

echo 'Updating releases table with new values'.PHP_EOL;

foreach (DB::table('movie_temp')->get() as $imdbid) {
    $movieinfo = MovieInfo::query()->where('imdbid', $imdbid->imdbid)->first(['id']);
    Release::query()->where('id', $imdbid->releases_id)->update(['movieinfo_id' => $movieinfo['id']]);
    echo '.';
}

echo 'Finished inserting new values into releases table'.PHP_EOL;

$check = Release::query()->whereNotNull('movieinfo_id')->count('id');

if ($check === $count) {
    DB::statement('DROP TABLE movie_temp');
}
