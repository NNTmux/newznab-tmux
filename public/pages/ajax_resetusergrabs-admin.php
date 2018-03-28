<?php

use App\Models\UserRequest;
use App\Models\UserDownload;
use Blacklight\http\BasePage;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$page = new BasePage();

$action = request()->input('action') ?? '';
$id = request()->input('id') ?? '';

switch ($action) {
    case 'grabs':
        UserDownload::delDownloadRequests($id);
    break;
    case 'api':
        UserRequest::delApiRequests($id);
    break;
    default:
        $page->show404();
    break;
}
