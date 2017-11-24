<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\Tmux;

$restart = (new Tmux())->stopIfRunning();
