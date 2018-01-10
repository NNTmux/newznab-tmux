<?php

use App\Models\User;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}
$id = $_GET['id'] + 0;

if (isset($id)) {
    Forumpost::deleteParent($id);
    header('Location:'.WWW_TOP.'/forum');
}
