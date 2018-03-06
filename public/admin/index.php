<?php

use Blacklight\http\AdminPage;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

$page->title = 'Admin Hangout';
$page->content = $page->smarty->fetch('index.tpl');
$page->render();
