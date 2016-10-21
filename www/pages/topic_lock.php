<?php

use nntmux\Forum;

if (!$page->users->isLoggedIn()) {
	$page->show403();
}
$forum = new Forum();

$lock = !empty($_GET['lock']);
$unlock = !empty($_GET['unlock']);

if ($lock) {
	$forum->lockUnlockTopic($lock, 1);
	header("Location:" . WWW_TOP . "/forum");
	die();
} elseif ($unlock) {
	$forum->lockUnlockTopic($unlock, 0);
	header("Location:" . WWW_TOP . "/forum");
	die();
}
