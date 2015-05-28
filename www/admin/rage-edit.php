<?php

require_once("config.php");

$page = new AdminPage();
$tvrage = new TvRage();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
		case 'update':
			$tvrage->refreshRageInfo($_GET["id"]);

			if(isset($_GET['from']) && !empty($_GET['from']))
			{
				header("Location:".$_GET['from']);
				exit;
			}

			header("Location:".WWW_TOP."/rage-edit.php?id=".$_GET["id"]);
			exit;


    case 'submit':
		if ($_POST["id"] == "")
		{
			$imgbytes = "";
			if($_FILES['imagedata']['size'] > 0)
			{
				$fileName = $_FILES['imagedata']['name'];
				$tmpName  = $_FILES['imagedata']['tmp_name'];
				$fileSize = $_FILES['imagedata']['size'];
				$fileType = $_FILES['imagedata']['type'];

				//
				// check the uploaded file is actually an image.
				//
				$file_info = getimagesize($tmpName);
				if(!empty($file_info))
				{
					$fp = fopen($tmpName, 'r');
					$imgbytes = fread($fp, filesize($tmpName));
					fclose($fp);
				}
			}

			$tvrage->add($_POST["rageID"], $_POST["releasetitle"], $_POST["description"], $_POST["genre"], $_POST['country'], $imgbytes);
		}
		else
		{
			$imgbytes = "";
			if($_FILES['imagedata']['size'] > 0)
			{
				$fileName = $_FILES['imagedata']['name'];
				$tmpName  = $_FILES['imagedata']['tmp_name'];
				$fileSize = $_FILES['imagedata']['size'];
				$fileType = $_FILES['imagedata']['type'];

				//
				// check the uploaded file is actually an image.
				//
				$file_info = getimagesize($tmpName);
				if(!empty($file_info))
				{
					$fp = fopen($tmpName, 'r');
					$imgbytes = fread($fp, filesize($tmpName));
					fclose($fp);
				}
			}

			$tvrage->update($_POST["id"], $_POST["rageID"], $_POST["releasetitle"], $_POST["description"], $_POST["genre"], $_POST['country'], $imgbytes);
		}

		if(isset($_POST['from']) && !empty($_POST['from']))
		{
			header("Location:".$_POST['from']);
			exit;
		}

		header("Location:".WWW_TOP."/rage-list.php");
        break;
    case 'view':
    default:

			if (isset($_GET["id"]))
			{
				$page->title = "Tv Rage Edit";
				$id = $_GET["id"];

				$rage = $tvrage->getByID($id);
				$page->smarty->assign('rage', $rage);
			}

	   	break;
}

$page->title="Add/Edit TV Rage Show Data";
$page->content = $page->smarty->fetch('rage-edit.tpl');
$page->render();

