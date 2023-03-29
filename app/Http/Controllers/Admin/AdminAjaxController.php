<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\UsenetGroup;
use Blacklight\Binaries;
use Blacklight\Regexes;
use Illuminate\Http\Request;

class AdminAjaxController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function ajaxAction(Request $request): void
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
        }
    }
}
