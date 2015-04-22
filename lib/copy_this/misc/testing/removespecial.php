<?php

define('FS_ROOT', realpath(dirname(__FILE__)));

$echoonly = false;
$limitedtotoday = true;
$verbose = true;

$p = new Parsing($echoonly, $limitedtotoday, $verbose);
$p->removeSpecial();
