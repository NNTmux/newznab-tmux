<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\db\DB;
use Blacklight\Sharing;
use App\Models\ReleaseComment;

// Login check.
$admin = new AdminPage;
$db = new DB();

if ($page->request->has('site_ID') && $page->request->has('site_status')) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d WHERE id = %d', $page->request->input('site_status'), $page->request->input('site_ID')));
    if ($page->request->input('site_status') === 1) {
        echo 'Activated site '.$page->request->input('site_ID');
    } else {
        echo 'Deactivated site '.$page->request->input('site_ID');
    }
} elseif ($page->request->has('enabled_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET enabled = %d', $page->request->input('enabled_status')));
    if ($page->request->input('enabled_status') === 1) {
        echo 'Enabled sharing!';
    } else {
        echo 'Disabled sharing!';
    }
} elseif ($page->request->has('posting_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET posting = %d', $page->request->input('posting_status')));
    if ($page->request->input('posting_status') === 1) {
        echo 'Enabled posting!';
    } else {
        echo 'Disabled posting!';
    }
} elseif ($page->request->has('fetching_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET fetching = %d', $page->request->input('fetching_status')));
    if ($page->request->input('fetching_status') === 1) {
        echo 'Enabled fetching!';
    } else {
        echo 'Disabled fetching!';
    }
} elseif ($page->request->has('auto_enable')) {
    $db->queryExec(sprintf('UPDATE sharing SET auto_enable = %d', $page->request->input('auto_status')));
    if ($page->request->input('auto_status') === 1) {
        echo 'Enabled automatic site enabling!';
    } else {
        echo 'Disabled automatic site enabling!';
    }
} elseif ($page->request->has('hide_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET hide_users = %d', $page->request->input('hide_status')));
    if ($page->request->input('hide_status') === 1) {
        echo 'Enabled hiding of user names!';
    } else {
        echo 'Disabled hiding of user names!';
    }
} elseif ($page->request->has('start_position')) {
    $db->queryExec(sprintf('UPDATE sharing SET start_position = %d', $page->request->input('start_position')));
    if ($page->request->input('start_position') === 1) {
        echo 'Enabled fetching from start of group!';
    } else {
        echo 'Disabled fetching from start of group!';
    }
} elseif ($page->request->has('toggle_all')) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d', $page->request->input('toggle_all')));
} elseif ($page->request->has('reset_settings')) {
    $guid = $db->queryOneRow('SELECT site_guid FROM sharing');
    $guid = ($guid === false ? '' : $guid['site_guid']);
    (new Sharing(['Settings' => $admin->settings]))->initSettings($guid);
    echo 'Re-initiated sharing settings!';
} elseif ($page->request->has('purge_site')) {
    $guid = $db->queryOneRow(sprintf('SELECT site_guid FROM sharing_sites WHERE id = %d', $page->request->input('purge_site')));
    if ($guid === false) {
        echo 'Error purging site '.$page->request->input('purge_site').'!';
    } else {
        $ids = $db->query(sprintf('SELECT id FROM release_comments WHERE siteid = %s', $db->escapeString($guid['site_guid'])));
        $total = count($ids);
        if ($total > 0) {
            foreach ($ids as $id) {
                ReleaseComment::deleteComment($id['id']);
            }
        }
        $db->queryExec(sprintf('UPDATE sharing_sites SET comments = 0 WHERE id = %d', $page->request->input('purge_site')));
        echo 'Deleted '.$total.' comments for site '.$page->request->input('purge_site');
    }
}
