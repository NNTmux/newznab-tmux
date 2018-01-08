<?php

include_once __DIR__.DIRECTORY_SEPARATOR.'bootstrap/constants.php';

if ($argc > 1) {
    $constant = $argv[1];
    include_once __DIR__.DIRECTORY_SEPARATOR.'bootstrap/constants.php';
    if (defined($constant)) {
        exit(constant($constant));
    }
}

exit(__DIR__.__FILE__);
