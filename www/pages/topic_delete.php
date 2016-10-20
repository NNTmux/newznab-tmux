<?php

use nntmux\Forum;

if (!$page->users->isLoggedIn()) {
	$page->show403();
}
$forum = new Forum();
$id = $_GET['id'] + 0;

if (isset($id))
{
	$forum->deleteTopic($id);
	header("Location:" . WWW_TOP . "/forum");
}
