<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Binaries;

$bin = new Binaries();

$page->title = 'Binary Black/Whitelist List';

$binlist = $bin->getBlacklist(false);
$page->smarty->assign('binlist', $binlist);

$page->content = $page->smarty->fetch('binaryblacklist-list.tpl');
$page->render();
