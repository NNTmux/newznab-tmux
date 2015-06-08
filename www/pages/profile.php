<?php

$rc = new ReleaseComments;
$sab = new SABnzbd($page);
$nzbget = new NZBGet($page);

if (!$page->users->isLoggedIn())
	$page->show403();

$userid = 0;
if (isset($_GET["id"]))
	$userid = $_GET["id"] + 0;
elseif (isset($_GET["name"]))
{
	$res = $page->users->getByUsername($_GET["name"]);
	if ($res)
		$userid = $res["id"];
}
else
	$userid = $page->users->currentUserId();

$privileged = ($page->users->isAdmin($userid) || $page->users->isModerator($userid)) ? true : false;
$privateProfiles = ($page->settings->getSetting('privateprofiles') == 1) ? true : false;
$publicView = false;

if (!$privateProfiles || $privileged) {

	$altID = (isset($_GET['id']) && $_GET['id'] >= 0) ? (int) $_GET['id'] : false;
	$altUsername = (isset($_GET['name']) && strlen($_GET['name']) > 0) ? $_GET['name'] : false;

	// If both 'id' and 'name' are specified, 'id' should take precedence.
	if ($altID === false && $altUsername !== false) {
		$user = $page->users->getByUsername($altUsername);
		if ($user) {
			$altID = $user['id'];
		}
	} else if ($altID !== false) {
		$userid = $altID;
		$publicView = true;
	}
}



$data = $page->users->getById($userid);
if (!$data)
	$page->show404();

$invitedby = '';
if ($data["invitedby"] != "")
	$invitedby = $page->users->getById($data["invitedby"]);

$page->smarty->assign('apihits', $page->users->getApiRequests($userid));
$page->smarty->assign('grabstoday', $page->users->getDownloadRequests($userid));
$page->smarty->assign('userinvitedby',$invitedby);
$page->smarty->assign('user',$data);
$page->smarty->assign('privateprofiles', $privateProfiles);
$page->smarty->assign('publicview', $publicView);
$page->smarty->assign('privileged', $privileged);

$commentcount = $rc->getCommentCountForUser($userid);
$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
$page->smarty->assign('pagertotalitems',$commentcount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', "/profile?id=".$userid."&offset=");
$page->smarty->assign('pagerquerysuffix', "#comments");
$page->smarty->assign('privateprofiles', ($page->settings->getSetting('privateprofiles') == 1) ? true : false );

$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

$commentslist = $rc->getCommentsForUserRange($userid, $offset, ITEMS_PER_PAGE);
$page->smarty->assign('commentslist',$commentslist);

$downloadlist = $page->users->getDownloadRequestsForUserAndAllHostHashes($userid);
$page->smarty->assign('downloadlist',$downloadlist);


$exccats = $page->users->getCategoryExclusionNames($userid);
$page->smarty->assign('exccats', implode(",", $exccats));

$page->smarty->assign('saburl', $sab->url);
$page->smarty->assign('sabapikey', $sab->apikey);

$page->smarty->assign('nzbgeturl', $nzbget->url);
$page->smarty->assign('nzbgetusername', $nzbget->userName);
$page->smarty->assign('nzbgetpassword', $nzbget->password);


$sabapikeytypes = array(SABnzbd::API_TYPE_NZB=>'Nzb Api Key', SABnzbd::API_TYPE_FULL=>'Full Api Key');
if ($sab->apikeytype != "")
	$page->smarty->assign('sabapikeytype', $sabapikeytypes[$sab->apikeytype]);

$sabpriorities = array(SABnzbd::PRIORITY_FORCE=>'Force', SABnzbd::PRIORITY_HIGH=>'High',  SABnzbd::PRIORITY_NORMAL=>'Normal', SABnzbd::PRIORITY_LOW=>'Low', SABnzbd::PRIORITY_PAUSED=>'Paused');
if ($sab->priority != "")
	$page->smarty->assign('sabpriority', $sabpriorities[$sab->priority]);

$sabsettings = array(1=>'Site', 2=>'Cookie');
$page->smarty->assign('sabsetting', $sabsettings[($sab->checkCookie()===true?2:1)]);

$page->meta_title = "View User Profile";
$page->meta_keywords = "view,profile,user,details";
$page->meta_description = "View User Profile for ".$data["username"] ;

$page->content = $page->smarty->fetch('profile.tpl');
$page->render();

