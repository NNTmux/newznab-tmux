<?php

require_once './config.php';


use nntmux\Users;
use nntmux\Releases;

$page = new AdminPage();
$users = new Users();
$releases = new Releases();

$page->title = "Site Stats";

$topgrabs = $users->getTopGrabbers();
$page->smarty->assign('topgrabs', $topgrabs);

$topdownloads = $releases->getTopDownloads();
$page->smarty->assign('topdownloads', $topdownloads);

$topcomments = $releases->getTopComments();
$page->smarty->assign('topcomments', $topcomments);

$recent = $releases->getRecentlyAdded();
$page->smarty->assign('recent', $recent);

$usersbymonth = $users->getUsersByMonth();
$page->smarty->assign('usersbymonth', $usersbymonth);

$usersbyrole = $users->getUsersByRole();
$page->smarty->assign('usersbyrole', $usersbyrole);
$page->smarty->assign('totusers', 0);
$page->smarty->assign('totrusers', 0);

$usersbyhosthash = $users->getUsersByHostHash();
$page->smarty->assign('usersbyhosthash', $usersbyhosthash);

$loginsbymonth = $users->getLoginCountsByMonth();
$page->smarty->assign('loginsbymonth', $loginsbymonth);

$page->content = $page->smarty->fetch('site-stats.tpl');
$page->render();

