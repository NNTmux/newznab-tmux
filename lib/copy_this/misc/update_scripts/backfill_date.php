<?php
/*
  DESCRIPTION:
    This script is an alternative to backfill.php.
    It allows you to backfill based on a specific date, bypassing
    the "backfill target" setting for each group, used by backfill.php

  PURPOSE:
    If you are backfilling many groups over a long span of time, the # of days
    set as your backfill target can become quickly outdated, resulting in
    potential gaps in your database. In this case, it may be more practical
    to specify a date explicity and just let the backfill work from there.

  USAGE:
    $ php backfill_date.php 2011-05-15
    => Script will backfill ALL active groups from May 15, 2011

    $ php backfill_date.php 2011-05-15 alt.binaries.games.xbox
    => Script will backfill ONLY a.b.games.xbox from May 15, 2011
*/

require_once("config.php");

$time = 0;

if (isset($argv[1]))
    $time = strtotime($argv[1]);

if (($time > 1) && ($time < time())) {
    $groupName = (isset($argv[2]) ? $argv[2] : '');

    if (isset($argv[3]) && $argv[3] == true)
        $regexOnly = true;
    else
        $regexOnly = false;

  $backfill = new Backfill();
  $backfill->backfillAllGroups($groupName, strtotime($argv[1]), $regexOnly);
} else {
  echo "You must provide a backfill date in the format YYYY-MM-DD to use backfill_date.php\n";
  echo "example: backfill_date.php 2002-04-27 alt.binaries.games.xbox true\n";
  echo "This will backfill your index with everything posted to a.b.g.x since April 27, 2002 that matches system regex\n";
  echo "If you choose not to provide a groupname, all active groups will be backfilled.\n";
  echo "\nIf you do not want to use a date, use the backfill.php script instead.\n";
}