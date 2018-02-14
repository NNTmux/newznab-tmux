<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\Binaries;

// login check
$admin = new AdminPage;
$bin = new Binaries();

if (isset($_GET['action']) && $_GET['action'] == '2') {
    $id = (int) $_GET['bin_id'];
    $bin->deleteBlacklist($id);
    echo "Blacklist $id deleted.";
}
