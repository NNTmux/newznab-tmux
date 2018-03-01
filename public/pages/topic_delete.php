<?php

use App\Models\User;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}
$id = request()->input('id') + 0;

if (isset($id)) {
    Forumpost::deleteParent($id);
    header('Location:'.WWW_TOP.'/forum');
}
