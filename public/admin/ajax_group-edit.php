<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use nntmux\Groups;

$admin = new AdminPage;
$group = new Groups(['Settings' => $admin->settings]);

// session_write_close(); allows the admin to use the site while the ajax request is being processed.
if (isset($_GET['action']) && $_GET['action'] === 2) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    $group->delete($id);
    echo "Group $id deleted.";
} elseif (isset($_GET['action']) && $_GET['action'] === 3) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    $group->reset($id);
    echo "Group $id reset.";
} elseif (isset($_GET['action']) && $_GET['action'] === 4) {
    $id = (int) $_GET['group_id'];
    session_write_close();
    $group->purge($id);
    echo "Group $id purged.";
} elseif (isset($_GET['action']) && $_GET['action'] === 5) {
    $group->resetall();
    echo 'All groups reset.';
} elseif (isset($_GET['action']) && $_GET['action'] === 6) {
    session_write_close();
    $group->purge();
    echo 'All groups purged.';
} else {
    if (isset($_GET['group_id'])) {
        $id = (int) $_GET['group_id'];
        if (isset($_GET['group_status'])) {
            $status = isset($_GET['group_status']) ? (int) $_GET['group_status'] : 0;
            echo $group->updateGroupStatus($id, 'active', $status);
        }
        if (isset($_GET['backfill_status'])) {
            $status = isset($_GET['backfill_status']) ? (int) $_GET['backfill_status'] : 0;
            echo $group->updateGroupStatus($id, 'backfill', $status);
        }
    }
}
