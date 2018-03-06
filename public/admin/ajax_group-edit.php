<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$page = new AdminPage;

// session_write_close(); allows the admin to use the site while the ajax request is being processed.
if (request()->has('action') && request()->input('action') === 2) {
    $id = (int) request()->input('group_id');
    session_write_close();
    Group::deleteGroup($id);
    echo "Group $id deleted.";
} elseif (request()->has('action') && request()->input('action') === 3) {
    $id = (int) request()->input('group_id');
    session_write_close();
    Group::reset($id);
    echo "Group $id reset.";
} elseif (request()->has('action') && request()->input('action') === 4) {
    $id = (int) request()->input('group_id');
    session_write_close();
    Group::purge($id);
    echo "Group $id purged.";
} elseif (request()->has('action') && request()->input('action') === 5) {
    Group::resetall();
    echo 'All groups reset.';
} elseif (request()->has('action') && request()->input('action') === 6) {
    session_write_close();
    Group::purge();
    echo 'All groups purged.';
} else {
    if (request()->has('group_id')) {
        $id = (int) request()->input('group_id');

        $status = request()->has('group_status') ? (int) request()->input('group_status') : 0;
        echo Group::updateGroupStatus($id, 'active', $status);

        $status = request()->has('backfill_status') ? (int) request()->input('backfill_status') : 0;
        echo Group::updateGroupStatus($id, 'backfill', $status);
    }
}
