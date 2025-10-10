<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Services\BlacklistService;
use Blacklight\Binaries;
use Illuminate\Http\Request;

class AdminBlacklistController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $svc = new BlacklistService;

        $meta_title = $title = 'Binary Black/White List';

        $binlist = $svc->getBlacklist(false);

        $this->viewData = array_merge($this->viewData, [
            'binlist' => $binlist,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.blacklist.index', $this->viewData);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|void
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $svc = new BlacklistService;
        $error = '';
        $regex = ['id' => '', 'groupname' => '', 'regex' => '', 'description' => '', 'msgcol' => 1, 'status' => 1, 'optype' => 1];
        $meta_title = $title = 'Binary Black/White list';

        switch ($request->input('action') ?? 'view') {
            case 'submit':
                if ($request->input('groupname') === '') {
                    $error = 'Group must be a valid usenet group';
                    break;
                }

                if ($request->input('regex') === '') {
                    $error = 'Regex cannot be empty';
                    break;
                }

                if (empty($request->input('id'))) {
                    $svc->addBlacklist($request->all());
                } else {
                    $svc->updateBlacklist($request->all());
                }

                return redirect()->to('admin/binaryblacklist-list');
                break;

            case 'addtest':
                if ($request->has('regex') && $request->has('groupname')) {
                    $regex += [
                        'groupname' => $request->input('groupname'),
                        'regex' => $request->input('regex'),
                        'ordinal' => 1,
                        'status' => 1,
                    ];
                }
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'Binary Black/Whitelist Edit';
                    $regex = $svc->getBlacklistByID((int) $request->input('id'));
                } else {
                    $title = 'Binary Black/Whitelist Add';
                    $regex += [
                        'status' => 1,
                        'optype' => 1,
                        'msgcol' => 1,
                    ];
                }
                break;
        }

        $this->viewData = array_merge($this->viewData, [
            'error' => $error,
            'regex' => (object) $regex,
            'status_ids' => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
            'status_names' => ['Yes', 'No'],
            'optype_ids' => [1, 2],
            'optype_names' => ['Black', 'White'],
            'msgcol_ids' => [
                Binaries::BLACKLIST_FIELD_SUBJECT,
                Binaries::BLACKLIST_FIELD_FROM,
                Binaries::BLACKLIST_FIELD_MESSAGEID,
            ],
            'msgcol_names' => ['Subject', 'Poster', 'MessageId'],
            'title' => $title,
            'meta_title' => $meta_title,
        ]);

        return view('admin.blacklist.edit', $this->viewData);
    }
}
