<?php
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/groups.php");
require_once(WWW_DIR."/lib/genres.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/util.php");

$releases = new Releases;
$rc = new ReleaseComments;
$gen = new Genres;
$category = new Category;
$grp = new Groups;
$nzb = new NZB;
$movie = new Movie;


if ($page->site->apienabled != 1)
	showApiError(910);

//
// api functions
//
$function = "s";
if (isset($_GET["t"]))
{
	if ( $_GET["t"] == "details" || $_GET["t"] == "d")
		$function = "d";
	elseif ( $_GET["t"] == "comments" || $_GET["t"] == "comm")
		$function = "co";
	elseif ( $_GET["t"] == "commentadd" || $_GET["t"] == "commadd")
		$function = "ca";
	elseif ( $_GET["t"] == "get" || $_GET["t"] == "g")
		$function = "g";
	elseif ( $_GET["t"] == "getnfo" || $_GET["t"] == "gn")
		$function = "gn";
	elseif ($_GET["t"] == "search" || $_GET["t"] == "s" )
		$function = "s";
	elseif ($_GET["t"] == "caps" || $_GET["t"] == "c")
		$function = "c";
	elseif ($_GET["t"] == "tvsearch" || $_GET["t"] == "tv")
		$function = "tv";
	elseif ($_GET["t"] == "movie" || $_GET["t"] == "m")
		$function = "m";
	elseif ($_GET["t"] == "music" || $_GET["t"] == "mu")
		$function = "mu";
	elseif ($_GET["t"] == "book" || $_GET["t"] == "b")
		$function = "b";
	elseif ($_GET["t"] == "register" || $_GET["t"] == "r")
		$function = "r";
	elseif ($_GET["t"] == "user" || $_GET["t"] == "u")
		$function = "u";
	elseif ($_GET["t"] == "cartadd" || $_GET["t"] == "uca")
		$function = "uca";
	elseif ($_GET["t"] == "cartdel" || $_GET["t"] == "ucd")
		$function = "ucd";
	else
		showApiError(202);
}
else
	showApiError(200);

//
// page is accessible only by the apikey, or logged in users.
//
$user="";
$uid="";
$apikey="";
$hosthash = "";
$catexclusions = array();
if (!$users->isLoggedIn())
{
	if ($function != "c" && $function != "r")
	{
		if (!isset($_GET["apikey"]))
			showApiError(200);

		$res = $users->getByRssToken($_GET["apikey"]);
		if (!$res)
			showApiError(100);

		$uid=$res["ID"];
		$apikey=$_GET["apikey"];
		$catexclusions = $users->getCategoryExclusion($uid);
		$maxrequests=$res['apirequests'];
	}
}
else
{
	$uid=$page->userdata["ID"];
	$apikey=$page->userdata["rsstoken"];
	$catexclusions = $page->userdata["categoryexclusions"];
	$maxrequests= $page->userdata['apirequests'];

	//
	// A hash of the users ip to record against the api hit
	//
	if ($page->site->storeuserips == 1)
		$hosthash = $users->getHostHash($_SERVER["REMOTE_ADDR"], $page->site->siteseed);
}


//
// record user access to the api, if its been called by a user (i.e. capabilities request do not require
// a user to be logged in or key provided)
//
if ($uid != "")
{
	$users->updateApiAccessed($uid);
	$apirequests = $users->getApiRequests($uid);
	if ($apirequests['num'] > $maxrequests) {
		showApiError(500, $apirequests['nextrequest']);
	}
}

$page->smarty->assign("uid",$uid);
$page->smarty->assign("rsstoken",$apikey);
if (isset($_GET["extended"]) && $_GET["extended"] == "1")
	$page->smarty->assign('extended','1');
if (isset($_GET["del"]) && $_GET["del"] == "1")
	$page->smarty->assign("del","1");
if (isset($_GET["attrs"]))
	$page->smarty->assign("attrs",array_flip (explode(",", $_GET["attrs"])));

//
// output is either json or xml
//
$outputtype = "xml";
if (isset($_GET["o"]))
	if ($_GET["o"] == "json")
		$outputtype = "json";

switch ($function)
{
	//
	// search releases
	//
	case "s":
		if (isset($_GET["q"]) && $_GET["q"]=="")
			showApiError(200);

		$sort = "";
		if (isset($_GET["sort"]))
		{
			if ($_GET["sort"]=="")
				showApiError(200);
			elseif (strpos($_GET["sort"], "_") === false)
				showApiError(201);
			else
				$sort = $_GET["sort"];
		}

		$maxage = -1;
		if (isset($_GET["maxage"]))
		{
			if ($_GET["maxage"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxage"]))
				showApiError(201);
			else
				$maxage = $_GET["maxage"];
		}

		$minsize = -1;
		if (isset($_GET["minsize"]))
		{
			if ($_GET["minsize"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["minsize"]))
				showApiError(201);
			else
				$minsize = $_GET["minsize"];
		}

		$maxsize = -1;
		if (isset($_GET["maxsize"]))
		{
			if ($_GET["maxsize"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxsize"]))
				showApiError(201);
			else
				$maxsize = $_GET["maxsize"];
		}

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$categoryId = array();
		if (isset($_GET["cat"]))
			$categoryId = explode(",",$_GET["cat"]);
		else
			$categoryId[] = -1;

		$groupName = array();
		if (isset($_GET["group"]))
			$groupName = explode(",",$_GET["group"]);

		$limit = 100;
		if (isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] < 100)
			$limit = $_GET["limit"];

		$offset = 0;
		if (isset($_GET["offset"]) && is_numeric($_GET["offset"]))
			$offset = $_GET["offset"];

		if (isset($_GET["q"]))
			$relData = $releases->search(
				$_GET['q'], -1, -1, -1, $categoryId, -1, -1, 0, 0, -1, -1, $offset, $limit, '', $maxage, $catexclusions
			);
		else

			$orderby = array();
			$orderby[0] = "post	date";
			$orderby[1] = "asc";
			$totrows = $releases->getBrowseCount($categoryId, $maxage, $catexclusions, $groupName);
			$reldata = $releases->getBrowseRange($categoryId, $offset, $limit, "", $maxage, $catexclusions, $groupName);
			if ($totrows > 0 && count($reldata))
				$reldata[0]["_totalrows"] = $totrows;


		$page->smarty->assign('offset',$offset);
		$page->smarty->assign('releases',$reldata);
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$output = trim($page->smarty->fetch('apiresult.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}
		break;

	//
	// search tv releases
	//
	case "tv":
		if (isset($_GET["q"]) && $_GET["q"]=="")
			showApiError(200);

		$categoryId = array();
		if (isset($_GET["cat"]))
			$categoryId = explode(",",$_GET["cat"]);
		else
			$categoryId[] = -1;

		$maxage = -1;
		if (isset($_GET["maxage"]))
		{
			if ($_GET["maxage"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxage"]))
				showApiError(201);
			else
				$maxage = $_GET["maxage"];
		}

		if (isset($_GET["rid"]) && $_GET["rid"]=="")
			showApiError(200);
		if (isset($_GET["season"]) && $_GET["season"]=="")
			showApiError(200);
		if (isset($_GET["ep"]) && $_GET["ep"]=="")
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$limit = 100;
		if (isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] < 100)
			$limit = $_GET["limit"];

		$offset = 0;
		if (isset($_GET["offset"]) && is_numeric($_GET["offset"]))
			$offset = $_GET["offset"];

		$reldata = $releases->searchbyRageId((isset($_GET["rid"]) ? $_GET["rid"] : "-1"), (isset($_GET["season"]) ? $_GET["season"] : "")
			, (isset($_GET["ep"]) ? $_GET["ep"] : ""), $offset, $limit, (isset($_GET["q"]) ? $_GET["q"] : ""), $categoryId, $maxage );

		$page->smarty->assign('offset',$offset);
		$page->smarty->assign('releases',$reldata);
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$output = trim($page->smarty->fetch('apiresult.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}
		break;

	//
	// search movie releases
	//
	case "m":
		if (isset($_GET["q"]) && $_GET["q"]=="")
			showApiError(200);

		$categoryId = array();
		if (isset($_GET["cat"]))
			$categoryId = explode(",",$_GET["cat"]);
		else
			$categoryId[] = -1;

		$maxage = -1;
		if (isset($_GET["maxage"]))
		{
			if ($_GET["maxage"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxage"]))
				showApiError(201);
			else
				$maxage = $_GET["maxage"];
		}
		if (isset($_GET["imdbid"]) && $_GET["imdbid"]=="")
			showApiError(200);

		$genre = "";
		if (isset($_GET["genre"]))
		{
			$genre = $_GET["genre"];
		}

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$limit = 100;
		if (isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] < 100)
			$limit = $_GET["limit"];

		$offset = 0;
		if (isset($_GET["offset"]) && is_numeric($_GET["offset"]))
			$offset = $_GET["offset"];
		$reldata = $releases->searchbyImdbId((isset($_GET["imdbid"]) ? $_GET["imdbid"] : "-1"), $offset, $limit, (isset($_GET["q"]) ? $_GET["q"] : ""), $categoryId, $genre, $maxage );

		$page->smarty->assign('offset',$offset);
		$page->smarty->assign('releases',$reldata);

		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$output = trim($page->smarty->fetch('apiresult.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}
		break;

	//
	// search music releases
	//
	case "mu":
		if (isset($_GET["artist"]) && $_GET["artist"]=="" && isset($_GET["album"]) && $_GET["album"]=="")
			showApiError(200);
		$categoryId = array();
		if (isset($_GET["cat"]))
			$categoryId = explode(",",$_GET["cat"]);
		else
			$categoryId[] = -1;

		$maxage = -1;
		if (isset($_GET["maxage"]))
		{
			if ($_GET["maxage"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxage"]))
				showApiError(201);
			else
				$maxage = $_GET["maxage"];
		}

		$genreId = array();
		if (isset($_GET["genre"]))
			$genreId = explode(",",$_GET["genre"]);
		else
			$genreId[] = -1;

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$limit = 100;
		if (isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] < 100)
			$limit = $_GET["limit"];

		$offset = 0;
		if (isset($_GET["offset"]) && is_numeric($_GET["offset"]))
			$offset = $_GET["offset"];
		$reldata = $releases->searchAudio((isset($_GET["artist"]) ? $_GET["artist"] : ""), (isset($_GET["album"]) ? $_GET["album"] : ""), (isset($_GET["label"]) ? $_GET["label"] : ""), (isset($_GET["track"]) ? $_GET["track"] : ""), (isset($_GET["year"]) ? $_GET["year"] : ""), $genreId, $offset, $limit, $categoryId, $maxage );

		$page->smarty->assign('offset',$offset);
		$page->smarty->assign('releases',$reldata);
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$output = trim($page->smarty->fetch('apiresult.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}
		break;

	//
	// search book releases
	//
	case "b":
		if (isset($_GET["author"]) && $_GET["author"]=="" && isset($_GET["title"]) && $_GET["title"]=="")
			showApiError(200);

		$maxage = -1;
		if (isset($_GET["maxage"]))
		{
			if ($_GET["maxage"]=="")
				showApiError(200);
			elseif (!is_numeric($_GET["maxage"]))
				showApiError(201);
			else
				$maxage = $_GET["maxage"];
		}

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$limit = 100;
		if (isset($_GET["limit"]) && is_numeric($_GET["limit"]) && $_GET["limit"] < 100)
			$limit = $_GET["limit"];

		$offset = 0;
		if (isset($_GET["offset"]) && is_numeric($_GET["offset"]))
			$offset = $_GET["offset"];
		$reldata = $releases->searchBook((isset($_GET["author"]) ? $_GET["author"] : ""), (isset($_GET["title"]) ? $_GET["title"] : ""), $offset, $limit, $maxage );

		$page->smarty->assign('offset',$offset);
		$page->smarty->assign('releases',$reldata);
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$output = trim($page->smarty->fetch('apiresult.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}
		break;


	//
	// get nzb
	//
	case "g":
		if (!isset($_GET["id"]))
			showApiError(200);

		$del = "";
		if (isset($_GET["del"]) && $_GET["del"] == "1")
			$del = "&del=1";

		$reldata = $releases->getByGuid($_GET["id"]);
		if ($reldata)
		{
			header("Location:".WWW_TOP."/getnzb?i=".$uid."&r=".$apikey."&id=".$reldata["guid"].$del);
		}
		else
		{
			showApiError(300);
		}
		break;

	//
	// add to cart
	//
	case "uca":
		if (!isset($_GET["id"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$reldata = $releases->getByGuid($_GET["id"]);

		if (!$reldata)
			showApiError(300);

		$ret = $users->addCart($uid, $reldata["ID"]);

		if ($ret == 0)
			showApiError(310);

		$content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$content.= "<cartadd id=\"".$ret."\" />\n";

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $content;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($content));
		}

		break;

	//
	// del from cart
	//
	case "ucd":
		if (!isset($_GET["id"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$reldata = $releases->getByGuid($_GET["id"]);
		if (!$reldata)
			showApiError(300);

		$cartdata = $users->getCart($uid, $reldata["ID"]);
		if (!$cartdata)
			showApiError(300);

		$guid = array();
		$guid[] = $_GET["id"];
		$users->delCartByGuid($guid ,$uid);

		$content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$content.= "<cartdel id=\"".$reldata["guid"]."\" />\n";

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $content;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($content));
		}

		break;

	//
	// get nfo
	//
	case "gn":
		if (!isset($_GET["id"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$reldata = $releases->getByGuid($_GET["id"]);
		if (!$reldata)
			showApiError(300);

		$nfo = $releases->getReleaseNfo($reldata["ID"], true);
		if (!$nfo)
			showApiError(300);

		$nforaw = cp437toUTF($nfo["nfo"]);
		$page->smarty->assign('release',$reldata);
		$page->smarty->assign('nfo',$nfo);
		$page->smarty->assign('nfoutf',$nforaw);

		if (isset($_GET["raw"]))
		{
			header("Content-type: text/x-nfo");
			header("Content-Disposition: attachment; filename=".str_replace(" ", "_", $reldata["searchname"]).".nfo");
			echo $nforaw;
			die();
		}
		else
		{
			$page->smarty->assign('rsstitle',"NFO");
			$page->smarty->assign('rssdesc',"NFO");
			$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
			$content = trim($page->smarty->fetch('apinfo.tpl'));

			if ($outputtype == "xml")
			{
				header("Content-type: text/xml");
				echo $content;
			}
			else
			{
				header('Content-type: application/json');
				echo json_encode(responseXmlToObject($content));
			}
		}
		break;

	//
	// get comments
	//
	case "co":
		if (!isset($_GET["id"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$data = $rc->getCommentsByGuid($_GET["id"]);
		if ($data)
			$reldata = $data;
		else
			$reldata = array();

		$page->smarty->assign('comments',$reldata);
		$page->smarty->assign('rsstitle',"API Comments");
		$page->smarty->assign('rssdesc',"API Comments");
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$content = trim($page->smarty->fetch('apicomments.tpl'));


		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $content;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($content));
		}

		break;

	//
	// add comment
	//
	case "ca":
		if (!isset($_GET["id"]))
			showApiError(200);

		if (!isset($_GET["text"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$reldata = $releases->getByGuid($_GET["id"]);
		if ($reldata)
		{
			$ret = $rc->addComment($reldata["ID"], $reldata["gid"], $_GET["text"], $uid, $_SERVER['REMOTE_ADDR']);

			$content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
			$content.= "<commentadd id=\"".$ret."\" />\n";

			if ($outputtype == "xml")
			{
				header("Content-type: text/xml");
				echo $content;
			}
			else
			{
				header('Content-type: application/json');
				echo json_encode(responseXmlToObject($content));
			}
		}
		else
		{
			showApiError(300);
		}

		break;

	//
	// get user
	//
	case "u":
		if (!isset($_GET["username"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$udata = $users->getByUsername($_GET["username"]);
		if ($udata)
		{
			$content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
			$content.= "<user username=\"".$udata["username"]."\" grabs=\"".$udata["grabs"]."\" role=\"".$udata["rolename"]."\" apirequests=\"".$udata["apirequests"]."\" downloadrequests=\"".$udata["downloadrequests"]."\" movieview=\"".$udata["movieview"]."\" musicview=\"".$udata["musicview"]."\" consoleview=\"".$udata["consoleview"]."\" createddate=\"".$udata["createddate"]."\" />\n";

			if ($outputtype == "xml")
			{
				header("Content-type: text/xml");
				echo $content;
			}
			else
			{
				header('Content-type: application/json');
				echo json_encode(responseXmlToObject($content));
			}
		}
		else
		{
			showApiError(300);
		}

		break;

	//
	// get individual nzb details
	//
	case "d":
		if (!isset($_GET["id"]))
			showApiError(200);

		$users->addApiRequest($uid, $_SERVER['REQUEST_URI'], $hosthash);

		$data = $releases->getByGuid($_GET["id"]);

		if ($data)
		{
			$mov = '';
			if ($data['imdbID'] != '')
			{
				$mov = $movie->getMovieInfo($data['imdbID']);
				$page->smarty->assign('mov',$mov);
			}

			$reldata[] = $data;
		}
		else
			$reldata = array();

		$page->smarty->assign('releases',$reldata);
		$page->smarty->assign('rsshead',$page->smarty->fetch('rssheader.tpl'));
		$content = trim($page->smarty->fetch('apidetail.tpl'));

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $content;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($content));
		}

		break;

	//
	// capabilities request
	//
	case "c":
		$parentcatlist = $category->getForMenu();
		$page->smarty->assign('parentcatlist',$parentcatlist);

		$grps = $grp->getActive();
		$page->smarty->assign('grps',$grps);

		$genres = $gen->getGenres('', true);
		$page->smarty->assign('genres',$genres);

		header("Content-type: text/xml");
		$output = $page->smarty->fetch('apicaps.tpl');

		if ($outputtype == "xml")
		{
			header("Content-type: text/xml");
			echo $output;
		}
		else
		{
			header('Content-type: application/json');
			echo json_encode(responseXmlToObject($output));
		}


		break;

	//
	// register request
	//
	case "r":
		if (!isset($_GET["email"]) || $_GET["email"]=="")
			showApiError(200);

		if ($page->site->registerstatus != Sites::REGISTER_STATUS_OPEN)
			showApiError(104);

		//
		// Check email is valid format
		//
		if (!$users->isValidEmail($_GET["email"]))
			showApiError(106);

		//
		// check email isnt taken
		//
		$ret = $users->getByEmail($_GET["email"]);
		if (isset($ret["ID"]))
			showApiError(105);

		//
		// create uname/pass and register
		//
		$username = $users->generateUsername($_GET["email"]);
		$password = $users->generatePassword();

		//
		// register
		//
		$userdefault = $users->getDefaultRole();
		$uid = $users->signup($username, $password, $_GET["email"], $_SERVER['REMOTE_ADDR'], $userdefault['ID'], "", $userdefault['defaultinvites'], "", false, false, false, true);
		$userdata = $users->getById($uid);
		if (!$userdata)
			showApiError(107);

		header("Content-type: text/xml");
		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<register username=\"".$username."\" password=\"".$password."\" apikey=\"".$userdata["rsstoken"]."\"/>\n";

		break;

	default:
		showApiError(202);
		break;
}

function showApiError($errcode=900, $errtext="")
{
	switch ($errcode)
	{
		case 100:
			$errtext = "Incorrect user credentials";
			break;
		case 101:
			$errtext = "Account suspended";
			break;
		case 102:
			$errtext = "Insufficient priviledges/not authorized";
			break;
		case 103:
			$errtext = "Registration denied";
			break;
		case 104:
			$errtext = "Registrations are closed";
			break;
		case 105:
			$errtext = "Invalid registration (Email Address Taken)";
			break;
		case 106:
			$errtext = "Invalid registration (Email Address Bad Format)";
			break;
		case 107:
			$errtext = "Registration Failed (Data error)";
			break;
		case 200:
			$errtext = "Missing parameter";
			break;
		case 201:
			$errtext = "Incorrect parameter";
			break;
		case 202:
			$errtext = "No such function";
			break;
		case 203:
			$errtext = "Function not available";
			break;
		case 300:
			$errtext = "No such item";
			break;
		case 310:
			$errtext = "Item already exists";
			break;
		case 500:
			$errtext = "Request limit reached. Retry in ".ceil($errtext/60)." minutes.";
			break;
		case 501:
			$errtext = "Download limit reached";
			break;
		case 910:
			$errtext = "Api Disabled";
			break;
		default:
			$errtext = "Unknown error";
			break;
	}
	if (in_array($errcode, array(500,501)))
	{
		header('HTTP/1.1 429 Too Many Requests');
		header('Retry-After: '.$errtext);
	}
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<error code=\"$errcode\" description=\"$errtext\"/>\n";
	die();
}