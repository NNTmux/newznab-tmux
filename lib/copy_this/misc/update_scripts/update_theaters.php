<?php
//run this once per day
require("config.php");
require_once(WWW_DIR.'/../misc/update_scripts/nix_scripts/tmux/lib/Film.php');

$m = new \Film(['Echo' => true]);
$m->updateUpcoming();

