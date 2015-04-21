<?php
define('FS_ROOT', realpath(dirname(__FILE__)));

$echoonly = false;
$limittotoday = false;
$verbose = true;

$p = new Parsing($echoonly, $limittotoday, $verbose);
$p->process();
