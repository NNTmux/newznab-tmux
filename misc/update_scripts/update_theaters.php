<?php
//run this once per day
require_once("config.php");

$m = new Film(['Echo' => true]);
$m->updateUpcoming();

