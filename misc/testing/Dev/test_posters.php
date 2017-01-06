<?php
require_once realpath(dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\ReleasesMultiGroup;
use nntmux\utility\Utility;


$poster = 'mmmq@meh.com';

$relMgrp = new ReleasesMultiGroup();
$posters = Utility::convertMultiArray($relMgrp->getAllPosters(), "','");

var_dump($posters);