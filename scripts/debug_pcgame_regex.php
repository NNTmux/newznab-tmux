<?php

require __DIR__.'/../vendor/autoload.php';

use Blacklight\Categorize;

$c = (new ReflectionClass(Categorize::class))->newInstanceWithoutConstructor();
$c->poster = '';

$samples = [
    'Starfield-RUNE',
    'Baldurs.Gate.3.TENOKE',
    'ELDEN.RING-EMPRESS',
    'Horizon.Zero.Dawn-CODEX',
    'Cyberpunk.2077.GOG',
    'Forza.Horizon.5.ElAmigos',
    'The.Witcher.3.Wild.Hunt.PLaza',
    'Resident.Evil.4.Remake-FITGIRL',
    'Red.Dead.Redemption.2.DODI-Repack',
    'Some.Game.SKiDROW',
    'Awesome.Game.SteamRip',
    'Great.Game.Repack-FitGirl',
    'Indie.Title.DRM-Free.GOG',
    'Cool.Game.PC.Game.2024',
    'Windows.10.Title.Repack',
    'Title-[PC]-DRMFree',
];

foreach ($samples as $name) {
    $c->releaseName = $name;
    $res = $c->isPCGame();
    echo ($res ? 'MATCH' : 'NO-MATCH')."\t$name\n";
}
