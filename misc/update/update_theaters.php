<?php
//run this once per day
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\Movie;

$m = new Movie(['Echo' => true]);
$m->updateUpcoming();

