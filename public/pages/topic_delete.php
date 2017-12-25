<?php

use nntmux\Forum;
use App\Models\User;

if (! User::isLoggedIn()) {
    $page->show403();
}
$forum = new Forum();
$id = $_GET['id'] + 0;

if (isset($id)) {
    $forum->deleteParent($id);
    header('Location:'.WWW_TOP.'/forum');
}
