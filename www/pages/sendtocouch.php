<?php

use newznab\CouchPotato;

if (!$page->users->isLoggedIn())
	$page->show403();

if (empty($_GET["id"]))
	$page->show404();

$cp = new CouchPotato($page);

if (empty($cp->cpurl))
	$page->show404();

if (empty($cp->cpapi))
	$page->show404();

$guid = $_GET["id"];

$cp->sendToCouchPotato($guid);
var_dump($cp->sendToCouchPotato($guid));

