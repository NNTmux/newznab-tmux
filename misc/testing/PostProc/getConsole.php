<?php

//This script will update all records in the consoleinfo table

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\Category;
use Blacklight\ColorCLI;
use Blacklight\Console;
use Illuminate\Support\Facades\DB;

$pdo = DB::connection()->getPdo();
$console = new Console(['Echo' => true]);
$colorCli = new ColorCLI();

$res = $pdo->query(
    sprintf(
        'SELECT searchname, id FROM releases WHERE consoleinfo_id IS NULL AND categories_id
				BETWEEN %s AND %s ORDER BY id DESC',
        Category::GAME_ROOT,
        Category::GAME_OTHER
    )
);
if ($res instanceof Traversable) {
    $colorCli->header('Updating console info for '.number_format($res->rowCount()).' releases.');

    foreach ($res as $arr) {
        $starttime = now()->timestamp;
        $gameInfo = $console->parseTitle($arr['searchname']);
        if ($gameInfo !== false) {
            $game = $console->updateConsoleInfo($gameInfo);
            if ($game === false) {
                $colorCli->primary($gameInfo['release'].' not found');
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
