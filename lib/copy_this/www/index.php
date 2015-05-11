<?php

if(is_file("config.php")) {
	require_once("config.php");
} else {
	if(is_dir("install")) {
		header("location: install");
		exit();
	}
}

require_once 'automated.config.php';

$page = new Page;
$users = new Users;

if ($page->site->style != "default" && file_exists(NN_WWW.'pages/'.$page->site->style."/".$page->page.'.php'))
	include(NN_WWW.'pages/'.$page->site->style."/".$page->page.'.php');
elseif (file_exists(WWW_DIR.'pages/'.$page->page.'.php'))
    include(NN_WWW.'pages/'.$page->page.'.php');
else
    $page->show404();
