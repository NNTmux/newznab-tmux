<?php
//This script will update all records in the cosole table

require_once dirname(__FILE__) . '/../../www/config.php';

use newznab\db\Settings;

$console = new Console(true);

$db = new Settings();

$res = $db->queryDirect(sprintf("SELECT searchname, ID from releases where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = %d ) ORDER BY id DESC LIMIT %d", Category::CAT_PARENT_GAME, Console::NUMTOPROCESSPERTIME));

if ($res != null) {
    while ($arr = $db->getAssocArray($res)) {
        $gameInfo = $console->parseTitle($arr['searchname']);
        if ($gameInfo !== false) {
            echo 'Searching ' . $gameInfo['release'] . '<br />';
            $game = $console->updateConsoleInfo($gameInfo);
            if ($game !== false) {
                echo "<pre>";
                print_r($game);
                echo "</pre>";
            } else {
                echo '<br />Game not found<br /><br />';
            }
        }
    }
}