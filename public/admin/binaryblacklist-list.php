<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Binaries;
use Blacklight\http\BasePage;

$page = new BasePage();
$page->setAdminPrefs();
$bin = new Binaries();

$page->title = 'Binary Black/Whitelist List';

$binlist = $bin->getBlacklist(false);
$page->smarty->assign('binlist', $binlist);

$page->content = $page->smarty->fetch('binaryblacklist-list.tpl');
$page->adminrender();
