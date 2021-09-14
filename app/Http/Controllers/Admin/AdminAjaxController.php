<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\ReleaseComment;
use App\Models\Sharing;
use App\Models\SharingSite;
use App\Models\UsenetGroup;
use Blacklight\Binaries;
use Blacklight\Regexes;
use Illuminate\Http\Request;

class AdminAjaxController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Throwable
     */
    public function ajaxAction(Request $request)
    {
        if ($request->missing('action')) {
            exit();
        }

        $settings = ['Settings' => $this->settings];
        switch ($request->input('action')) {
            case 'binary_blacklist_delete':
                $id = (int) $request->input('row_id');
                (new Binaries($settings))->deleteBlacklist($id);
                echo "Blacklist $id deleted.";
                break;

            case 'category_regex_delete':
                $id = (int) $request->input('row_id');
                (new Regexes(['Settings' => $this->settings, 'Table_Name' => 'category_regexes']))->deleteRegex($id);
                echo "Regex $id deleted.";
                break;

            case 'collection_regex_delete':
                $id = (int) $request->input('row_id');
                (new Regexes(['Settings' => $this->settings, 'Table_Name' => 'collection_regexes']))->deleteRegex($id);
                echo "Regex $id deleted.";
                break;

            case 'release_naming_regex_delete':
                $id = (int) $request->input('row_id');
                (new Regexes(['Settings' => $this->settings, 'Table_Name' => 'release_naming_regexes']))->deleteRegex($id);
                echo "Regex $id deleted.";
                break;

            case 'group_edit_purge_all':
                UsenetGroup::purge();
                echo 'All groups purged.';
                break;

            case 'group_edit_reset_all':
                UsenetGroup::resetall();
                echo 'All groups reset.';
                break;

            case 'group_edit_purge_single':
                $id = (int) $request->input('group_id');
                UsenetGroup::purge($id);
                echo "Group $id purged.";
                break;

            case 'group_edit_reset_single':
                $id = (int) $request->input('group_id');
                UsenetGroup::reset($id);
                echo "Group $id reset.";
                break;

            case 'group_edit_delete_single':
                $id = (int) $request->input('group_id');
                UsenetGroup::deleteGroup($id);
                echo "Group $id deleted.";
                break;

            case 'toggle_group_active_status':
                print UsenetGroup::updateGroupStatus((int) $request->input('group_id'), 'active', ($request->has('group_status') ? (int) $request->input('group_status') : 0));
                break;

            case 'toggle_group_backfill_status':
                print UsenetGroup::updateGroupStatus(
                    (int) $request->input('group_id'),
                    'backfill',
                    ($request->has('backfill_status') ? (int) $request->input('backfill_status') : 0)
                );
                break;

            case 'sharing_toggle_status':
                SharingSite::query()->where('id', $request->input('site_id'))->update(['enabled' => $request->input('site_status')]);
                echo($request->input('site_status') === 1 ? 'Activated' : 'Deactivated').' site '.$request->input('site_id');
                break;

            case 'sharing_toggle_enabled':
                Sharing::query()->update(['enabled' => $request->input('enabled_status')]);
                echo($request->input('enabled_status') === 1 ? 'Enabled' : 'Disabled').' sharing!';
                break;

            case 'sharing_start_position':
                Sharing::query()->update(['start_position' => $request->input('start_position')]);
                echo($request->input('start_position') === 1 ? 'Enabled' : 'Disabled').' fetching from start of group!';
                break;

            case 'sharing_reset_settings':
                $guid = Sharing::query()->first(['site_guid']);
                $guid = ($guid === null ? '' : $guid['site_guid']);
                (new \Blacklight\Sharing(['Settings' => $this->settings]))->initSettings($guid);
                echo 'Re-initiated sharing settings!';
                break;

            case 'sharing_purge_site':
                $guid = SharingSite::query()->where('id', $request->input('purge_site'))->first(['site_guid']);
                if ($guid === null) {
                    echo 'Error purging site '.$request->input('purge_site').'!';
                } else {
                    $ids = ReleaseComment::query()->where('siteid', $guid['site_guid'])->get(['id']);
                    $total = $ids->count();
                    if ($total > 0) {
                        foreach ($ids as $id) {
                            ReleaseComment::deleteComment($id['id']);
                        }
                    }
                    SharingSite::query()->where('id', $request->input('purge_site'))->update(['comments' => 0]);
                    echo 'Deleted '.$total.' comments for site '.$request->input('purge_site');
                }
                break;

            case 'sharing_toggle_posting':
                Sharing::query()->update(['posting' => $request->input('posting_status')]);
                echo($request->input('posting_status') === 1 ? 'Enabled' : 'Disabled').' posting!';
                break;

            case 'sharing_toggle_fetching':
                Sharing::query()->update(['fetching' => $request->input('fetching_status')]);
                echo($request->input('fetching_status') === 1 ? 'Enabled' : 'Disabled').' fetching!';
                break;

            case 'sharing_toggle_site_auto_enabling':
                Sharing::query()->update(['auto_enable' => $request->input('auto_status')]);
                echo($request->input('auto_status') === 1 ? 'Enabled' : 'Disabled').' automatic site enabling!';
                break;

            case 'sharing_toggle_hide_users':
                Sharing::query()->update(['hide_users' => $request->input('hide_status')]);
                echo($request->input('hide_status') === 1 ? 'Enabled' : 'Disabled').' hiding of user names!';
                break;

            case 'sharing_toggle_all_sites':
                SharingSite::query()->update(['enabled' => $request->input('toggle_all')]);
                break;
        }
    }
}
