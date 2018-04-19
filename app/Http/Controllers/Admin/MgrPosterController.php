<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\MultigroupPoster;
use App\Http\Controllers\BasePageController;

class MgrPosterController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $posters = MultigroupPoster::all('id', 'poster')->sortBy('poster');

        $postersCheck = $posters->first();

        $poster = $request->has('poster') && ! empty($request->input('poster')) ? $request->input('poster') : '';

        $this->smarty->assign(
            [
                'poster' => $poster,
                'posters' => $posters,
                'check' => $postersCheck,
            ]
        );

        $title = 'MultiGroup Posters List';
        $content = $this->smarty->fetch('posters-list.tpl');

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
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        switch ($action) {
            case 'submit':
                if ($request->input('id') === '') {
                    // Add a new mg poster.
                    $poster = MultigroupPoster::query()->create(['poster' => $request->input('poster')]);
                } else {
                    // Update an existing mg poster.
                    $poster = MultigroupPoster::query()->where('id', '=', $request->input('id'))->update(['poster' => $request->input('poster')]);
                }
                $this->smarty->assign('poster', $poster);

                return redirect('admin/posters-list');
                break;

            case 'view':
            default:
                if (! empty($request->input('id'))) {
                    $title = 'MultiGroup Poster Edit';
                    $poster = MultigroupPoster::query()->where('id', '=', $request->input('id'))->firstOrFail();
                } else {
                    $title = 'MultiGroup Poster Add';
                    $poster = '';
                }
                $this->smarty->assign('poster', $poster);
                break;
        }

        $content = $this->smarty->fetch('posters-edit.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        if ($id) {
            MultigroupPoster::query()->where('id', '=', $id)->delete();
        }

        return redirect('admin/posters-list');
    }
}
