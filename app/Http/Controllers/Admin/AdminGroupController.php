<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminGroupListRequest;
use App\Models\UsenetGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminGroupController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(AdminGroupListRequest $request): mixed
    {
        $groupname = $request->groupName();
        $grouplist = UsenetGroup::getGroupsRange($groupname);
        $title = 'Group List';

        return view('admin.groups.index', compact('title', 'groupname', 'grouplist'));
    }

    /**
     * @throws \Exception
     */
    public function createBulk(Request $request): mixed
    {
        // set the current action
        $action = $request->input('action') ?? 'view';
        $groupmsglist = '';

        if ($action === 'submit') {
            $groupFilter = $request->input('groupfilter');

            if (is_string($groupFilter) && $groupFilter !== '') {
                $active = $request->has('active') ? $request->integer('active') : 1;
                $backfill = $request->has('backfill') ? $request->integer('backfill') : 1;

                $groupmsglist = UsenetGroup::addBulk($groupFilter, $active, $backfill);
            }
        }

        $title = 'Bulk Add Newsgroups';

        return view('admin.groups.bulk', compact('title', 'groupmsglist'));
    }

    /**
     * @return RedirectResponse|View
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
                $minimumSize = $request->input('minsizetoformrelease');
                if ($minimumSize !== null && trim((string) $minimumSize) !== '') {
                    try {
                        $request->merge(['minsizetoformrelease' => parse_group_file_size($minimumSize)]);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages([
                            'minsizetoformrelease' => $exception->getMessage(),
                        ]);
                    }
                }

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

        return view('admin.groups.edit', compact('title', 'group'));
    }

    /**
     * @throws \Exception
     */
    public function active(AdminGroupListRequest $request): mixed
    {
        $groupname = $request->groupName();
        $grouplist = UsenetGroup::getGroupsRange($groupname, true);
        $title = 'Active Groups';

        return view('admin.groups.index', compact('title', 'groupname', 'grouplist'));
    }

    /**
     * @throws \Exception
     */
    public function inactive(AdminGroupListRequest $request): mixed
    {
        $groupname = $request->groupName();
        $grouplist = UsenetGroup::getGroupsRange($groupname, false);
        $title = 'Inactive Groups';

        return view('admin.groups.index', compact('title', 'groupname', 'grouplist'));
    }
}
