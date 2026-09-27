<?php

declare(strict_types=1);

use App\Mcp\Servers\NewznabTmuxServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('newznab-tmux', NewznabTmuxServer::class);
