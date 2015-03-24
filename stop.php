<?php

require_once(dirname(__FILE__) . "/bin/config.php");
require_once(WWW_DIR . '/lib/Tmux.php');

$restart = (new \Tmux())->stopIfRunning();
