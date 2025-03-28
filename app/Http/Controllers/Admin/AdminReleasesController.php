<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use Blacklight\Releases;
use Illuminate\Http\RedirectResponse;
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     *
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
                    $request->input('anidbid')
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

    public function destroy($id): RedirectResponse
    {
        try {
            if ($id) {
                $releases = new Releases;
                $releases->deleteMultiple($id);
                session()->flash('success', 'Release deleted successfully');
            }

            // Check if request is coming from the NZB details page
            $referer = request()->headers->get('referer');
            if ($referer && str_contains($referer, '/details/')) {
                // If coming from details page, redirect to home page
                return redirect()->route('All');
            }

            // Default redirection logic for other cases
            $redirectUrl = session('intended_redirect') ?? route('admin.release-list');
            session()->forget('intended_redirect');

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting release: '.$e->getMessage());

            return redirect()->route('admin.release-list');
        }
    }
}
