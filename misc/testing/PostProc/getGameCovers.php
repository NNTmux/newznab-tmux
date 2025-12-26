<?php

// This script will update all records in the gamesinfo table

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Services\GamesService;

use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$game = new GamesService;


$res = $pdo->query(
    sprintf('SELECT id, title FROM gamesinfo WHERE cover = 0 ORDER BY id DESC LIMIT 100')
);
$total = $res->rowCount();
if ($total > 0) {
    cli()->header('Updating game covers for '.number_format($total).' releases.');

    foreach ($res as $arr) {
        $starttime = now()->timestamp;
        $gameInfo = $game->parseTitle($arr['title']);
        if ($gameInfo !== false) {
            cli()->primary('Looking up: '.$gameInfo['release']);
            $gameData = $game->updateGamesInfo($gameInfo);
            if ($gameData === false) {
                cli()->primary($gameInfo['release'].' not found');
            } else {
                if (file_exists(storage_path('covers/games/').$gameData.'.jpg')) {
                    $pdo->exec(sprintf('UPDATE gamesinfo SET cover = 1 WHERE id = %d', $arr['id']));
                }
            }
        }

        // Rate limiting - 1 per second
        $diff = floor((now()->timestamp - $starttime) * 1000000);
        if (1000000 - $diff > 0) {
            cli()->alternate('Sleeping');
            usleep(1000000 - $diff);
        }
    }
}
