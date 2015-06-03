<?php

use newznab\db\Settings;

if ($users->isLoggedIn())
	$page->show404();

$showregister = 1;

if ($page->settings->getSetting('registerstatus') == Settings::REGISTER_STATUS_CLOSED)
{
	$page->smarty->assign('error', "Registrations are currently disabled.");
	$showregister = 0;
}
elseif ($page->settings->getSetting('registerstatus') == Settings::REGISTER_STATUS_INVITE && (!isset($_REQUEST['invitecode']) || empty($_REQUEST['invitecode'])))
{
	$page->smarty->assign('error', "Registrations are currently invite only.");
	$showregister = 0;
}

if ($showregister == 0)
{
	$page->smarty->assign('showregister', "0");
}
else {

	$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

	//Be sure to persist the invite code in the event of multiple form submissions. (errors)
	if (isset($_REQUEST['invitecode'])) {
		$page->smarty->assign('invite_code_query', '&invitecode='.htmlspecialchars($_REQUEST["invitecode"]));
	} else {
		$page->smarty->assign('invite_code_query', '');
	}

	switch ($action) {
		case 'submit':
			if ($page->captcha->getError() === false) {
				$username = htmlspecialchars($_POST['username']);
				$password = htmlspecialchars($_POST['password']);
				$confirmpassword = htmlspecialchars($_POST['confirmpassword']);
				$email = htmlspecialchars($_POST['email']);
				$invitecode = htmlspecialchars($_POST['invitecode']);

				$page->smarty->assign('username', $username);
				$page->smarty->assign('password', $password);
				$page->smarty->assign('confirmpassword', $confirmpassword);
				$page->smarty->assign('email', $email);
				$page->smarty->assign('invitecode', $invitecode);

				//
				// check uname/email isnt in use, password valid.
				// if all good create new user account and redirect back to home page
				//
				if ($password != $confirmpassword) {
					$page->smarty->assign('error', "Password Mismatch");
				} else {
					//get the default user role
					$userdefault = $users->getDefaultRole();

					$ret = $users->signup($username, $password, $email, $_SERVER['REMOTE_ADDR'], $userdefault['id'], "", $userdefault['defaultinvites'], $invitecode, false);
					if ($ret > 0) {
						$users->login($ret, $_SERVER['REMOTE_ADDR']);
						header("Location: " . WWW_TOP . "/");
					} else {
						switch ($ret) {
							case Users::ERR_SIGNUP_BADUNAME:
								$page->smarty->assign('error', "Your username must be longer than three characters.");
								break;
							case Users::ERR_SIGNUP_BADPASS:
								$page->smarty->assign('error', "Your password must be longer than five characters.");
								break;
							case Users::ERR_SIGNUP_BADEMAIL:
								$page->smarty->assign('error', "Your email is not a valid format.");
								break;
							case Users::ERR_SIGNUP_UNAMEINUSE:
								$page->smarty->assign('error', "Sorry, the username is already taken.");
								break;
							case Users::ERR_SIGNUP_EMAILINUSE:
								$page->smarty->assign('error', "Sorry, the email is already in use.");
								break;
							case Users::ERR_SIGNUP_BADINVITECODE:
								$page->smarty->assign('error', "Sorry, the invite code is old or has been used.");
								break;
							default:
								$page->smarty->assign('error', "Failed to register.");
								break;
						}
					}
				}
			}
			break;
		case "view": {
			$invitecode = isset($_GET["invitecode"]) ? htmlspecialchars($_GET["invitecode"]) : null;
			if (isset($invitecode)) {
					//
					// see if it is a valid invite
					//
					$invite = $users->getInvite($invitecode);
					if (!$invite) {
						$page->smarty->assign('error', sprintf("Bad or invite code older than %d days.", Users::DEFAULT_INVITE_EXPIRY_DAYS));
						$page->smarty->assign('showregister', "0");
					} else {
						$page->smarty->assign('invitecode', $invite["guid"]);
					}
				}
				break;
			}
		}
	}

$page->meta_title = "Register";
$page->meta_keywords = "register,signup,registration";
$page->meta_description = "Register";

$page->content = $page->smarty->fetch('register.tpl');
$page->render();