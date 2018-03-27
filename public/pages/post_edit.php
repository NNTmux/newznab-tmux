<?php

use App\Models\Forumpost;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$id = request()->input('id') + 0;

if (isset($id) && ! empty(request()->input('addMessage'))) {
    $parent = Forumpost::getPost($id);
    Forumpost::editPost($id, request()->input('addMessage'), Auth::id());
    if ((int) $parent['parentid'] !== 0) {
        header('/forumpost/'.$parent['parentid'].'#last');
    } else {
        header('/forumpost/'.$id);
    }
}

$result = Forumpost::getPost($id);

$page->meta_title = 'Edit forum Post';
$page->meta_keywords = 'edit, view,forum,post,thread';
$page->meta_description = 'Edit forum post';

$page->smarty->assign('result', $result);

$page->content = $page->smarty->fetch('post_edit.tpl');
$page->render();
