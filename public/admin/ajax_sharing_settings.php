<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\db\DB;
use Blacklight\Sharing;
use App\Models\ReleaseComment;

// Login check.
$admin = new AdminPage;
$db = new DB();

if (isset($_GET['site_ID']) && isset($_GET['site_status'])) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d WHERE id = %d', $_GET['site_status'], $_GET['site_ID']));
    if ($_GET['site_status'] == 1) {
        echo 'Activated site '.$_GET['site_ID'];
    } else {
        echo 'Deactivated site '.$_GET['site_ID'];
    }
} elseif (isset($_GET['enabled_status'])) {
    $db->queryExec(sprintf('UPDATE sharing SET enabled = %d', $_GET['enabled_status']));
    if ($_GET['enabled_status'] == 1) {
        echo 'Enabled sharing!';
    } else {
        echo 'Disabled sharing!';
    }
} elseif (isset($_GET['posting_status'])) {
    $db->queryExec(sprintf('UPDATE sharing SET posting = %d', $_GET['posting_status']));
    if ($_GET['posting_status'] == 1) {
        echo 'Enabled posting!';
    } else {
        echo 'Disabled posting!';
    }
} elseif (isset($_GET['fetching_status'])) {
    $db->queryExec(sprintf('UPDATE sharing SET fetching = %d', $_GET['fetching_status']));
    if ($_GET['fetching_status'] == 1) {
        echo 'Enabled fetching!';
    } else {
        echo 'Disabled fetching!';
    }
} elseif (isset($_GET['auto_status'])) {
    $db->queryExec(sprintf('UPDATE sharing SET auto_enable = %d', $_GET['auto_status']));
    if ($_GET['auto_status'] == 1) {
        echo 'Enabled automatic site enabling!';
    } else {
        echo 'Disabled automatic site enabling!';
    }
} elseif (isset($_GET['hide_status'])) {
    $db->queryExec(sprintf('UPDATE sharing SET hide_users = %d', $_GET['hide_status']));
    if ($_GET['hide_status'] == 1) {
        echo 'Enabled hiding of user names!';
    } else {
        echo 'Disabled hiding of user names!';
    }
} elseif (isset($_GET['start_position'])) {
    $db->queryExec(sprintf('UPDATE sharing SET start_position = %d', $_GET['start_position']));
    if ($_GET['start_position'] == 1) {
        echo 'Enabled fetching from start of group!';
    } else {
        echo 'Disabled fetching from start of group!';
    }
} elseif (isset($_GET['toggle_all'])) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d', $_GET['toggle_all']));
} elseif (isset($_GET['reset_settings'])) {
    $guid = $db->queryOneRow('SELECT site_guid FROM sharing');
    $guid = ($guid === false ? '' : $guid['site_guid']);
    (new Sharing(['Settings' => $admin->settings]))->initSettings($guid);
    echo 'Re-initiated sharing settings!';
} elseif (isset($_GET['purge_site'])) {
    $guid = $db->queryOneRow(sprintf('SELECT site_guid FROM sharing_sites WHERE id = %d', $_GET['purge_site']));
    if ($guid === false) {
        echo 'Error purging site '.$_GET['purge_site'].'!';
    } else {
        $ids = $db->query(sprintf('SELECT id FROM release_comments WHERE siteid = %s', $db->escapeString($guid['site_guid'])));
        $total = count($ids);
        if ($total > 0) {
            foreach ($ids as $id) {
                ReleaseComment::deleteComment($id['id']);
            }
        }
        $db->queryExec(sprintf('UPDATE sharing_sites SET comments = 0 WHERE id = %d', $_GET['purge_site']));
        echo 'Deleted '.$total.' comments for site '.$_GET['purge_site'];
    }
}
