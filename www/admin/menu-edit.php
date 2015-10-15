<?php

require_once './config.php';

use newznab\controllers\AdminPage;
use newznab\controllers\Menu;
use newznab\controllers\Users;

$page = new AdminPage();
$page->title = "Menu Add";
$menu = new Menu();
$users = new Users();
$id = 0;

//get the user roles
$userroles = $users->getRoles();
$roles = [];
foreach ($userroles as $r) {
	$roles[$r['ID']] = $r['name'];
}

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action)
{
    case 'submit':

	    if ($_POST["id"] == "")
    	{
            $menu->add($_POST);
        }
        else
        {
            $ret = $menu->update($_POST);
        }

        header("Location:".WWW_TOP."/menu-list.php");
        break;
    case 'view':
    default:

		if (isset($_GET["id"]))
		{
			$page->title = "Menu Edit";
			$id = $_GET["id"];

			$menurow = $menu->getByID($id);

			$page->smarty->assign('menu', $menurow);
		}

      break;
}

$page->smarty->assign('yesno_ids', array(1,0));
$page->smarty->assign('yesno_names', array( 'Yes', 'No'));

$page->smarty->assign('role_ids', array_keys($roles));
$page->smarty->assign('role_names', $roles);

$page->content = $page->smarty->fetch('menu-edit.tpl');
$page->render();

