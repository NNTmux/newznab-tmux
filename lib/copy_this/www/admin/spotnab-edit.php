<?php

require_once("config.php");

$page = new AdminPage();
$spotnab = new SpotNab();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
    case 'add':
    		$page->title = "Spotnab Source Add";
				$source = array();
				$source["description"] = '';
				$row = $spotnab->getDefaultValue('spotnabsources','username');
				$source["username"] = $row[0]["Default"];
				$row = $spotnab->getDefaultValue('spotnabsources','useremail');
				$source["useremail"] = $row[0]["Default"];
				$row = $spotnab->getDefaultValue('spotnabsources','usenetgroup');
				$source["usenetgroup"] = $row[0]["Default"];
				$source["publickey"] = '';
				$page->smarty->assign('source', $source);
			break;
    case 'submit':
	    	if ($_POST["id"] == "")
	    	{
					$ret = $spotnab->addSource($_POST['description'], $_POST['username'], $_POST['useremail'], $_POST['usenetgroup'], $_POST['publickey']);
					header("Location:".WWW_TOP."/spotnab-list.php");
	    	}
	    	else
	    	{
					$ret = $spotnab->updateSource($_POST['id'],$_POST['description'], $_POST['username'], $_POST['useremail'], $_POST['usenetgroup'], $_POST['publickey']);
					header("Location:".WWW_TOP."/spotnab-list.php");
			}
      		break;
    case 'view':
    default:

			if (isset($_GET["id"]))
			{
				$page->title = "Spotnab Source Edit";
				$id = $_GET["id"];
				$source = $spotnab->getSourceByID($id);
				$page->smarty->assign('source', $source);
			}

      break;
}

$page->content = $page->smarty->fetch('spotnab-edit.tpl');
$page->render();

