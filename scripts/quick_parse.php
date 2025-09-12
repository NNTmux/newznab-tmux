<?php

require __DIR__.'/../vendor/autoload.php';

$rc = new ReflectionClass(\Blacklight\Games::class);
$games = $rc->newInstanceWithoutConstructor();

$inputs = [
    '[FitGirl] Red.Dead.Redemption.2.v1.0.1436.28.Repack',
    'Baldurs_Gate_3_v1.0.2_MULTI12-EMPRESS',
    'Starfield.Update.1.7.29.Patch-FLT',
];
foreach ($inputs as $in) {
    $res = $games->parseTitle($in);
    echo $in, ' => ', json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
}
