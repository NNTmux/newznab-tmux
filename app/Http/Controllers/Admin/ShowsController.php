<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Video;
use Blacklight\processing\tv\TV;
use Illuminate\Http\Request;

class ShowsController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        $title = 'TV Shows List';

        $tvshowname = ($request->has('showname') && ! empty($request->input('showname')) ? $request->input('showname') : '');

        $this->smarty->assign(
            [
                'showname'          => $tvshowname,
                'tvshowlist'        => Video::getRange($tvshowname),
            ]
        );

        $content = $this->smarty->fetch('show-list.tpl');
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
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();

        switch ($request->input('action') ?? 'view') {
            case 'submit':
                if ($request->has('from') && ! empty($request->input('from'))) {
                    header('Location:'.$request->input('from'));
                    exit;
                }

                header('Location:'.WWW_TOP.'/show-list.php');
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

        $title = 'Edit TV Show Data';
        $content = $this->smarty->fetch('show-edit.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }
}
