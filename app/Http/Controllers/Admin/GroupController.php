<?php

namespace App\Http\Controllers\Admin;

use App\Models\Group;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class GroupController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
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
                'grouplist' => Group::getGroupsRange(config('nntmux.items_per_page'), $groupName),
            ]
        );

        $title = 'Group List';
        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * @param \Illuminate\Http\Request $request
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
                $msgs = Group::addBulk($request->input('groupfilter'), $request->input('active'), $request->input('backfill'));
            }
        } else {
            $msgs = '';
        }

        $this->smarty->assign('groupmsglist', $msgs);
        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $title = 'Bulk Add Newsgroups';
        $content = $this->smarty->fetch('group-bulk.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
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
                if ($request->input('id') === '') {
                    // Add a new group.
                    $request->merge(['name' => Group::isValidGroup($request->input('name'))]);
                    if ($request->input('name') !== false) {
                        Group::addGroup($request->all());
                    }
                } else {
                    // Update an existing group.
                    Group::updateGroup($request->all());
                }

                return redirect('group-list');
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'Newsgroup Edit';
                    $id = $request->input('id');
                    $group = Group::getGroupByID($id);
                } else {
                    $this->title = 'Newsgroup Add';
                }
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);

        $this->smarty->assign('group', $group);

        $content = $this->smarty->fetch('group-edit.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
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

        $page = $request->has('page') ? $request->input('page') : 1;

        $grouplist = Group::getGroupsRange($page, $gname, true);

        $this->smarty->assign('grouplist', $grouplist);

        $title = 'Group List';

        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
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

        $page = $request->has('page') ? $request->input('page') : 1;

        $grouplist = Group::getGroupsRange($page, $gname, false);

        $this->smarty->assign('grouplist', $grouplist);

        $title = 'Group List';

        $content = $this->smarty->fetch('group-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }
}
