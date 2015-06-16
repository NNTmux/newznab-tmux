<?php

use newznab\utility\Utility;

if ($page->users->isLoggedIn())
	$page->show404();

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

switch($action) {
	case "reset":
		if (!isset($_REQUEST['guid'])) {
			$page->smarty->assign('error', "No reset code provided.");
			break;
		}

		$ret = $page->users->getByPassResetGuid($_REQUEST['guid']);
		if (!$ret) {
			$page->smarty->assign('error', "Bad reset code provided.");
			break;
		} else {
			//
			// reset the password, inform the user, send out the email
			//
			$page->users->updatePassResetGuid($ret["id"], "");
			$newpass = $page->users->generatePassword();
			$page->users->updatePassword($ret["id"], $newpass);

			$to = $ret["email"];
			$subject = $page->settings->getSetting('title') . " Password Reset";
			$contents = "Your password has been reset to " . $newpass;
			Utility::sendEmail($to, $subject, $contents, $page->settings->getSetting('email'));
			// Print new password to the screen so users dont have to check e-mail.
			$page->smarty->assign('error', $contents);
			$page->smarty->assign('confirmed', "true");

			break;
		}

		break;
	case 'submit':

		if ($page->captcha->getError() === false) {
			$page->smarty->assign('email', $_POST['email']);

			if ($_POST['email'] == "") {
				$page->smarty->assign('error', "Missing Email");
			} else {
				//
				// Check users exists and send an email
				//
				$ret = $page->users->getByEmail($_POST['email']);
				if (!$ret) {
					$page->smarty->assign('error', "The email address is not recognised.");
					break;
				} else {
					//
					// Generate a forgottenpassword guid, store it in the user table
					//
					$guid = md5(uniqid());
					$page->users->updatePassResetGuid($ret["id"], $guid);

					//
					// Send the email
					//
					$to = $ret["email"];
					$subject = $page->settings->getSetting('title') . " Forgotten Password Request";
					$contents = "Someone has requested a password reset for this email address. To reset the password use the following link.\n\n " . $page->serverurl . "forgottenpassword?action=reset&guid=" . $guid;
					$page->smarty->assign('sent', "true");
					Utility::sendEmail($to, $subject, $contents, $page->settings->getSetting('email'));
					break;
				}
			}
			break;
		}
}

$page->title = "Forgotten Password";
$page->meta_title = "Forgotten Password";
$page->meta_keywords = "forgotten,password,signup,registration";
$page->meta_description = "Forgotten Password";

$page->content = $page->smarty->fetch('forgottenpassword.tpl');
$page->render();
