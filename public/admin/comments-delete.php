<?php

use App\Models\ReleaseComment;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

if (request()->has('id')) {
    ReleaseComment::deleteComment(request()->input('id'));
}

$referrer = request()->server('HTTP_REFERER');
header('Location: '.$referrer);
