<?php

namespace App\Http\Controllers\Admin;

use Blacklight\Music;
use Blacklight\Genres;
use Illuminate\Http\Request;
use Blacklight\utility\Utility;
use App\Http\Controllers\BasePageController;

class MusicController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'Music List';

        $musicList = Utility::getRange('musicinfo');

        $this->smarty->assign('musiclist', $musicList);

        $content = $this->smarty->fetch('music-list.tpl');
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
        $music = new Music();
        $gen = new Genres();

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $mus = $music->getMusicInfo($id);

            if (! $mus) {
                $this->show404();
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = resource_path().'/covers/music/'.$id.'.jpg';

                    if ($_FILES['cover']['size'] > 0) {
                        $tmpName = $_FILES['cover']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                        }
                    }

                    $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                    $request->merge(['salesrank' => (empty($request->input('salesrank')) || ! ctype_digit($request->input('salesrank'))) ? 'null' : $request->input('salesrank')]);
                    $request->merge(['releasedate' => (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate'))) ? $mus['releasedate'] : Carbon::parse($request->input('releasedate'))->timestamp]);

                    $music->update($id, $request->input('title'), $request->input('asin'), $request->input('url'), $request->input('salesrank'), $request->input('artist'), $request->input('publisher'), $request->input('releasedate'), $request->input('year'), $request->input('tracks'), $request->input('cover'), $request->input('genre'));

                    return redirect('admin/music-list');

                    break;
                case 'view':
                default:
                    $title = 'Music Edit';
                    $this->smarty->assign('music', $mus);
                    $this->smarty->assign('genres', $gen->getGenres(Genres::MUSIC_TYPE));
                    break;
            }
        }

        $content = $this->smarty->fetch('music-edit.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }
}
