<?php

require_once("config.php");
require_once(WWW_DIR."/lib/adminpage.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/sabnzbd.php");

$page = new AdminPage();
$sites = new Sites();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
	case 'submit':

		if (!empty($_POST['book_reqids'])) {
			// book_reqids is an array it needs to be a comma separated string, make it so.
			$_POST['book_reqids'] = is_array($_POST['book_reqids']) ?
				implode(', ', $_POST['book_reqids']) : $_POST['book_reqids'];
		}
		$error = "";
		$ret = $sites->update($_POST);
		if (is_int($ret))
		{
			if ($ret == Sites::ERR_BADUNRARPATH)
				$error = "The unrar path does not point to a valid binary";
			elseif ($ret == Sites::ERR_BADFFMPEGPATH)
				$error = "The ffmpeg path does not point to a valid binary";
			elseif ($ret == Sites::ERR_BADMEDIAINFOPATH)
				$error = "The mediainfo path does not point to a valid binary";
			elseif ($ret == Sites::ERR_BADNZBPATH)
				$error = "The nzb path does not point to a valid directory";
			elseif ($ret == Sites::ERR_DEEPNOUNRAR)
				$error = "Deep password check requires a valid path to unrar binary";
			elseif ($ret == Sites::ERR_BADTMPUNRARPATH)
				$error = "The temp unrar path is not a valid directory";
			elseif ($ret == Sites::ERR_BADLAMEPATH)
				$error = "The lame path is not a valid directory";
			elseif ($ret == Sites::ERR_SABCOMPLETEPATH)
				$error = "The sab complete path is not a valid directory";
		}

		if ($error == "")
		{
			$site = $ret;
			$returnid = $site->id;
			header("Location:".WWW_TOP."/site-edit.php?ID=".$returnid);
		}
		else
		{
			$page->smarty->assign('error', $error);
			$site = $sites->row2Object($_POST);
			$page->smarty->assign('fsite', $site);
		}

		break;
	case 'view':
	default:

		$page->title = "Site Edit";
		$site = $sites->get();
		$page->smarty->assign('fsite', $site);

		break;
}

$page->smarty->assign('yesno_ids', array(1,0));
$page->smarty->assign('yesno_names', array( 'Yes', 'No'));

$page->smarty->assign('passwd_ids', array(2,1,0));
$page->smarty->assign('passwd_names', array( 'Deep (requires unrar)', 'Shallow', 'None'));

$page->smarty->assign('rottentomatoquality_ids', array('thumbnail', 'profile', 'detailed', 'original'));
$page->smarty->assign('rottentomatoquality_names', array('Thumbnail', 'Profile', 'Detailed', 'Original'));

$page->smarty->assign('sabintegrationtype_ids', array(SABnzbd::INTEGRATION_TYPE_USER, SABnzbd::INTEGRATION_TYPE_SITEWIDE, SABnzbd::INTEGRATION_TYPE_NONE));
$page->smarty->assign('sabintegrationtype_names', array( 'User', 'Site-wide', 'None (Off)'));

$page->smarty->assign('sabapikeytype_ids', array(SABnzbd::API_TYPE_NZB,SABnzbd::API_TYPE_FULL));
$page->smarty->assign('sabapikeytype_names', array( 'Nzb Api Key', 'Full Api Key'));

$page->smarty->assign('sabpriority_ids', array(SABnzbd::PRIORITY_FORCE, SABnzbd::PRIORITY_HIGH, SABnzbd::PRIORITY_NORMAL, SABnzbd::PRIORITY_LOW));
$page->smarty->assign('sabpriority_names', array( 'Force', 'High', 'Normal', 'Low'));

$page->smarty->assign('curlproxytype_names', array( '', 'HTTP', 'SOCKS5'));

$page->smarty->assign('newgroupscan_names', array('Days','Posts'));
$page->smarty->assign('registerstatus_ids', array(Sites::REGISTER_STATUS_OPEN, Sites::REGISTER_STATUS_INVITE, Sites::REGISTER_STATUS_CLOSED));
$page->smarty->assign('registerstatus_names', array( 'Open', 'Invite', 'Closed'));
$page->smarty->assign('passworded_ids', array(0,1,2));
$page->smarty->assign('passworded_names', array( 'Dont show passworded or potentially passworded', 'Dont show passworded', 'Show everything'));

$page->smarty->assign('sphinxrebuildfreqday_days', array('', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'));

$page->smarty->assign('lookuplanguage_iso', array('en', 'de', 'es', 'fr', 'it', 'nl', 'pt', 'sv'));
$page->smarty->assign('lookuplanguage_names', array('English', 'Deutsch', 'Español', 'Français', 'Italiano', 'Nederlands', 'Português', 'Svenska'));

$page->smarty->assign('imdb_urls', array(0,1));
$page->smarty->assign('imdburl_names', array('imdb.com', 'akas.imdb.com'));

$page->smarty->assign('lookup_reqids_ids', array(0,1,2));
$page->smarty->assign('lookup_reqids_names', array('Disabled', 'Lookup Request IDs', 'Lookup Request IDs Threaded'));

// return a list of audiobooks, ebooks, technical and foreign books
$result = $page->settings->query("SELECT ID, title FROM category WHERE ID in (3030, 7010, 7040, 7060)");

// setup the display lists for these categories, this could have been static, but then if names changed they would be wrong
$book_reqids_ids = array();
$book_reqids_names = array();
foreach ($result as $bookcategory)
{
	$book_reqids_ids[]   = $bookcategory["ID"];
	$book_reqids_names[] = $bookcategory["title"];
}

// convert from a string array to an int array as we want to use int
$book_reqids_ids = array_map(create_function('$value', 'return (int)$value;'), $book_reqids_ids);
$page->smarty->assign('book_reqids_ids', $book_reqids_ids);
$page->smarty->assign('book_reqids_names', $book_reqids_names);

// convert from a list to an array as we need to use an array, but teh sites table only saves strings
$books_selected = explode(",", $page->site->book_reqids);

// convert from a string array to an int array
$books_selected = array_map(create_function('$value', 'return (int)$value;'), $books_selected);
$page->smarty->assign('book_reqids_selected', $books_selected);

$themelist = array();
$themes = scandir(WWW_DIR."/templates");
foreach ($themes as $theme)
	if (strpos($theme, ".") === false && is_dir(WWW_DIR."/templates/".$theme))
		$themelist[] = $theme;

$page->smarty->assign('themelist', $themelist);

if (strpos(NNTP_SERVER, "astra")===false)
	$page->smarty->assign('compress_headers_warning', "compress_headers_warning");

$page->content = $page->smarty->fetch('site-edit.tpl');
$page->render();