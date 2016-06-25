<?php
require_once realpath(dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'indexer.php');

use nntmux\Tmux;

$restart = (new Tmux())->stopIfRunning();
