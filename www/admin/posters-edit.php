<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use app\models\MultigroupPosters;
use nntmux\processing\ProcessReleasesMultiGroup;

$page = new AdminPage();
$relPosters = new ProcessReleasesMultiGroup(['Settings' => $page->settings]);

// Set the current action.
$action = $_REQUEST['action'] ?? 'view';

switch ($action) {
	case 'submit':
		if ($_POST['id'] === '') {
			// Add a new mg poster.
			$poster = MultigroupPosters::query()->insert([
				'poster' => $_POST['poster'],
			]);
		} else {
			// Update an existing mg poster.
			$poster = MultigroupPosters::query()->update(['poster' => $_POST['poster'], 'id' => $_POST['id']]);
		}

		header('Location:' . WWW_TOP . '/posters-list.php');
		break;

	case 'view':
	default:
		if (!empty($_GET['id'])) {
			$page->title = 'MultiGroup Poster Edit';
			$poster = MultigroupPosters::query()->where('id', '=', $_GET['id'])->firstOrFail();
		} else {
			$page->title = 'MultiGroup Poster Add';
			$poster = '';
		}
		$page->smarty->assign('poster', $poster);
		break;
}

$page->content = $page->smarty->fetch('posters-edit.tpl');
$page->render();
