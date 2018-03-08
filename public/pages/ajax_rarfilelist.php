<?php

use App\Models\User;
use App\Models\ReleaseFile;

if (! User::isLoggedIn()) {
    $page->show403();
}

if (! request()->has('id')) {
    $page->show404();
}

$files = ReleaseFile::getByGuid(request()->input('id'));

if (count($files) === 0) {
    echo 'No files';
} else {
    echo "<ul>\n";
    foreach ($files as $f) {
        echo '<li>'.htmlentities($f['name'], ENT_QUOTES).'&nbsp;'.($f['passworded'] === 1 ? '<img width="12" src="'.WWW_TOP.'/assets/images/icons/lock.gif" />' : '')."</li>\n";
    }
    echo '</ul>';
}
