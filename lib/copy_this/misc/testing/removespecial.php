<?php

require_once dirname(__FILE__) . '/../../www/config.php';

$echoonly = false;
$limitedtotoday = true;
$verbose = true;

$p = new Parsing($echoonly, $limitedtotoday, $verbose);
$p->removeSpecial();
