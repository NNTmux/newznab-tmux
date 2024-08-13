<?php

use App\Models\User;
use Blacklight\ColorCLI;
use Jrean\UserVerification\Facades\UserVerification;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$colorCli = new ColorCLI;

if (isset($argv[1]) && is_numeric($argv[1])) {
    $user = User::find($argv[1]);
    UserVerification::generate($user);

    UserVerification::send($user, 'User email verification required');

    $colorCli->info('Email has been sent');
} else {
    $colorCli->error('You need to provide user id as argument');
}
