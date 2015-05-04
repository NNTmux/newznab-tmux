<?php
require_once dirname(__FILE__) . '/../../www/config.php';

$echoonly = true;
$limittotoday = false;
$verbose = true;

$p = new Parsing($echoonly, $limittotoday, $verbose);
$p->cleanup();