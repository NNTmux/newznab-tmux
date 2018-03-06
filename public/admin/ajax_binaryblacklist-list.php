<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Binaries;

// login check
$page = new AdminPage;
$bin = new Binaries();

if (request()->has('action') && request()->input('action') === '2') {
    $id = (int) request()->input('bin_id');
    $bin->deleteBlacklist($id);
    echo "Blacklist $id deleted.";
}
