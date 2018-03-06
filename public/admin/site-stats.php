<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\User;
use App\Models\Release;
use App\Models\Category;
use App\Models\UserRole;
use Blacklight\http\AdminPage;

$page = new AdminPage();

$page->title = 'Site Stats';

$topgrabs = User::getTopGrabbers();
$page->smarty->assign('topgrabs', $topgrabs);

$topdownloads = Release::getTopDownloads();
$page->smarty->assign('topdownloads', $topdownloads);

$topcomments = Release::getTopComments();
$page->smarty->assign('topcomments', $topcomments);

$recent = Category::getRecentlyAdded();
$page->smarty->assign('recent', $recent);

$usersbymonth = User::getUsersByMonth();
$page->smarty->assign('usersbymonth', $usersbymonth);

$usersbyrole = UserRole::getUsersByRole();
$page->smarty->assign('usersbyrole', $usersbyrole);
$page->smarty->assign('totusers', 0);
$page->smarty->assign('totrusers', 0);

$page->content = $page->smarty->fetch('site-stats.tpl');
$page->render();
