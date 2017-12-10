<?php

use nntmux\Users;
use App\Models\UserRequest;
use App\Models\UserDownload;

$page = new AdminPage();
$u = new Users();

$action = $_REQUEST['action'] ?? '';
$id = $_REQUEST['id'] ?? '';

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
