<?php

namespace App\Http\Controllers\Admin;

use Blacklight\AniDB;
use App\Models\Release;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class AnidbController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $AniDB = new AniDB();
        $title = 'AniDB List';

        $aname = '';
        if (request()->has('animetitle') && ! empty(request()->input('animetitle'))) {
            $aname = request()->input('animetitle');
        }

        $this->smarty->assign('animetitle', $aname);

        $page = request()->has('page') && is_numeric(request()->input('page')) ? request()->input('page') : 1;

        $anidblist = $AniDB->getAnimeRange($page, $aname);
        $this->smarty->assign('anidblist', $anidblist);

        $content = $this->smarty->fetch('anidb-list.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'meta_title' => $title,
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
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function edit($id)
    {
        $this->setAdminPrefs();

        $AniDB = new AniDB();

        // Set the current action.
        $action = request()->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                $AniDB->updateTitle(
                    request()->input('anidbid'),
                    request()->input('title'),
                    request()->input('type'),
                    request()->input('startdate'),
                    request()->input('enddate'),
                    request()->input('related'),
                    request()->input('similar'),
                    request()->input('creators'),
                    request()->input('description'),
                    request()->input('rating'),
                    request()->input('categories'),
                    request()->input('characters'),
                    request()->input('epnos'),
                    request()->input('airdates'),
                    request()->input('episodetitles')
                );

                return redirect('admin/anidb-list');
                break;

            case 'view':
            default:
                if (! empty($id)) {
                    $this->title = 'AniDB Edit';
                    $AniDBAPIArray = $AniDB->getAnimeInfo($id);
                    $this->smarty->assign('anime', $AniDBAPIArray);
                }
                break;
        }

        $title = 'Edit AniDB Data';
        $content = $this->smarty->fetch('anidb-edit.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     *
     * @throws \Exception
     */
    public function destroy($id)
    {
        $this->setAdminPrefs();

        $success = false;

        if (request()->has('id')) {
            $success = Release::removeAnidbIdFromReleases($id);
            $this->smarty->assign('anidbid', $id);
        }
        $this->smarty->assign('success', $success);

        $title = 'Remove anidbID from Releases';
        $content = $this->smarty->fetch('anidb-remove.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }
}
