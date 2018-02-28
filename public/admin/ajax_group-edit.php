<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage;

// session_write_close(); allows the admin to use the site while the ajax request is being processed.
if ($page->request->has('action') && $page->request->input('action') === 2) {
    $id = (int) $page->request->input('group_id');
    session_write_close();
    Group::deleteGroup($id);
    echo "Group $id deleted.";
} elseif ($page->request->has('action') && $page->request->input('action') === 3) {
    $id = (int) $page->request->input('group_id');
    session_write_close();
    Group::reset($id);
    echo "Group $id reset.";
} elseif ($page->request->has('action') && $page->request->input('action') === 4) {
    $id = (int) $page->request->input('group_id');
    session_write_close();
    Group::purge($id);
    echo "Group $id purged.";
} elseif ($page->request->has('action') && $page->request->input('action') === 5) {
    Group::resetall();
    echo 'All groups reset.';
} elseif ($page->request->has('action') && $page->request->input('action') === 6) {
    session_write_close();
    Group::purge();
    echo 'All groups purged.';
} else {
    if ($page->request->has('group_id')) {
        $id = (int) $page->request->input('group_id');

        $status = $page->request->has('group_status') ? (int) $page->request->input('group_status') : 0;
        echo Group::updateGroupStatus($id, 'active', $status);

        $status = $page->request->has('backfill_status') ? (int) $page->request->input('backfill_status') : 0;
        echo Group::updateGroupStatus($id, 'backfill', $status);
    }
}
