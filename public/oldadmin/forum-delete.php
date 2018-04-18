<?php

use App\Models\Forumpost;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

if (request()->has('id')) {
    Forumpost::deletePost(request()->input('id'));
}

if (request()->has('from')) {
    $referrer = request()->input('from');
} else {
    $referrer = request()->server('HTTP_REFERER');
}
header('Location: '.$referrer);
