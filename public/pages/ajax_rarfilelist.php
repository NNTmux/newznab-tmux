<?php

use nntmux\ReleaseFiles;

if (! $page->users->isLoggedIn()) {
    $page->show403();
}

if (! isset($_REQUEST['id'])) {
    $page->show404();
}

$rf = new ReleaseFiles();
$files = $rf->getByGuid($_REQUEST['id']);

if (count($files) === 0) {
    echo 'No files';
} else {
    echo "<ul>\n";
    foreach ($files as $f) {
        echo '<li>'.htmlentities($f['name'], ENT_QUOTES).'&nbsp;'.($f['passworded'] === 1 ? '<img width="12" src="'.WWW_TOP.'/themes/shared/images/icons/lock.gif" />' : '')."</li>\n";
    }
    echo '</ul>';
}
