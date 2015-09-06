<?php

require_once './config.php';

$page = new AdminPage();
$book = new Books();
$gen = new Genres();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

if (isset($_REQUEST["id"]))
{
	$id = $_REQUEST["id"];
	$b = $book->getBookInfo($id);

	if (!$b) {
		$page->show404();
	}

	switch($action)
	{
	    case 'submit':
	    	$coverLoc = WWW_DIR."covers/book/".$id.'.jpg';

			if($_FILES['cover']['size'] > 0)
			{
				$tmpName = $_FILES['cover']['tmp_name'];
				$file_info = getimagesize($tmpName);
				if(!empty($file_info))
				{
					move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
				}
			}

			$_POST['cover'] = (file_exists($coverLoc)) ? 1 : 0;
			$_POST['publishdate'] = (empty($_POST['publishdate']) || !strtotime($_POST['publishdate'])) ? $con['publishdate'] : date("Y-m-d H:i:s", strtotime($_POST['publishdate']));
			$book->update($id, $_POST["title"], $_POST['asin'], $_POST['url'], $_POST["author"], $_POST["publisher"], $_POST["publishdate"], $_POST["cover"]);

			header("Location:".WWW_TOP."/book-list.php");
	        die();
	    break;
	    case 'view':
	    default:
			$page->title = "Book Edit";
			$page->smarty->assign('book', $b);
		break;
	}
}

$page->content = $page->smarty->fetch('book-edit.tpl');
$page->render();
