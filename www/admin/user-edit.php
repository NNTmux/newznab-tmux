<?php

require_once './config.php';


use nntmux\Users;

$page = new AdminPage();
$users = new Users();

$user = [
	'id' => '',
	'username' => '',
	'email' => '',
	'password' => ''
];

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

//get the user roles
$userroles = $users->getRoles();
$roles = [];
$defaultRole = Users::ROLE_USER;
$defaultInvites = Users::DEFAULT_INVITES;
foreach ($userroles as $r) {
	$roles[$r['id']] = $r['name'];
	if ($r['isdefault'] == 1) {
		$defaultrole = $r['id'];
		$defaultinvites = $r['defaultinvites'];
	}
}

switch ($action) {
	case 'add':
		$user                += [
			'role'        => $defaultRole,
			'notes'       => '',
			'invites'     => $defaultInvites,
			'movieview'   => '1',
			'xxxview'     => '1',
			'musicview'   => '1',
			'consoleview' => '1',
			'gameview'    => '1',
			'bookview'    => '1'
		];
		$page->smarty->assign('user', $user);
		break;
	case 'submit':

		if ($_POST["id"] == "") {
			$invites = $defaultinvites;
			foreach ($userroles as $role) {
				if ($role['id'] == $_POST['role'])
					$invites = $role['defaultinvites'];
			}
			$ret = $users->signup($_POST["username"], $_POST["password"], $_POST["email"], '', $_POST["role"], $_POST["notes"], $invites, "", true);
		} else {
			$ret = $users->update($_POST["id"], $_POST["username"], $_POST["email"], $_POST["grabs"], $_POST["role"], $_POST["notes"], $_POST["invites"], (isset($_POST['movieview']) ? "1" : "0"), (isset($_POST['musicview']) ? "1" : "0"), (isset($_POST['gameview']) ? "1" : "0"), (isset($_POST['xxxview']) ? "1" : "0"), (isset($_POST['consoleview']) ? "1" : "0"), (isset($_POST['bookview']) ? "1" : "0"));
			if ($_POST['password'] != "") {
				$users->updatePassword($_POST["id"], $_POST['password']);
			}
			if ($_POST['rolechangedate'] != "") {
				$users->updateUserRoleChangeDate($_POST["id"], $_POST["rolechangedate"]);
			}
		}

		if ($ret >= 0) {
			header("Location:" . WWW_TOP . "/user-list.php");
		} else {
			switch ($ret) {
				case Users::ERR_SIGNUP_BADUNAME:
					$page->smarty->assign('error', "Bad username. Try a better one.");
					break;
				case Users::ERR_SIGNUP_BADPASS:
					$page->smarty->assign('error', "Bad password. Try a longer one.");
					break;
				case Users::ERR_SIGNUP_BADEMAIL:
					$page->smarty->assign('error', "Bad email.");
					break;
				case Users::ERR_SIGNUP_UNAMEINUSE:
					$page->smarty->assign('error', "Username in use.");
					break;
				case Users::ERR_SIGNUP_EMAILINUSE:
					$page->smarty->assign('error', "Email in use.");
					break;
				default:
					$page->smarty->assign('error', "Unknown save error.");
					break;
			}
			$user = [
				'id'        => $_POST["id"],
				'username'  => $_POST["username"],
				'email'     => $_POST["email"],
				'notes'     => $_POST["notes"],
				'grabs'     => (isset($_POST["grabs"]) ? $_POST["grabs"] : '0'),
				'role'      => $_POST["role"],
				'invites'   => (isset($_POST["invites"]) ? $_POST["invites"] : '0'),
				'movieview' => $_POST["movieview"],
				'bookview' => $_POST["bookview"]
			];
			$page->smarty->assign('user', $user);
		}
		break;
	case 'view':
	default:

	if (isset($_GET["id"])) {
			$page->title = "User Edit";
			$id = $_GET["id"];
			$user = $users->getById($id);

			$page->smarty->assign('user', $user);
		}

		break;
}

$page->smarty->assign('yesno_ids', array(1, 0));
$page->smarty->assign('yesno_names', array('Yes', 'No'));

$page->smarty->assign('role_ids', array_keys($roles));
$page->smarty->assign('role_names', $roles);

$page->content = $page->smarty->fetch('user-edit.tpl');
$page->render();

