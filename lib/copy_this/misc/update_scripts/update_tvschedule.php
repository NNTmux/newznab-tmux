<?php

// Run this once per day.
require_once("config.php");
require_once(WWW_DIR . '/../misc/update_scripts/nix_scripts/tmux/lib/TvAnger.php');

(new \TvAnger(['Echo' => true]))->updateSchedule();