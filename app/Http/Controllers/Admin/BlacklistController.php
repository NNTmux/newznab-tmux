<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use Blacklight\Binaries;
use Illuminate\Http\Request;

class BlacklistController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();
        $binaries = new Binaries();

        $title = 'Binary Black/Whitelist List';

        $binlist = $binaries->getBlacklist(false);
        $this->smarty->assign('binlist', $binlist);

        $content = $this->smarty->fetch('binaryblacklist-list.tpl');
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
        $binaries = new Binaries(['Settings' => $this->pdo]);
        $error = '';
        $regex = ['id' => '', 'groupname' => '', 'regex' => '', 'description' => '', 'msgcol' => 1, 'status' => 1, 'optype' => 1];
        $title = 'Binary Black/Whitelist';

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

                if ($request->input('id') === '') {
                    $binaries->addBlacklist($request->all());
                } else {
                    $binaries->updateBlacklist($request->all());
                }

                return redirect('binaryblacklist-list');
                break;

            case 'addtest':
                if ($request->has('regex') && $request->has('groupname')) {
                    $regex += [
                        'groupname' => $request->input('groupname'),
                        'regex'     => $request->input('regex'),
                        'ordinal'   => 1,
                        'status'    => 1,
                    ];
                }
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'Binary Black/Whitelist Edit';
                    $regex = $binaries->getBlacklistByID($request->input('id'));
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

        $this->smarty->assign(
            [
                'error'        => $error,
                'regex'        => $regex,
                'status_ids'   => [Category::STATUS_ACTIVE, Category::STATUS_INACTIVE],
                'status_names' => ['Yes', 'No'],
                'optype_ids'   => [1, 2],
                'optype_names' => ['Black', 'White'],
                'msgcol_ids'   => [
                    Binaries::BLACKLIST_FIELD_SUBJECT,
                    Binaries::BLACKLIST_FIELD_FROM,
                    Binaries::BLACKLIST_FIELD_MESSAGEID,
                ],
                'msgcol_names' => ['Subject', 'Poster', 'MessageId'],
            ]
        );

        $content = $this->smarty->fetch('binaryblacklist-edit.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
