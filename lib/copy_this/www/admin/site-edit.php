<?php

require_once("config.php");
require_once(WWW_DIR. "/lib/adminpage.php");
require_once(WWW_DIR. "/lib/framework/Settings.php");
require_once(WWW_DIR. "/lib/sabnzbd.php");

$page = new AdminPage();
$id = 0;
$error = '';

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
	case 'submit':
		$error = "";

		if (!empty($_POST['book_reqids'])) {
			// book_reqids is an array it needs to be a comma separated string, make it so.
			$_POST['book_reqids'] = is_array($_POST['book_reqids']) ?
				implode(', ', $_POST['book_reqids']) : $_POST['book_reqids'];
		}
		// update site table as always
		$ret = $page->settings->update($_POST);

		if (is_int($ret))
		{
			if ($ret == Settings::ERR_BADUNRARPATH)
				$error = "The unrar path does not point to a valid binary";
			else if ($ret == Settings::ERR_BADFFMPEGPATH)
				$error = "The ffmpeg path does not point to a valid binary";
			else if ($ret == Settings::ERR_BADMEDIAINFOPATH)
				$error = "The mediainfo path does not point to a valid binary";
			else if ($ret == Settings::ERR_BADNZBPATH)
				$error = "The nzb path does not point to an existing directory";
			else if ($ret == Settings::ERR_DEEPNOUNRAR)
				$error = "Deep password check requires a valid path to unrar binary";
			else if ($ret == Settings::ERR_BADTMPUNRARPATH)
				$error = "The temp unrar path is not a valid directory";
			else if ($ret == Settings::ERR_BADNZBPATH_UNREADABLE) {
				$error = "The nzb path cannot be read from. Check the permissions.";
			} else if ($ret == Settings::ERR_BADNZBPATH_UNSET) {
				$error = "The nzb path is required, please set it.";
			} else if ($ret == Settings::ERR_BAD_COVERS_PATH) {
				$error = 'The covers&apos; path is required and must exist. Please set it.';
			} else if ($ret == Settings::ERR_BAD_YYDECODER_PATH) {
				$error = 'The yydecoder&apos;s path must exist. Please set it or leave it empty.';
			}
		}

		if ($error == "") {
			$site     = $ret;
			$returnid = $site['ID'];
			header("Location:" . WWW_TOP . "/site-edit.php?id=" . $returnid);
		} else {
			$page->smarty->assign('error', $error);
			$page->smarty->assign('settings', $page->settings->rowsToArray($_POST));
		}
		break;
	case 'view':
	default:
		$page->title = "Site Edit";
		$site = $page->settings;
		$page->smarty->assign('site', $site);
		break;
}
if ($error === '') {
	$page->smarty->assign('error', '');
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
$page->smarty->assign('registerstatus_ids', array(Settings::REGISTER_STATUS_OPEN, Settings::REGISTER_STATUS_INVITE, Settings::REGISTER_STATUS_CLOSED));
$page->smarty->assign('registerstatus_names', array( 'Open', 'Invite', 'Closed'));
$page->smarty->assign('passworded_ids', array(0,1,2));
$page->smarty->assign('passworded_names', array( 'Dont show passworded or potentially passworded', 'Dont show passworded', 'Show everything'));

$page->smarty->assign('sphinxrebuildfreqday_days', array('', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'));

$page->smarty->assign('lookuplanguage_iso', array('en', 'de', 'es', 'fr', 'it', 'nl', 'pt', 'sv'));
$page->smarty->assign('lookuplanguage_names', array('English', 'Deutsch', 'Español', 'Français', 'Italiano', 'Nederlands', 'Português', 'Svenska'));

$page->smarty->assign('loggingopt_ids', array(0,1,2,3));
$page->smarty->assign('loggingopt_names', array ('Disabled', 'Log in DB only', 'Log both DB and file', 'Log only in file'));

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