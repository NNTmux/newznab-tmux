<?php
require_once (NN_TMUX . 'lib' . DS . 'Film.php');
if (!$users->isLoggedIn()) {
	$page->show403();
}

$m = new Film();

if (!isset($_GET["id"])) {
	$_GET["id"] = 1;
}
$user = $users->getById($users->currentUserId());
$cpapi = $user['cp_api'];
$cpurl = $user['cp_url'];
$page->smarty->assign('cpapi', $cpapi);
$page->smarty->assign('cpurl', $cpurl);

$data = $m->getUpcoming($_GET["id"]);
//print_r(json_decode($data["info"])->movies);die();
if ($data["info"] == "") {
	$page->smarty->assign("nodata", "No upcoming data.");
} else {
	$data = json_decode($data["info"]);

	if (isset($data->error)) {
		$page->smarty->assign("nodata", $data->error);
	} else if (!isset($data->movies)) {
		$page->smarty->assign("nodata", 'Unspecified error.');
	} else {
		$page->smarty->assign('data', $data->movies);

		switch ($_GET["id"]) {
			case Film::SRC_BOXOFFICE;
				$page->title = "Box Office";
				break;
			case Film::SRC_INTHEATRE;
				$page->title = "In Theater";
				break;
			case Film::SRC_OPENING;
				$page->title = "Opening";
				break;
			case Film::SRC_UPCOMING;
				$page->title = "Upcoming";
				break;
			case Film::SRC_DVD;
				$page->title = "DVD Releases";
				break;
		}
	}
	$page->meta_title = "View upcoming theatre releases";
	$page->meta_keywords = "view,series,theatre,dvd";
	$page->meta_description = "View upcoming theatre releases";
}

/**
 * Replace _tmb.jpg with user setting from site edit.
 *
 * @param string $imageURL    The url to change.
 * @param string $userSetting The users's setting.
 *
 * @return string
 */
function replace_quality($imageURL, $userSetting)
{
	$types = array('thumbnail' => '_tmb.', 'profile' => '_pro.', 'detailed' => '_det.', 'original' => '_ori.');
	return preg_replace("#http://resizing\.flixster\.com(/[\w=+-]+){3}\.cloudfront\.net#i", "https://content6.flixster.com", str_replace('_tmb.', $types[$userSetting], $imageURL));
}

$page->content = $page->smarty->fetch('upcoming.tpl');
$page->render();