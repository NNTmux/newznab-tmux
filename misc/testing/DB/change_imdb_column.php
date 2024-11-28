<?php

/**
 * This script will convert releases table imdbid column
 * from zerofilled int to varchar(15).
 */
use App\Models\Release;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$sql = Release::query()->whereNotNull('imdbid')->where('imdbid', '<>', '0000000')->get(['imdbid', 'id']);

DB::unprepared('
    DROP TABLE IF EXISTS movie_temp;
    CREATE TABLE movie_temp (
        releases_id INT(11),
        imdbid VARCHAR(15)
    )
    ENGINE=InnoDB
    DEFAULT CHARACTER SET utf8
    COLLATE utf8_unicode_ci;
    ');
DB::commit();
$count = $sql->count();

echo 'Copying '.$count.' imdbid values'.PHP_EOL;

foreach ($sql as $movie) {
    DB::table('movie_temp')->insert(['releases_id' => $movie['id'], 'imdbid' => str_pad($movie['imdbid'], 7, '0', STR_PAD_LEFT)]);
    echo '.';
}

echo PHP_EOL.'Finished copying '.$sql->count().' imdbid values'.PHP_EOL;

DB::statement('ALTER TABLE releases DROP imdbid');
DB::statement('ALTER TABLE releases ADD imdbid VARCHAR(15) DEFAULT NULL');

echo 'Updating releases table with new values'.PHP_EOL;

foreach (DB::table('movie_temp')->get() as $imdbid) {
    DB::table('releases')->where('id', $imdbid->releases_id)->update(['imdbid' => $imdbid->imdbid]);
    echo '.';
}

echo 'Finished inserting new values into releases table'.PHP_EOL;

$check = Release::query()->whereNotNull('imdbid')->where('imdbid', '<>', '0000000')->count('id');

if ($check === $count) {
    DB::statement('DROP TABLE movie_temp');
}
