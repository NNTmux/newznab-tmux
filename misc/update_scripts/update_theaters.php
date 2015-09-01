<?php
//run this once per day
require_once("config.php");

$m = new Movie(['Echo' => true]);
$m->updateUpcoming();

