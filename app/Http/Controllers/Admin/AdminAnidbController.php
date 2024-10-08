<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use Blacklight\AniDB;
use Illuminate\Http\Request;

class AdminAnidbController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request): void
    {
        $this->setAdminPrefs();

        $AniDB = new AniDB;
        $title = $meta_title = 'AniDB List';

        $aname = '';
        if ($request->has('animetitle') && ! empty($request->input('animetitle'))) {
            $aname = $request->input('animetitle');
        }

        $this->smarty->assign('animetitle', $aname);

        $anidblist = $AniDB->getAnimeRange($aname);
        $this->smarty->assign('anidblist', $anidblist);

        $content = $this->smarty->fetch('anidb-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request, int $id): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setAdminPrefs();

        $AniDB = new AniDB;

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                $AniDB->updateTitle(
                    $request->input('anidbid'),
                    $request->input('title'),
                    $request->input('type'),
                    $request->input('startdate'),
                    $request->input('enddate'),
                    $request->input('related'),
                    $request->input('similar'),
                    $request->input('creators'),
                    $request->input('description'),
                    $request->input('rating'),
                    $request->input('categories'),
                    $request->input('characters'),
                    $request->input('epnos'),
                    $request->input('airdates'),
                    $request->input('episodetitles')
                );

                return redirect()->to('admin/anidb-list');
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

        $this->smarty->assign(compact('title', 'content'));

        $this->adminrender();
    }

    /**
     * Remove the specified resource from storage.
     *
     *
     * @throws \Exception
     */
    public function destroy(Request $request, int $id): void
    {
        $this->setAdminPrefs();

        $success = false;

        if ($request->has('id')) {
            $success = Release::removeAnidbIdFromReleases($id);
            $this->smarty->assign('anidbid', $id);
        }
        $this->smarty->assign('success', $success);

        $title = 'Remove anidbID from Releases';
        $content = $this->smarty->fetch('anidb-remove.tpl');
        $this->smarty->assign(compact('title', 'content'));
        $this->adminrender();
    }
}
