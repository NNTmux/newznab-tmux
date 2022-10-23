<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use Blacklight\Releases;
use Illuminate\Http\Request;

class AdminReleasesController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Release List';

        $releaseList = Release::getReleasesRange();
        $this->smarty->assign('releaselist', $releaseList);

        $content = $this->smarty->fetch('release-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $meta_title = $title = 'Release Edit';

        // Set the current action.
        $action = ($request->input('action') ?? 'view');

        switch ($action) {
            case 'submit':
                Release::updateRelease(
                    $request->input('id'),
                    $request->input('name'),
                    $request->input('searchname'),
                    $request->input('fromname'),
                    $request->input('category'),
                    $request->input('totalpart'),
                    $request->input('grabs'),
                    $request->input('size'),
                    $request->input('postdate'),
                    $request->input('adddate'),
                    $request->input('videos_id'),
                    $request->input('tv_episodes_id'),
                    $request->input('imdbid'),
                    $request->input('anidbid'),
                    $request->input('tagnames')
                );

                $release = Release::getByGuid($request->input('guid'));
                $this->smarty->assign('release', $release);

                return redirect('details/'.$release['guid']);
                break;

            case 'view':
            default:
                $id = $request->input('id');
                $release = Release::getByGuid($id);
                $this->smarty->assign('release', $release);
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);
        $this->smarty->assign('catlist', Category::getForSelect(false));

        $content = $this->smarty->fetch('release-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($id): \Illuminate\Http\RedirectResponse
    {
        if ($id) {
            $releases = new Releases();
            $releases->deleteMultiple($id);
        }

        return redirect()->back();
    }
}
