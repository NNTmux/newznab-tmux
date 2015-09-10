<?php

// Run this once per day.
require_once("config.php");

(new \TvRage(['Echo' => true]))->updateSchedule();