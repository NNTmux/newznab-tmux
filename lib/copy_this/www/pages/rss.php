<?php

$category = new Category;
$releases = new Releases;

//
// user has to either be logged in, or using rsskey
//

//
// if no content id provided then show user the rss selection page
//
if (!isset($_GET["t"]) && !isset($_GET["rage"]) && !isset($_GET["anidb"]))
{
	//
	// must be logged in to view this help page
	//
	if (!$users->isLoggedIn())
		$page->show403();

	$page->title = "Rss Feeds";
	$page->meta_title = "Rss Nzb Feeds";
	$page->meta_keywords = "view,nzb,description,details,rss,atom";
	$page->meta_description = "View available Rss Nzb feeds.";

	$categorylist = $category->get(true, $page->userdata["categoryexclusions"]);
	$page->smarty->assign('categorylist',$categorylist);

	$parentcategorylist = $category->getForMenu($page->userdata["categoryexclusions"]);
	$page->smarty->assign('parentcategorylist',$parentcategorylist);

	$page->content = $page->smarty->fetch('rssdesc.tpl');
	$page->render();

}
//
// user requested a feed, ensure either logged in or passing a valid token
//
else
{
	$uid = -1;
	$rsstoken = -1;
	if (!$users->isLoggedIn())
	{
		if (!isset($_GET["i"]) || !isset($_GET["r"]))
			$page->show403();

		$res = $users->getByIdAndRssToken($_GET["i"], $_GET["r"]);
		if (!$res)
			$page->show403();

		$uid = $_GET["i"];
		$rsstoken = $_GET["r"];
		$maxrequests = $res['apirequests'];
	}
	else
	{
		$uid = $page->userdata["id"];
		$rsstoken = $page->userdata["rsstoken"];
		$maxrequests = $page->userdata['apirequests'];
	}

	//
	// A hash of the users ip to record against the api hit
	//
	$hosthash = "";
	if ($page->site->storeuserips == 1)
		$hosthash = $users->getHostHash($_SERVER["REMOTE_ADDR"], $page->site->siteseed);

	$apirequests = $users->getApiRequests($uid);
	if ($apirequests['num'] > $maxrequests)
	{
		$page->show429($apirequests['nextrequest']);
	} else {
		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);
	}

	//
	// valid or logged in user, get them the requested feed
	//
	if (isset($_GET["dl"]) && $_GET["dl"] == "1")
		$page->smarty->assign("dl","1");

	$usercat = -1;
	if (isset($_GET["t"]))
		$usercat = ($_GET["t"]==0 ? -1 : $_GET["t"]);

	$userrage = -1;
	$useranidb = -1;
	$userseries = -1;
	if (isset($_GET["rage"]))
	{
		$userrage = ($_GET["rage"]==0 ? -1 : $_GET["rage"]+0);
	} elseif (isset($_GET["anidb"])) {
		$useranidb = ($_GET["anidb"]==0 ? -1 : $_GET["anidb"]+0);
	}

	$usernum = 100;
	if (isset($_GET["num"]))
		$usernum = $_GET["num"]+0;

	if (isset($_GET["del"]) && $_GET["del"] == "1")
		$page->smarty->assign("del","1");

	$userairdate = -1;
	if (isset($_GET["airdate"]))
		$userairdate = $_GET["airdate"]+0;

	$page->smarty->assign('uid',$uid);
	$page->smarty->assign('rsstoken',$rsstoken);

	if ($usercat == -3)
	{
		$page->smarty->assign('rsstitle',"My Shows Feed");
		$catexclusions = $users->getCategoryExclusion($uid);
		$reldata = $releases->getShowsRss($usernum, $uid, $catexclusions, $userairdate);
	}
	elseif ($usercat == -4)
	{
		$page->smarty->assign('rsstitle',"My Movies Feed");
		$catexclusions = $users->getCategoryExclusion($uid);
		$reldata = $releases->getMyMoviesRss($usernum, $uid, $catexclusions);
	}
	else
	{
		if ($usercat == -2)
			$page->smarty->assign('rsstitle',"My Cart Feed");

		$reldata = $releases->getRss(explode(",",$usercat), $usernum, $uid, $userrage, $useranidb, $userairdate);
	}
	$page->smarty->assign('releases',$reldata);
	header("Content-type: text/xml");

	$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
	echo trim($page->smarty->fetch('rss.tpl'));

}
