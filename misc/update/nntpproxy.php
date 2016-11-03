<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use nntmux\db\DB;
use nntmux\utility\Utility;

Utility::clearScreen();

$pdo = new DB();
$t = new \Tmux();
$tmux = $t->get();
$powerline = (isset($tmux->powerline)) ? $tmux->powerline : 0;
$s= new Sites();
$site = $s->get();

$tmux_session = 'NNTPProxy';

function python_module_exist($module)
{
	exec("python -c \"import $module\"", $output, $returnCode);
	return ($returnCode == 0 ? true : false);
}

$nntpproxy = $site->nntpproxy;
if ($nntpproxy === '0') {
	exit();
} else {
	$modules = array("socketpool");
	foreach ($modules as &$value) {
		if (!python_module_exist($value)) {
			exit($pdo->log->error("NNTP Proxy requires " . $value . " python module but it's not installed. Aborting."));
		}
	}
}

function window_proxy($tmux_session, $powerline)
{
	global $pdo;
	global $site;

	$DIR = NN_MISC;
	if ($powerline === '1') {
		$tmuxconfig = $DIR . "update/nix/tmux/powerline/tmux.conf";
	} else {
		$tmuxconfig = $DIR . "update/nix/tmux/tmux.conf";
	}

	$nntpproxy = $site->nntpproxy;
	if ($nntpproxy === '1') {
		$DIR = NN_MISC;
		$nntpproxypy = $DIR . "update/nix/python/nntpproxy.py";
		if (file_exists($DIR . "update/nix/python/lib/nntpproxy.conf")) {
			$nntpproxyconf = $DIR . "update/nix/python/lib/nntpproxy.conf";
			shell_exec("cd ${DIR}/update/nix/tmux; tmux -f $tmuxconfig attach-session -t $tmux_session || tmux -f $tmuxconfig new-session -d -s $tmux_session -n NNTPProxy 'printf \"\033]2;\"NNTPProxy\"\033\" && python $nntpproxypy $nntpproxyconf'");
		}
	}

	if ($nntpproxy == '1' && ($site->alternate_nntp == '1')) {
		$DIR = NN_MISC;
		$nntpproxypy = $DIR . "update/nix/python/nntpproxy.py";
		if (file_exists($DIR . "update/nix/python/lib/nntpproxy_a.conf")) {
			$nntpproxyconf = $DIR . "update/nix/python/lib/nntpproxy_a.conf";
			shell_exec("tmux selectp -t 0; tmux splitw -t $tmux_session:0 -h -p 50 'printf \"\033]2;NNTPProxy\033\" && python $nntpproxypy $nntpproxyconf'");
		}
	}
}

window_proxy($tmux_session, $powerline);
