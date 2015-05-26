<?php
require_once(dirname(__FILE__) . "/../bin/config.php");

(new \PreHash(['Echo' => true]))->checkPre((isset($argv[1]) && is_numeric($argv[1]) ? $argv[1] : false));