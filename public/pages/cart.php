<?php

use App\Models\User;
use Blacklight\Releases;

if (! User::isLoggedIn()) {
    $page->show403();
}

use App\Models\Release;
use App\Models\UsersRelease;

if (isset($_GET['add'])) {
    $releases = new Releases(['Settings' => $page->settings]);
    $guids = explode(',', $_GET['add']);
    $data = Release::getByGuid($guids);

    if (! $data) {
        $page->show404();
    }

    foreach ($data as $d) {
        UsersRelease::addCart(User::currentUserId(), $d['id']);
    }
} elseif (isset($_REQUEST['delete'])) {
    if (isset($_GET['delete']) && ! empty($_GET['delete'])) {
        $ids = [$_GET['delete']];
    } elseif (isset($_POST['delete']) && is_array($_POST['delete'])) {
        $ids = $_POST['delete'];
    }

    if ($ids !== null) {
        UsersRelease::delCartByGuid($ids, User::currentUserId());
    }

    if (! isset($_POST['delete'])) {
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
