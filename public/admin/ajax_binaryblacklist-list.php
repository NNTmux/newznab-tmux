<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Binaries;

// login check
$admin = new AdminPage;
$bin = new Binaries();

if ($page->request->has('action') && $page->request->input('action') === '2') {
    $id = (int) $page->request->input('bin_id');
    $bin->deleteBlacklist($id);
    echo "Blacklist $id deleted.";
}
