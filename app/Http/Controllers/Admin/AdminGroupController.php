<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\UsenetGroup;
use Illuminate\Http\Request;

class AdminGroupController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $groupname = $request->input('groupname') ?? '';
        $grouplist = UsenetGroup::getGroupsRange($groupname);
        $title = 'Group List';

        return view('admin.group-list', compact('title', 'groupname', 'grouplist'));
    }

    /**
     * @throws \Exception
     */
    public function createBulk(Request $request)
    {
        // set the current action
        $action = $request->input('action') ?? 'view';
        $groupmsglist = '';

        if ($action === 'submit') {
            if ($request->has('groupfilter') && ! empty($request->input('groupfilter'))) {
                $groupmsglist = UsenetGroup::addBulk($request->input('groupfilter'), $request->input('active'), $request->input('backfill'));
            }
        }

        $title = 'Bulk Add Newsgroups';

        return view('admin.group-bulk', compact('title', 'groupmsglist'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $group = [
            'id' => '',
            'name' => '',
            'description' => '',
            'minfilestoformrelease' => 0,
            'active' => 0,
            'backfill' => 0,
            'minsizetoformrelease' => 0,
            'first_record' => 0,
            'last_record' => 0,
            'backfill_target' => 0,
        ];

        switch ($action) {
            case 'submit':
                if (empty($request->input('id'))) {
                    // Add a new group.
                    $request->merge(['name' => UsenetGroup::isValidGroup($request->input('name'))]);
                    if ($request->input('name') !== false) {
                        UsenetGroup::addGroup($request->all());
                    }
                } else {
                    // Update an existing group.
                    UsenetGroup::updateGroup($request->all());
                }

                return redirect()->to('admin/group-list');
                break;

            case 'view':
            default:
                $title = 'Group Edit';
                if ($request->has('id')) {
                    $title = 'Newsgroup Edit';
                    $id = $request->input('id');
                    $group = UsenetGroup::getGroupByID($id);
                } else {
                    $title = 'Newsgroup Add';
                }
                break;
        }

        return view('admin.group-edit', compact('title', 'group'));
    }

    /**
     * @throws \Exception
     */
    public function active(Request $request)
    {
        $gname = '';
        if (! empty($request->input('groupname'))) {
            $gname = $request->input('groupname');
        }

        $groupname = ! empty($request->input('groupname')) ? $request->input('groupname') : '';
        $grouplist = UsenetGroup::getGroupsRange($gname, true);
        $title = 'Active Groups';

        return view('admin.group-list', compact('title', 'groupname', 'grouplist'));
    }

    /**
     * @throws \Exception
     */
    public function inactive(Request $request)
    {
        $gname = '';
        if (! empty($request->input('groupname'))) {
            $gname = $request->input('groupname');
        }

        $groupname = ! empty($request->input('groupname')) ? $request->input('groupname') : '';
        $grouplist = UsenetGroup::getGroupsRange($gname, false);
        $title = 'Inactive Groups';

        return view('admin.group-list', compact('title', 'groupname', 'grouplist'));
    }
}
