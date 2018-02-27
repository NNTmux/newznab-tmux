<?php

use App\Models\User;
use App\Models\Forumpost;

if (! User::isLoggedIn()) {
    $page->show403();
}
$id = $page->request->input('id') + 0;

if (isset($id) && ! empty($page->request->input('addMessage'))) {
    $parent = Forumpost::getPost($id);
    Forumpost::editPost($id, $page->request->input('addMessage'), User::currentUserId());
    if ((int) $parent['parentid'] !== 0) {
        header('Location:'.WWW_TOP.'/forumpost/'.$parent['parentid'].'#last');
    } else {
        header('Location:'.WWW_TOP.'/forumpost/'.$id);
    }
}

$result = Forumpost::getPost($id);

$page->meta_title = 'Edit forum Post';
$page->meta_keywords = 'edit, view,forum,post,thread';
$page->meta_description = 'Edit forum post';

$page->smarty->assign('result', $result);

$page->content = $page->smarty->fetch('post_edit.tpl');
$page->render();
