<?php

//This script will update all records in the consoleinfo table

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Console;
use App\Models\Category;
use Blacklight\ColorCLI;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$console = new Console(['Echo' => true]);

$res = $pdo->query(
    sprintf(
        'SELECT searchname, id FROM releases WHERE consoleinfo_id IS NULL AND categories_id
				BETWEEN %s AND %s ORDER BY id DESC',
        Category::GAME_ROOT,
        Category::GAME_OTHER
    )
);
if ($res instanceof \Traversable) {
    echo ColorCLI::header('Updating console info for '.number_format($res->rowCount()).' releases.');

    foreach ($res as $arr) {
        $starttime = microtime(true);
        $gameInfo = $console->parseTitle($arr['searchname']);
        if ($gameInfo !== false) {
            $game = $console->updateConsoleInfo($gameInfo);
            if ($game === false) {
                echo ColorCLI::primary($gameInfo['release'].' not found');
            }
        }

        // amazon limits are 1 per 1 sec
        $diff = floor((microtime(true) - $starttime) * 1000000);
        if (1000000 - $diff > 0) {
            echo ColorCLI::alternate('Sleeping');
            usleep(1000000 - $diff);
        }
    }
}
