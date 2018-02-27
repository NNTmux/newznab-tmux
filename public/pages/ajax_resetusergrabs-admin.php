<?php

use App\Models\UserRequest;
use App\Models\UserDownload;

$page = new AdminPage();

$action = $page->request->input('action') ?? '';
$id = $page->request->input('id') ?? '';

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
