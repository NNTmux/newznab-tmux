<?php

//This script will update all records in the gamesinfo table

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\Games;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$game = new Games(['Echo' => true]);
$colorCli = new ColorCLI;

$res = $pdo->query(
    sprintf('SELECT id, title FROM gamesinfo WHERE cover = 0 ORDER BY id DESC LIMIT 100')
);
$total = $res->rowCount();
if ($total > 0) {
    $colorCli->header('Updating game covers for '.number_format($total).' releases.');

    foreach ($res as $arr) {
        $starttime = now()->timestamp;
        $gameInfo = $game->parseTitle($arr['title']);
        if ($gameInfo !== false) {
            $colorCli->primary('Looking up: '.$gameInfo['release']);
            $gameData = $game->updateGamesInfo($gameInfo);
            if ($gameData === false) {
                $colorCli->primary($gameInfo['release'].' not found');
            } else {
                if (file_exists(storage_path('covers/games/').$gameData.'.jpg')) {
                    $pdo->exec(sprintf('UPDATE gamesinfo SET cover = 1 WHERE id = %d', $arr['id']));
                }
            }
        }

        // amazon limits are 1 per 1 sec
        $diff = floor((now()->timestamp - $starttime) * 1000000);
        if (1000000 - $diff > 0) {
            $colorCli->alternate('Sleeping');
            usleep(1000000 - $diff);
        }
    }
}
