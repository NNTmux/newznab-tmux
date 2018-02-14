<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\ReleaseRegex;

// login check
$admin = new AdminPage;
$regex = new ReleaseRegex();

if (isset($_GET['action']) && $_GET['action'] == '2') {
    $id = (int) $_GET['regex_id'];
    $regex->delete($id);
    echo "Regex $id deleted.";
}
