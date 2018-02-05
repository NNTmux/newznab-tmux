<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\Group;

$admin = new AdminPage;

// session_write_close(); allows the admin to use the site while the ajax request is being processed.
if (isset($_GET['action']) && $_GET['action'] === 2) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    Group::deleteGroup($id);
    echo "Group $id deleted.";
} elseif (isset($_GET['action']) && $_GET['action'] === 3) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    Group::reset($id);
    echo "Group $id reset.";
} elseif (isset($_GET['action']) && $_GET['action'] === 4) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    Group::purge($id);
    echo "Group $id purged.";
} elseif (isset($_GET['action']) && $_GET['action'] === 5) {
    Group::resetall();
    echo 'All groups reset.';
} elseif (isset($_GET['action']) && $_GET['action'] === 6) {
    session_write_close();
    Group::purge();
    echo 'All groups purged.';
} else {
    if (isset($_GET['group_id'])) {
        $id = (int) $_GET['group_id'];
        if (isset($_GET['group_status'])) {
            $status = isset($_GET['group_status']) ? (int) $_GET['group_status'] : 0;
            echo Group::updateGroupStatus($id, 'active', $status);
        }
        if (isset($_GET['backfill_status'])) {
            $status = isset($_GET['backfill_status']) ? (int) $_GET['backfill_status'] : 0;
            echo Group::updateGroupStatus($id, 'backfill', $status);
        }
    }
}
