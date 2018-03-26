<?php

use App\Models\Forumpost;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$id = request()->input('id') + 0;

if (isset($id)) {
    Forumpost::deleteParent($id);
    request()->header('/forum');
}
