<?php

use App\Models\User;
use nntmux\Forum;

if (! User::isLoggedIn()) {
    $page->show403();
}
$forum = new Forum();
$id = $_GET['id'] + 0;

if (isset($id)) {
    $forum->deleteParent($id);
    header('Location:'.WWW_TOP.'/forum');
}
