<?php

use App\Models\Forumpost;
use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}
$id = $_GET['id'] + 0;

if (isset($id)) {
    Forumpost::deleteParent($id);
    header('Location:'.WWW_TOP.'/forum');
}
