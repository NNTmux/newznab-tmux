<?php

use Blacklight\http\BasePage;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

$page = new BasePage();

$page->setAdminPrefs();

$page->title = 'Admin Hangout';
$page->content = $page->smarty->fetch('index.tpl');
$page->adminrender();
