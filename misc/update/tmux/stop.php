<?php

require_once dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'bootstrap.php';

use nntmux\Tmux;

$restart = (new Tmux())->stopIfRunning();
