<?php
define('FS_ROOT', realpath(dirname(__FILE__)));

$p = new PreDB(true);
$p->nzpreUpdate();
$p->processReleases(2000);