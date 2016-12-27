<?php

use app\models\Settings;
use nntmux\utility\Utility;
use nntmux\Captcha;

if ($page->users->isLoggedIn()) {
	header('Location: ' . WWW_TOP . '/');
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

$captcha = new Captcha($page);
$email = $sent = $confirmed = '';

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
			$subject = Settings::value('site.main.title') . " Password Reset";
			$contents = "Your password has been reset to " . $newpass;
			$onscreen = "Your password has been reset to <strong>" . $newpass ."</strong> and sent to your e-mail address.";
			Utility::sendEmail($to, $subject, $contents, Settings::value('site.main.email'));
			$page->smarty->assign('notice',  $onscreen);
			$confirmed = "true";
			break;
		}

		break;
	case 'submit':

		if ($captcha->getError() === false) {
			$email = $_POST['email'];
			if ($email == '') {
				$page->smarty->assign('error', "Missing Email");
			} else {
				//
				// Check users exists and send an email
				//
				$ret = $page->users->getByEmail($email);
				if (!$ret) {
					$page->smarty->assign('error', "The email address is not recognised.");
					$sent = "true";
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
					$subject = Settings::value('site.main.title') . " Forgotten Password Request";
					$contents = "Someone has requested a password reset for this email address. To reset the password use the following link.\n\n " . $page->serverurl . "forgottenpassword?action=reset&guid=" . $guid;
					Utility::sendEmail($to, $subject, $contents, Settings::value('site.main.email'));
					$sent = "true";
					break;
				}
			}
			break;
		}
}
$page->smarty->assign([
		'email'     => $email,
		'confirmed' => $confirmed,
		'sent'      => $sent
	]
);

$page->title = "Forgotten Password";
$page->meta_title = "Forgotten Password";
$page->meta_keywords = "forgotten,password,signup,registration";
$page->meta_description = "Forgotten Password";

$page->content = $page->smarty->fetch('forgottenpassword.tpl');
$page->render();
