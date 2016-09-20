<?php
require_once realpath(__DIR__ . DIRECTORY_SEPARATOR . 'install.php');

use nntmux\Install;

$page = new InstallPage();
$page->title = "OpenSSL Setup";

$cfg = new Install();

if (!$cfg->isInitialized()) {
	header("Location: index.php");
	die();
}

$cfg = $cfg->getSession();

if ($page->isPostBack()) {
	$cfg->doCheck = true;

	$cfg->NN_SSL_CAFILE = trim($_POST['cafile']);
	$cfg->NN_SSL_CAPATH = trim($_POST['capath']);
	$cfg->NN_SSL_VERIFY_PEER = (isset($_POST['verifypeer']) ? (trim($_POST['verifypeer']) == '1' ? true : false) : false);
	$cfg->NN_SSL_VERIFY_HOST = (isset($_POST['verifyhost']) ? (trim($_POST['verifyhost']) == '1' ? true : false) : false);
	$cfg->NN_SSL_ALLOW_SELF_SIGNED = (isset($_POST['allowselfsigned']) ? (trim($_POST['allowselfsigned']) == '1' ? true : false) : false);

	// If the user doesn't want to verify peer, disable everything.
	if (!$cfg->NN_SSL_VERIFY_PEER) {
		$cfg->NN_SSL_ALLOW_SELF_SIGNED = true;
		$cfg->NN_SSL_VERIFY_HOST = false;
		$cfg->NN_SSL_CAFILE = $cfg->NN_SSL_CAPATH = '';
	}

	// If both paths are empty, disable everything.
	if (!$cfg->NN_SSL_CAPATH && !$cfg->NN_SSL_CAFILE) {
		$cfg->NN_SSL_VERIFY_PEER = $cfg->NN_SSL_VERIFY_HOST = false;
		$cfg->NN_SSL_ALLOW_SELF_SIGNED = true;
		$cfg->NN_SSL_CAFILE = $cfg->NN_SSL_CAPATH = '';
	}

	// Make sure the files and all paths are readable.
	if ($cfg->NN_SSL_CAFILE != '') {
		if (!checkPathsReadable($cfg->NN_SSL_CAFILE)) {
			$cfg->error = 'Invalid ca file path or it is not readable or the folders up to it are not readable.';
		} else if (1) {

		}
	}
	if ($cfg->NN_SSL_CAPATH != '' && !checkPathsReadable($cfg->NN_SSL_CAPATH)) {
		$cfg->error = 'Invalid ca folder path or it is not readable or the folders up to it are not readable.';
	}

	if (!$cfg->error) {
		$cfg->setSession();
		header("Location: ?success");
		die();
	}
}

$page->smarty->assign('cfg', $cfg);
$page->smarty->assign('page', $page);

$page->content = $page->smarty->fetch('step3.tpl');
$page->render();

function checkPathsReadable($location)
{
	$paths = preg_split('#\/#', $location);
	$directory = '';
	if ($paths && count($paths)) {
		foreach ($paths as $path) {
			$directory .= DS . $path;
			if (!is_readable($directory)) {
				return false;
			}
		}
		return true;
	}
	return false;
}
