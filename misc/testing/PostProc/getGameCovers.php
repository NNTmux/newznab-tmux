<?php

//This script will update all records in the gamesinfo table

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Games;
use Blacklight\ColorCLI;

$pdo = DB::connection()->getPdo();
$game = new Games(['Echo' => true]);

$res = $pdo->query(
    sprintf('SELECT id, title FROM gamesinfo WHERE cover = 0 ORDER BY id DESC LIMIT 100')
);
$total = $res->rowCount();
if ($total > 0) {
    ColorCLI::header('Updating game covers for '.number_format($total).' releases.');

    foreach ($res as $arr) {
        $starttime = microtime(true);
        $gameInfo = $game->parseTitle($arr['title']);
        if ($gameInfo !== false) {
            ColorCLI::primary('Looking up: '.$gameInfo['release']);
            $gameData = $game->updateGamesInfo($gameInfo);
            if ($gameData === false) {
                ColorCLI::primary($gameInfo['release'].' not found');
            } else {
                if (file_exists(NN_COVERS.'games'.DS.$gameData.'.jpg')) {
                    $pdo->exec(sprintf('UPDATE gamesinfo SET cover = 1 WHERE id = %d', $arr['id']));
                }
            }
        }

        // amazon limits are 1 per 1 sec
        $diff = floor((microtime(true) - $starttime) * 1000000);
        if (1000000 - $diff > 0) {
            ColorCLI::alternate('Sleeping');
            usleep(1000000 - $diff);
        }
    }
}
