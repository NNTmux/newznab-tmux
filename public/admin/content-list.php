<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\Contents;
use Blacklight\http\AdminPage;

$page = new AdminPage();
$contents = new Contents();
$contentlist = $contents->getAll();
$page->smarty->assign('contentlist', $contentlist);

$page->title = 'Content List';

$page->content = $page->smarty->fetch('content-list.tpl');
$page->render();
