<?php

$page->smarty->assign(['error' => '', 'username' => '', 'rememberme' => '']);

$page->meta_title = 'Login';
$page->meta_keywords = 'Login';
$page->meta_description = 'Login';
$page->content = $page->smarty->fetch('login.tpl');
$page->pagerender();
