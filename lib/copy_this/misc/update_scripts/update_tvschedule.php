<?php

// Run this once per day.
require("config.php");
require_once(WWW_DIR.'/../misc/update_scripts/nix_scripts/tmux/lib/TvAnger.php');

$m = new \TvAnger(['Echo' => true]);
$m->updateUpcoming();
