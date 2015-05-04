<?php
require_once dirname(__FILE__) . '/../../www/config.php';

$p = new PreDB(true);
$p->nzpreUpdate();
$p->processReleases(2000);