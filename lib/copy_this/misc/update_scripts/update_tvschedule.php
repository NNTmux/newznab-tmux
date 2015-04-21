<?php

// Run this once per day.
require_once("config.php");

(new \TvAnger(['Echo' => true]))->updateSchedule();