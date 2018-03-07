<?php

use App\Models\UserRequest;
use App\Models\UserDownload;
use Blacklight\http\AdminPage;

$page = new AdminPage();

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
