<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Models\Video;
use Illuminate\Http\Request;

class AdminShowsController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function index(Request $request): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'TV Shows List';

        $tvshowname = ($request->has('showname') && ! empty($request->input('showname')) ? $request->input('showname') : '');

        $this->smarty->assign(
            [
                'showname'          => $tvshowname,
                'tvshowlist'        => Video::getRange($tvshowname),
            ]
        );

        $content = $this->smarty->fetch('show-list.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws \Exception
     */
    public function edit(Request $request): void
    {
        $this->setAdminPrefs();

        switch ($request->input('action') ?? 'view') {
            case 'submit':
                if ($request->has('from') && ! empty($request->input('from'))) {
                    header('Location:'.$request->input('from'));
                    exit;
                }

                header('Location:'.url('admin/show-list'));
                break;

            case 'view':
            default:
                if ($request->has('id')) {
                    $title = 'TV Show Edit';
                    $show = Video::getByVideoID($request->input('id'));
                }
                break;
        }

        $this->smarty->assign('show', $show);

        $meta_title = $title = 'Edit TV Show Data';
        $content = $this->smarty->fetch('show-edit.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @param $id
     *
     * @throws \Exception
     */
    public function destroy($id): void
    {
        $this->setAdminPrefs();

        $success = false;

        if ($id) {
            $success = Release::removeVideoIdFromReleases($id);
            $this->smarty->assign('videoid', $id);
        }

        $this->smarty->assign('success', $success);

        $meta_title = $title = 'Remove Video and Episode IDs from Releases';
        $content = $this->smarty->fetch('show-remove.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
