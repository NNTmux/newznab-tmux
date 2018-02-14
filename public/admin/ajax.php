<?php

use App\Models\Group;
use Blacklight\db\DB;
use Blacklight\Regexes;
use Blacklight\Sharing;
use Blacklight\Binaries;
use App\Models\ReleaseComment;

// This script waits for ajax queries from the web.

if (! isset($_GET['action'])) {
    exit();
}
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

// Make sure the user is an admin and logged in.
$admin = new AdminPage;
$pdo = new DB();

$settings = ['Settings' => $admin->settings];
switch ($_GET['action']) {
    case 'binary_blacklist_delete':
        $id = (int) $_GET['row_id'];
        (new Binaries($settings))->deleteBlacklist($id);
        echo "Blacklist $id deleted.";
        break;

    case 'category_regex_delete':
        $id = (int) $_GET['row_id'];
        (new Regexes(['Settings' => $admin->settings, 'Table_Name' => 'category_regexes']))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 'collection_regex_delete':
        $id = (int) $_GET['row_id'];
        (new Regexes(['Settings' => $admin->settings, 'Table_Name' => 'collection_regexes']))->deleteRegex($id);
        echo "Regex $id deleted.";
        break;

    case 'release_naming_regex_delete':
        $id = (int) $_GET['row_id'];
        (new Regexes(['Settings' => $admin->settings, 'Table_Name' => 'release_naming_regexes']))->deleteRegex($id);
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
        $id = (int) $_GET['group_id'];
        session_write_close();
        Group::purge($id);
        echo "Group $id purged.";
        break;

    case 'group_edit_reset_single':
        $id = (int) $_GET['group_id'];
        session_write_close();
        Group::reset($id);
        echo "Group $id reset.";
        break;

    case 'group_edit_delete_single':
        $id = (int) $_GET['group_id'];
        session_write_close();
        Group::deleteGroup($id);
        echo "Group $id deleted.";
        break;

    case 'toggle_group_active_status':
        print Group::updateGroupStatus((int) $_GET['group_id'], 'active', (isset($_GET['group_status']) ? (int) $_GET['group_status'] : 0));
        break;

    case 'toggle_group_backfill_status':
        print Group::updateGroupStatus(
            (int) $_GET['group_id'],
            'backfill',
            (isset($_GET['backfill_status']) ? (int) $_GET['backfill_status'] : 0)
        );
        break;

    case 'sharing_toggle_status':
        $pdo->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d WHERE id = %d', $_GET['site_status'], $_GET['site_id']));
        echo($_GET['site_status'] === 1 ? 'Activated' : 'Deactivated').' site '.$_GET['site_id'];
        break;

    case 'sharing_toggle_enabled':
        $pdo->queryExec(sprintf('UPDATE sharing SET enabled = %d', $_GET['enabled_status']));
        echo($_GET['enabled_status'] === 1 ? 'Enabled' : 'Disabled').' sharing!';
        break;

    case 'sharing_start_position':
        $pdo->queryExec(sprintf('UPDATE sharing SET start_position = %d', $_GET['start_position']));
        echo($_GET['start_position'] === 1 ? 'Enabled' : 'Disabled').' fetching from start of group!';
        break;

    case 'sharing_reset_settings':
        $guid = $pdo->queryOneRow('SELECT site_guid FROM sharing');
        $guid = ($guid === false ? '' : $guid['site_guid']);
        (new Sharing(['Settings' => $admin->settings]))->initSettings($guid);
        echo 'Re-initiated sharing settings!';
        break;

    case 'sharing_purge_site':
        $guid = $pdo->queryOneRow(sprintf('SELECT site_guid FROM sharing_sites WHERE id = %d', $_GET['purge_site']));
        if ($guid === false) {
            echo 'Error purging site '.$_GET['purge_site'].'!';
        } else {
            $ids = $pdo->query(sprintf('SELECT id FROM release_comments WHERE siteid = %s', $pdo->escapeString($guid['site_guid'])));
            $total = count($ids);
            if ($total > 0) {
                foreach ($ids as $id) {
                    ReleaseComment::deleteComment($id['id']);
                }
            }
            $pdo->queryExec(sprintf('UPDATE sharing_sites SET comments = 0 WHERE id = %d', $_GET['purge_site']));
            echo 'Deleted '.$total.' comments for site '.$_GET['purge_site'];
        }
        break;

    case 'sharing_toggle_posting':
        $pdo->queryExec(sprintf('UPDATE sharing SET posting = %d', $_GET['posting_status']));
        echo($_GET['posting_status'] === 1 ? 'Enabled' : 'Disabled').' posting!';
        break;

    case 'sharing_toggle_fetching':
        $pdo->queryExec(sprintf('UPDATE sharing SET fetching = %d', $_GET['fetching_status']));
        echo($_GET['fetching_status'] === 1 ? 'Enabled' : 'Disabled').' fetching!';
        break;

    case 'sharing_toggle_site_auto_enabling':
        $pdo->queryExec(sprintf('UPDATE sharing SET auto_enable = %d', $_GET['auto_status']));
        echo($_GET['auto_status'] === 1 ? 'Enabled' : 'Disabled').' automatic site enabling!';
        break;

    case 'sharing_toggle_hide_users':
        $pdo->queryExec(sprintf('UPDATE sharing SET hide_users = %d', $_GET['hide_status']));
        echo($_GET['hide_status'] === 1 ? 'Enabled' : 'Disabled').' hiding of user names!';
        break;

    case 'sharing_toggle_all_sites':
        $pdo->queryExec(sprintf('UPDATE sharing_sites SET enabled = %d', $_GET['toggle_all']));
        break;
}
