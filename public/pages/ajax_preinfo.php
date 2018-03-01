<?php

use App\Models\User;
use App\Models\Predb;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (! request()->has('id')) {
    $page->show404();
}

$predata = Predb::getOne(request()->input('id'));

if (! $predata) {
    echo 'No pre info';
} else {
    echo "<table>\n";
    if (isset($predata['nuked'])) {
        $nuked = '';
        switch ($predata['nuked']) {
            case Predb::PRE_NUKED:
                $nuked = 'NUKED';
                break;
            case Predb::PRE_MODNUKE:
                $nuked = 'MODNUKED';
                break;
            case Predb::PRE_OLDNUKE:
                $nuked = 'OLDNUKE';
                break;
            case Predb::PRE_RENUKED:
                $nuked = 'RENUKE';
                break;
            case Predb::PRE_UNNUKED:
                $nuked = 'UNNUKED';
                break;
        }
        if ($nuked !== '') {
            echo '<tr><th>'.$nuked.':</th><td>'.htmlentities($predata['nukereason'] ?? '', ENT_QUOTES)."</td></tr>\n";
        }
    }
    echo '<tr><th>Title:</th><td>'.htmlentities($predata['title'], ENT_QUOTES)."</td></tr>\n";
    if (isset($predata['category']) && $predata['category'] !== '') {
        echo '<tr><th>Cat:</th><td>'.htmlentities($predata['category'], ENT_QUOTES)."</td></tr>\n";
    }
    echo '<tr><th>Source:</th><td>'.htmlentities($predata['source'], ENT_QUOTES)."</td></tr>\n";
    if (isset($predata['size'])) {
        if (isset($predata['size'][0]) && $predata['size'][0] > 0) {
            echo '<tr><th>Size:</th><td>'.htmlentities($predata['size'], ENT_QUOTES)."</td></tr>\n";
        }
    }
    if (isset($predata['files'])) {
        echo '<tr><th>Files:</th><td>'.htmlentities((preg_match('/F|B/', $predata['files'], $match) ? $predata['files'] : ($predata['files'].'MB')), ENT_QUOTES)."</td></tr>\n";
    }
    echo '<tr><th>Pred:</th><td>'.htmlentities($predata['predate'], ENT_QUOTES)."</td></tr>\n";
    echo '</table>';
}
