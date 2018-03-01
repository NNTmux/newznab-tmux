<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\ReleaseRegex;

// login check
$page = new AdminPage;
$regex = new ReleaseRegex();

if (\request()->has('action') && \request()->input('action') === '2') {
    $id = (int) \request()->input('regex_id');
    $regex->delete($id);
    echo "Regex $id deleted.";
}
