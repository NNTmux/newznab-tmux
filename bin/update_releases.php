<?php
$time = TIME();
require("config.php");
require_once(WWW_DIR."/lib/releases.php");
//require_once(WWW_DIR."/lib/sphinx.php");

$releases = new Releases;
//$sphinx = new Sphinx();
$releases->processReleases();
//$sphinx->update();

$secs = TIME() - $time;
$mins = floor($secs / 60);
$hrs = floor($mins / 60);
$days = floor($hrs / 24);
$sec = floor($secs % 60);
$min = ($mins % 60);
$day = ($days % 24);
$hr = ($hrs % 24);

if ( $sec != 1 ) { $string_sec = "secs"; }
else { $string_sec = "sec"; }

if ( $min != 1 ) { $string_min = "mins"; }
else { $string_min = "min"; }

if ( $hr != 1 ) { $string_hr = "hrs"; }
else { $string_hr = "hr"; }

if ( $day != 1 ) { $string_day = "days"; }
else { $string_day = "day"; }

if ( $day > 0 ) { $time_string = "\033[38;5;160m$day\033[1;33m $string_day, \033[38;5;208m$hr\033[1;33m $string_hr, \033[1;31m$min\033[1;33m $string_min, \033[1;31m$sec\033[1;33m $string_sec."; }
elseif ( $hr > 0 ) { $time_string = "\033[38;5;208m$hr\033[1;33m $string_hr, \033[1;31m$min\033[1;33m $string_min, \033[1;31m$sec\033[1;33m $string_sec."; }
elseif ($min > 0 ) { $time_string = "\033[1;31m$min\033[1;33m $string_min, \033[1;31m$sec\033[1;33m $string_sec."; }
else { $time_string = "\033[1;31m$sec\033[1;33m $string_sec."; }


echo "\033[1;33mThis loop completed in: $time_string\033[0m";
?>
