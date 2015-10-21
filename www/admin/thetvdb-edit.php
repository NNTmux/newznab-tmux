<?php

require_once("config.php");

use newznab\TheTVDB;

$page = new AdminPage();
$TheTVDB = new TheTVDB();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
	case 'submit':

		$TheTVDB->updateSeries($_POST['tvdbid'], $_POST['actors'], $_POST['airsday'], $_POST['airstime'], $_POST['contentrating'], $_POST['firstaired'], $_POST['genre'], $_POST['imdbid'], $_POST['network'], $_POST['overview'], $_POST['rating'], $_POST['ratingcount'], $_POST['runtime'], $_POST['seriesname'], $_POST['status']);

		if(isset($_POST['from']) && !empty($_POST['from']))
		{
			header("Location:".$_POST['from']);
			exit;
		}

		header("Location:".WWW_TOP."/thetvdb-list.php");
	break;

	case 'view':
	default:

		if (isset($_GET["id"]))
		{
			$page->title = "TheTVDB Edit";
			$page->smarty->assign('series', $TheTVDB->getSeriesInfoByID($_GET["id"]));
		}

	break;
}

$page->title="Edit TheTVDB Data";
$page->content = $page->smarty->fetch('thetvdb-edit.tpl');
$page->render();

