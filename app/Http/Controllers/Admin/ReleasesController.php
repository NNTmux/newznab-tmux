<?php

namespace App\Http\Controllers\Admin;

use App\Models\Release;
use App\Models\Category;
use Blacklight\Releases;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class ReleasesController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'Release List';

        $releaseList = Release::getReleasesRange();
        $this->smarty->assign('releaselist', $releaseList);

        $content = $this->smarty->fetch('release-list.tpl');

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
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

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
                    $request->input('anidbid')
                );

                $release = Release::getByGuid($request->input('guid'));
                $this->smarty->assign('release', $release);

                return redirect('details/'.$release['guid']);
                break;

            case 'view':
            default:
                $title = 'Release Edit';
                $id = $request->input('id');
                $release = Release::getByGuid($id);
                $this->smarty->assign('release', $release);
                break;
        }

        $this->smarty->assign('yesno_ids', [1, 0]);
        $this->smarty->assign('yesno_names', ['Yes', 'No']);
        $this->smarty->assign('catlist', Category::getForSelect(false));

        $content = $this->smarty->fetch('release-edit.tpl');

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
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($id)
    {
        if ($id) {
            $releases = new Releases(['Settings' => null]);
            $releases->deleteMultiple($id);
        }

        return redirect()->back();
    }
}
