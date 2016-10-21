<?php
use nntmux\Forum;

$forum = new Forum;

if (!$page->users->isLoggedIn())
	$page->show403();

if ($page->isPostBack())
{
	if (!isset($_POST["addSubject"]) || empty($_POST["addSubject"])) {
		$page->show403();
	}
	if (!isset($_POST["addMessage"]) || empty($_POST["addMessage"])) {
		$page->show403();
	}

	$forum->add(0, $page->users->currentUserId(), $_POST["addSubject"], $_POST["addMessage"]);
	header("Location:".WWW_TOP."/forum");
	die();
}
if (!empty($_GET['lock']) || !empty($_GET['unlock'])) {
	$lock = $_GET['lock'];
	$unlock = $_GET['unlock'];
}

if (isset($lock)) {
	$forum->lockUnlockTopic($lock, 1);
	header("Location:" . WWW_TOP . "/forum");
	die();
} elseif (isset($unlock)) {
	$forum->lockUnlockTopic($unlock, 0);
	header("Location:" . WWW_TOP . "/forum");
	die();
}

$browsecount = $forum->getBrowseCount();

$offset = (isset($_REQUEST["offset"]) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST["offset"] : 0;

$results = [];
$results = $forum->getBrowseRange($offset, ITEMS_PER_PAGE);

$page->smarty->assign('pagertotalitems',$browsecount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/forum?offset=");
$page->smarty->assign('pagerquerysuffix', "#results");
$page->smarty->assign('privateprofiles', ($page->settings->getSetting('privateprofiles') == 1) ? true : false );

$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);
$page->smarty->assign('results',$results);

$page->meta_title = "Forum";
$page->meta_keywords = "forum,chat,posts";
$page->meta_description = "Forum";

$page->content = $page->smarty->fetch('forum.tpl');
$page->render();

