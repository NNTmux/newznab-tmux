<?php

use App\Models\User;
use App\Models\Release;

if (! User::isLoggedIn()) {
    $page->show403();
}

use Blacklight\Releases;
use App\Models\UsersRelease;

if (\request()->has('add')) {
    $releases = new Releases(['Settings' => $page->settings]);
    $guids = explode(',', \request()->input('add'));
    $data = Release::getByGuid($guids);

    if (! $data) {
        $page->show404();
    }

    foreach ($data as $d) {
        UsersRelease::addCart(User::currentUserId(), $d['id']);
    }
} elseif (\request()->has('delete')) {
    if (\request()->has('delete') && ! empty(\request()->input('delete'))) {
        $ids = [\request()->input('delete')];
    } elseif (\request()->has('delete') && is_array(\request()->input('delete'))) {
        $ids = \request()->input('delete');
    }

    if ($ids !== null) {
        UsersRelease::delCartByGuid($ids, User::currentUserId());
    }

    if (! \request()->has('delete')) {
        header('Location: '.WWW_TOP.'/cart');
    }

    exit();
} else {
    $page->meta_title = 'My Download Basket';
    $page->meta_keywords = 'search,add,to,cart,download,basket,nzb,description,details';
    $page->meta_description = 'Manage Your Download Basket';

    $results = UsersRelease::getCart(User::currentUserId());
    $page->smarty->assign('results', $results);

    $page->content = $page->smarty->fetch('cart.tpl');
    $page->render();
}
