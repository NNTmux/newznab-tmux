<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\UsenetGroup;
use Illuminate\Http\Request;

class AdminGroupController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $groupName = $request->input('groupname') ?? '';

        $this->smarty->assign(
            [
                'groupname' => $groupName,
                'grouplist' => UsenetGroup::getGroupsRange($groupName),
            ]
        );

        $meta_title = $title = 'Group List';
        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function createBulk(Request $request)
    {
        $this->setAdminPrefs();

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($action === 'submit') {
            if ($request->has('groupfilter') && ! empty($request->input('groupfilter'))) {
                $msgs = UsenetGroup::addBulk($request->input('groupfilter'), $request->input('active'), $request->input('backfill'));
            }
        } else {
            $msgs = '';
        }

        $this->smarty->assign('groupmsglist', $msgs);
        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $meta_title = $title = 'Bulk Add Newsgroups';
        $content = $this->smarty->fetch('group-bulk.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $group = [
            'id'                    => '',
            'name'                  => '',
            'description'           => '',
            'minfilestoformrelease' => 0,
            'active'                => 0,
            'backfill'              => 0,
            'minsizetoformrelease'  => 0,
            'first_record'          => 0,
            'last_record'           => 0,
            'backfill_target'       => 0,
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

                return redirect('admin/group-list');
                break;

            case 'view':
            default:
                $meta_title = $title = 'Group Edit';
                if ($request->has('id')) {
                    $meta_title = $title = 'Newsgroup Edit';
                    $id = $request->input('id');
                    $group = UsenetGroup::getGroupByID($id);
                } else {
                    $meta_title = $title = 'Newsgroup Add';
                }
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('group', $group);

        $content = $this->smarty->fetch('group-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function active(Request $request)
    {
        $this->setAdminPrefs();
        $gname = '';
        if (! empty($request->input('groupname'))) {
            $gname = $request->input('groupname');
        }

        $groupname = ! empty($request->input('groupname')) ? $request->input('groupname') : '';

        $this->smarty->assign('groupname', $groupname);

        $grouplist = UsenetGroup::getGroupsRange($gname, true);

        $this->smarty->assign('grouplist', $grouplist);

        $meta_title = $title = 'Group List';

        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function inactive(Request $request)
    {
        $this->setAdminPrefs();
        $gname = '';
        if (! empty($request->input('groupname'))) {
            $gname = $request->input('groupname');
        }

        $groupname = ! empty($request->input('groupname')) ? $request->input('groupname') : '';

        $this->smarty->assign('groupname', $groupname);

        $grouplist = UsenetGroup::getGroupsRange($gname, false);

        $this->smarty->assign('grouplist', $grouplist);

        $meta_title = $title = 'Group List';

        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
