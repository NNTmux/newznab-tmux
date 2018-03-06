<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\db\DB;

use Blacklight\http\AdminPage;
use Blacklight\Sharing;
use App\Models\ReleaseComment;

// Login check.
$page = new AdminPage;
$db = new DB();

if (request()->has('site_ID') && request()->has('site_status')) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d WHERE id = %d', request()->input('site_status'), request()->input('site_ID')));
    if (request()->input('site_status') === 1) {
        echo 'Activated site '.request()->input('site_ID');
    } else {
        echo 'Deactivated site '.request()->input('site_ID');
    }
} elseif (request()->has('enabled_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET enabled = %d', request()->input('enabled_status')));
    if (request()->input('enabled_status') === 1) {
        echo 'Enabled sharing!';
    } else {
        echo 'Disabled sharing!';
    }
} elseif (request()->has('posting_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET posting = %d', request()->input('posting_status')));
    if (request()->input('posting_status') === 1) {
        echo 'Enabled posting!';
    } else {
        echo 'Disabled posting!';
    }
} elseif (request()->has('fetching_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET fetching = %d', request()->input('fetching_status')));
    if (request()->input('fetching_status') === 1) {
        echo 'Enabled fetching!';
    } else {
        echo 'Disabled fetching!';
    }
} elseif (request()->has('auto_enable')) {
    $db->queryExec(sprintf('UPDATE sharing SET auto_enable = %d', request()->input('auto_status')));
    if (request()->input('auto_status') === 1) {
        echo 'Enabled automatic site enabling!';
    } else {
        echo 'Disabled automatic site enabling!';
    }
} elseif (request()->has('hide_status')) {
    $db->queryExec(sprintf('UPDATE sharing SET hide_users = %d', request()->input('hide_status')));
    if (request()->input('hide_status') === 1) {
        echo 'Enabled hiding of user names!';
    } else {
        echo 'Disabled hiding of user names!';
    }
} elseif (request()->has('start_position')) {
    $db->queryExec(sprintf('UPDATE sharing SET start_position = %d', request()->input('start_position')));
    if (request()->input('start_position') === 1) {
        echo 'Enabled fetching from start of group!';
    } else {
        echo 'Disabled fetching from start of group!';
    }
} elseif (request()->has('toggle_all')) {
    $db->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d', request()->input('toggle_all')));
} elseif (request()->has('reset_settings')) {
    $guid = $db->queryOneRow('SELECT site_guid FROM sharing');
    $guid = ($guid === false ? '' : $guid['site_guid']);
    (new Sharing(['Settings' => $page->settings]))->initSettings($guid);
    echo 'Re-initiated sharing settings!';
} elseif (request()->has('purge_site')) {
    $guid = $db->queryOneRow(sprintf('SELECT site_guid FROM sharing_sites WHERE id = %d', request()->input('purge_site')));
    if ($guid === false) {
        echo 'Error purging site '.request()->input('purge_site').'!';
    } else {
        $ids = $db->query(sprintf('SELECT id FROM release_comments WHERE siteid = %s', $db->escapeString($guid['site_guid'])));
        $total = count($ids);
        if ($total > 0) {
            foreach ($ids as $id) {
                ReleaseComment::deleteComment($id['id']);
            }
        }
        $db->queryExec(sprintf('UPDATE sharing_sites SET comments = 0 WHERE id = %d', request()->input('purge_site')));
        echo 'Deleted '.$total.' comments for site '.request()->input('purge_site');
    }
}
