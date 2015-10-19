<?php

require_once("config.php");

use newznab\Users;
use newznab\Category;

$page = new AdminPage();
$users = new Users();
$category = new Category();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

//get the user roles
$userroles = $users->getRoles();
$roles = [];
foreach ($userroles as $r) {
	$roles[$r['ID']] = $r['name'];
}

switch($action)
{
    case 'add':
    		$page->title = "User Roles Add";
				$role = [];
				$role["name"] = '';
				$role["apirequests"] = '';
				$role["downloadrequests"] = '';
				$role["defaultinvites"] = '';
				$role["canpreview"] = 0;
				$role["canpre"] = 0;
				$role["hideads"] = 0;
				$page->smarty->assign('role', $role);

			break;
    case 'submit':
	    	if ($_POST["id"] == "")
	    	{
					$ret = $users->addRole($_POST['name'], $_POST['apirequests'], $_POST['downloadrequests'], $_POST['defaultinvites'], $_POST['canpreview'], $_POST['canpre'], $_POST['hidetads']);
					header("Location:".WWW_TOP."/role-list.php");
	    	}
	    	else
	    	{
					$ret = $users->updateRole($_POST['id'], $_POST['name'], $_POST['apirequests'], $_POST['downloadrequests'], $_POST['defaultinvites'], $_POST['isdefault'], $_POST['canpreview'], $_POST['canpre'], $_POST['hidetads']);
					header("Location:".WWW_TOP."/role-list.php");

					$_POST['exccat'] = (!isset($_POST['exccat']) || !is_array($_POST['exccat'])) ? [] : $_POST['exccat'];
					$users->addRoleCategoryExclusions($_POST['id'], $_POST['exccat']);
				}

      break;
    case 'view':
    default:

			if (isset($_GET["id"]))
			{
				$page->title = "User Roles Edit";
				$id = $_GET["id"];
				$role = $users->getRoleByID($id);

				$page->smarty->assign('role', $role);
			}

      break;
}

$page->smarty->assign('yesno_ids', array(1,0));
$page->smarty->assign('yesno_names', array( 'Yes', 'No'));

$page->smarty->assign('catlist',$category->getForSelect(false));

if (isset($_GET["id"]))
{
	$page->smarty->assign('roleexccat', $users->getRoleCategoryExclusion($_GET["id"]));
}
$page->content = $page->smarty->fetch('role-edit.tpl');
$page->render();

