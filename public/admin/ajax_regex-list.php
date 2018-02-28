<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\ReleaseRegex;

// login check
$admin = new AdminPage;
$regex = new ReleaseRegex();

if ($page->request->has('action') && $page->request->input('action') === '2') {
    $id = (int) $page->request->input('regex_id');
    $regex->delete($id);
    echo "Regex $id deleted.";
}
