<?php

declare(strict_types=1);

$command = $argv[1] ?? '';
if ($command !== 'schedule:work') {
    exit(1);
}
foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $path) {
    $arguments = @file_get_contents($path);
    if ($arguments !== false && str_contains($arguments, "artisan\0schedule:work\0")) {
        exit(0);
    }
}
exit(1);
