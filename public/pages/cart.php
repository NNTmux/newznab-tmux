<?php

use App\Models\Release;
use Blacklight\Releases;
use App\Models\UsersRelease;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

if (request()->has('add')) {
    $releases = new Releases(['Settings' => $page->settings]);
    $guids = explode(',', request()->input('add'));
    $data = Release::getByGuid($guids);

    if (! $data) {
        $page->show404();
    }

    foreach ($data as $d) {
        UsersRelease::addCart(Auth::id(), $d['id']);
    }
} elseif (request()->has('delete')) {
    if (request()->has('delete') && ! empty(request()->input('delete'))) {
        $ids = (array) request()->input('delete');
    } elseif (request()->has('delete') && is_array(request()->input('delete'))) {
        $ids = request()->input('delete');
    }

    if ($ids !== null && UsersRelease::delCartByGuid($ids, Auth::id())) {
        redirect('/cart');
    }

    if (! request()->has('delete')) {
        redirect('/cart');
    }
} else {
    $page->meta_title = 'My Download Basket';
    $page->meta_keywords = 'search,add,to,cart,download,basket,nzb,description,details';
    $page->meta_description = 'Manage Your Download Basket';

    $results = UsersRelease::getCart(Auth::id());
    $page->smarty->assign('results', $results);

    $page->content = $page->smarty->fetch('cart.tpl');
    $page->pagerender();
}
