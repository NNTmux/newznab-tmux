<?php

use App\Models\Group;
use Blacklight\db\DB;
use Blacklight\Regexes;
use Blacklight\Sharing;
use Blacklight\Binaries;
use App\Models\ReleaseComment;

// This script waits for ajax queries from the web.

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

// Make sure the user is an admin and logged in.
$pdo = new DB();

if (! request()->has('action')) {
    exit();
}

$settings = ['Settings' => $page->settings];
switch (request()->input('action')) {
    case 'binary_blacklist_delete':
        $id = (int) request()->input('row_id');
        (new Binaries($settings))->deleteBlacklist($id);
        echo "Blacklist $id deleted.";
        break;

    case 'category_regex_delete':
        $id = (int) request()->input('row_id');
        (new Regexes(['Settings' => $page->settings, 'Table_Name' => 'category_regexes']))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 'collection_regex_delete':
        $id = (int) request()->input('row_id');
        (new Regexes(['Settings' => $page->settings, 'Table_Name' => 'collection_regexes']))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 'release_naming_regex_delete':
        $id = (int) request()->input('row_id');
        (new Regexes(['Settings' => $page->settings, 'Table_Name' => 'release_naming_regexes']))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 'group_edit_purge_all':
        session_write_close();
        Group::purge();
        echo 'All groups purged.';
        break;

    case 'group_edit_reset_all':
        Group::resetall();
        echo 'All groups reset.';
        break;

    case 'group_edit_purge_single':
        $id = (int) request()->input('group_id');
        session_write_close();
        Group::purge($id);
        echo "Group $id purged.";
        break;

    case 'group_edit_reset_single':
        $id = (int) request()->input('group_id');
        session_write_close();
        Group::reset($id);
        echo "Group $id reset.";
        break;

    case 'group_edit_delete_single':
        $id = (int) request()->input('group_id');
        session_write_close();
        Group::deleteGroup($id);
        echo "Group $id deleted.";
        break;

    case 'toggle_group_active_status':
        print Group::updateGroupStatus((int) request()->input('group_id'), 'active', (request()->has('group_status') ? (int) request()->input('group_status') : 0));
        break;

    case 'toggle_group_backfill_status':
        print Group::updateGroupStatus(
            (int) request()->input('group_id'),
            'backfill',
            (request()->has('backfill_status') ? (int) request()->input('backfill_status') : 0)
        );
        break;

    case 'sharing_toggle_status':
        $pdo->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d WHERE id = %d', request()->input('site_status'), request()->input('site_id')));
        echo(request()->input('site_status') === 1 ? 'Activated' : 'Deactivated').' site '.request()->input('site_id');
        break;

    case 'sharing_toggle_enabled':
        $pdo->queryExec(sprintf('UPDATE sharing SET enabled = %d', request()->input('enabled_status')));
        echo(request()->input('enabled_status') === 1 ? 'Enabled' : 'Disabled').' sharing!';
        break;

    case 'sharing_start_position':
        $pdo->queryExec(sprintf('UPDATE sharing SET start_position = %d', request()->input('start_position')));
        echo(request()->input('start_position') === 1 ? 'Enabled' : 'Disabled').' fetching from start of group!';
        break;

    case 'sharing_reset_settings':
        $guid = $pdo->queryOneRow('SELECT site_guid FROM sharing');
        $guid = ($guid === false ? '' : $guid['site_guid']);
        (new Sharing(['Settings' => $page->settings]))->initSettings($guid);
        echo 'Re-initiated sharing settings!';
        break;

    case 'sharing_purge_site':
        $guid = $pdo->queryOneRow(sprintf('SELECT site_guid FROM sharing_sites WHERE id = %d', request()->input('purge_site')));
        if ($guid === false) {
            echo 'Error purging site '.request()->input('purge_site').'!';
        } else {
            $ids = $pdo->query(sprintf('SELECT id FROM release_comments WHERE siteid = %s', $pdo->escapeString($guid['site_guid'])));
            $total = count($ids);
            if ($total > 0) {
                foreach ($ids as $id) {
                    ReleaseComment::deleteComment($id['id']);
                }
            }
            $pdo->queryExec(sprintf('UPDATE sharing_sites SET comments = 0 WHERE id = %d', request()->input('purge_site')));
            echo 'Deleted '.$total.' comments for site '.request()->input('purge_site');
        }
        break;

    case 'sharing_toggle_posting':
        $pdo->queryExec(sprintf('UPDATE sharing SET posting = %d', request()->input('posting_status')));
        echo(request()->input('posting_status') === 1 ? 'Enabled' : 'Disabled').' posting!';
        break;

    case 'sharing_toggle_fetching':
        $pdo->queryExec(sprintf('UPDATE sharing SET fetching = %d', request()->input('fetching_status')));
        echo(request()->input('fetching_status') === 1 ? 'Enabled' : 'Disabled').' fetching!';
        break;

    case 'sharing_toggle_site_auto_enabling':
        $pdo->queryExec(sprintf('UPDATE sharing SET auto_enable = %d', request()->input('auto_status')));
        echo(request()->input('auto_status') === 1 ? 'Enabled' : 'Disabled').' automatic site enabling!';
        break;

    case 'sharing_toggle_hide_users':
        $pdo->queryExec(sprintf('UPDATE sharing SET hide_users = %d', request()->input('hide_status')));
        echo(request()->input('hide_status') === 1 ? 'Enabled' : 'Disabled').' hiding of user names!';
        break;

    case 'sharing_toggle_all_sites':
        $pdo->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d', request()->input('toggle_all')));
        break;
}
