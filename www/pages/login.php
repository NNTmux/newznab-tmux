<?php

$page->smarty->assign(['error' => '', 'username' => '', 'rememberme' => '']);

$captcha = new Captcha($page);

if ($page->isPostBack())
{
	if (!isset($_POST["username"]) || !isset($_POST["password"])){
		$page->smarty->assign('error', "Please enter your username and password.");
} elseif ($captcha->getError() === false) {
		$username = htmlspecialchars($_POST["username"]);
		$page->smarty->assign('username', $username);
		$res = $page->users->getByUsername($username);
		$dis = $page->users->isDisabled($username);

		if (!$res)
			$res = $page->users->getByEmail($username);

		if ($res)
		{
			if ($dis)
			{
				$page->smarty->assign('error', "Your account has been disabled.");
			}
			else if ($page->users->checkPassword($_POST["password"], $res["password"], $res['id']))
			{
				$rememberMe = (isset($_POST['rememberme']) && $_POST['rememberme'] == 'on') ? 1 : 0;
				$page->users->login($res["id"], $_SERVER['REMOTE_ADDR'], $rememberMe);

				if (isset($_POST["redirect"]) && $_POST["redirect"] != "") {
					header("Location: " . $_POST["redirect"]);
				} else {
					header("Location: " . WWW_TOP . $page->settings->getSetting('home_link'));
				}
				die();
			}
			else
			{
				$page->smarty->assign('error', "Incorrect username or password.");
			}
		}
		else
		{
			$page->smarty->assign('error', "Incorrect username or password.");
		}
	}
}

$page->smarty->assign('redirect', (isset($_GET['redirect'])) ? $_GET['redirect'] : '');
$page->meta_title = "Login";
$page->meta_keywords = "Login";
$page->meta_description = "Login";
$page->content = $page->smarty->fetch('login.tpl');
$page->render();