<?php

require_once './config.php';


use app\models\MgrPosters;

$page = new AdminPage();
$relPosters = new MgrPosters();

// Set the current action.
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action) {
	case 'submit':
		if (empty($_POST)) {
			// Add a new mgr poster.
				$relPosters->create(
					[
						'poster' => $_POST
					]
				);
			} else {
			// Update an existing mgr poster.
			$relPosters->update($_POST);
		}
		header("Location:".WWW_TOP."/posters-list.php");
		break;

	case 'view':
	default:
		if (isset($_GET["id"])) {
			$page->title = "MGR Poster Edit";
			$poster      = $_GET["poster"];
		} else {
			$page->title = "MGR Poster Add";
			$poster = [
				'poster' => ''
			];
		}
		$page->smarty->assign('poster', $poster);
		break;
}

$page->content = $page->smarty->fetch('posters-edit.tpl');
$page->render();
