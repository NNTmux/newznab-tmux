<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\UsenetGroup;
use App\Services\BlacklistService;
use App\Services\RegexService;
use Illuminate\Http\Request;

class AdminAjaxController extends BasePageController
{
    /**
     * @throws \Throwable
     */
    public function ajaxAction(Request $request)
    {
        if ($request->missing('action')) {
            return response()->json(['success' => false, 'message' => 'No action specified'], 400);
        }

        try {
            switch ($request->input('action')) {
                case 'binary_blacklist_delete':
                    $id = (int) $request->input('row_id');
                    (new BlacklistService)->deleteBlacklist($id);
                    echo "Blacklist $id deleted.";
                    break;

                case 'category_regex_delete':
                    $id = (int) $request->input('row_id');
                    (new RegexService('category_regexes'))->deleteRegex($id);
                    echo "Regex $id deleted.";
                    break;

                case 'collection_regex_delete':
                    $id = (int) $request->input('row_id');
                    (new RegexService('collection_regexes'))->deleteRegex($id);
                    echo "Regex $id deleted.";
                    break;

                case 'release_naming_regex_delete':
                    $id = (int) $request->input('row_id');
                    (new RegexService('release_naming_regexes'))->deleteRegex($id);
                    echo "Regex $id deleted.";
                    break;

                case 'group_edit_purge_all':
                case 'purge_all_groups':
                    UsenetGroup::purge();

                    return response()->json(['success' => true, 'message' => 'All groups purged successfully']);

                case 'group_edit_reset_all':
                case 'reset_all_groups':
                    UsenetGroup::resetall();

                    return response()->json(['success' => true, 'message' => 'All groups reset successfully']);

                case 'group_edit_purge_single':
                case 'purge_group':
                    $id = (int) $request->input('group_id');
                    UsenetGroup::purge($id);

                    return response()->json(['success' => true, 'message' => "Group $id purged successfully"]);

                case 'group_edit_reset_single':
                case 'reset_group':
                    $id = (int) $request->input('group_id');
                    UsenetGroup::reset($id);

                    return response()->json(['success' => true, 'message' => "Group $id reset successfully"]);

                case 'group_edit_delete_single':
                case 'delete_group':
                    $id = (int) $request->input('group_id');
                    UsenetGroup::deleteGroup($id);

                    return response()->json(['success' => true, 'message' => "Group $id deleted successfully"]);

                case 'toggle_group_active_status':
                    $groupId = (int) $request->input('group_id');
                    $status = $request->has('group_status') ? (int) $request->input('group_status') : 0;

                    $message = UsenetGroup::updateGroupStatus($groupId, 'active', $status);

                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'newStatus' => $status,
                    ]);

                case 'toggle_group_backfill_status':
                case 'toggle_group_backfill':
                    $groupId = (int) $request->input('group_id');
                    $status = $request->has('backfill_status')
                        ? (int) $request->input('backfill_status')
                        : ($request->has('backfill') ? (int) $request->input('backfill') : 0);
                    $message = UsenetGroup::updateGroupStatus($groupId, 'backfill', $status);

                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'newStatus' => $status,
                    ]);

                default:
                    return response()->json(['success' => false, 'message' => 'Unknown action'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
