<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\Tmux;

$restart = (new Tmux())->stopIfRunning();
