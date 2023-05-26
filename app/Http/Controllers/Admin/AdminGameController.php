<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\Games;
use Blacklight\Genres;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminGameController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();
        $game = new Games(['Settings' => null]);

        $meta_title = $title = 'Game List';

        $gamelist = $game->getRange();

        $this->smarty->assign('gamelist', $gamelist);

        $content = $this->smarty->fetch('game-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|void
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $this->setAdminPrefs();
        $games = new Games(['Settings' => null]);
        $gen = new Genres(['Settings' => null]);
        $meta_title = $title = 'Game Edit';

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $game = $games->getGamesInfoById($id);

            if (! $game) {
                $this->show404();
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/games/').$id.'.jpg';

                    if ($_FILES['cover']['size'] > 0) {
                        $tmpName = $_FILES['cover']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                        }
                    }

                    $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                    $request->merge(['releasedate' => (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate'))) ? $game['releasedate'] : Carbon::parse($request->input('releasedate'))->timestamp]);

                    $games->update($id, $request->input('title'), $request->input('asin'), $request->input('url'), $request->input('publisher'), $request->input('releasedate'), $request->input('esrb'), $request->input('cover'), $request->input('trailerurl'), $request->input('genre'));

                    return redirect()->to('admin/game-list');

                    break;

                case 'view':
                default:
                    $this->smarty->assign('game', $game);
                    $this->smarty->assign('genres', $gen->getGenres(Genres::GAME_TYPE));
                    break;
            }
        }

        $content = $this->smarty->fetch('game-edit.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }
}
