<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\Movie;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class MovieController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'Movie List';

        $movieList = Utility::getRange('movieinfo');
        $this->smarty->assign('movielist', $movieList);

        $content = $this->smarty->fetch('movie-list.tpl');

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
    public function create(Request $request)
    {
        $this->setAdminPrefs();
        $movie = new Movie(['Settings' => $this->pdo]);

        $title = 'Movie Add';

        if ($request->has('id') && ctype_digit($request->input('id')) && \strlen($request->input('id')) === 7) {
            $id = $request->input('id');

            $movCheck = $movie->getMovieInfo($id);
            if (
                $movie->updateMovieInfo($id) && (! $movCheck || ($request->has('update') && $request->input('update') === 1))) {
                return redirect('admin/movie-list');
            }
        }

        $content = $this->smarty->fetch('movie-add.tpl');

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

        $movie = new Movie();

// set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $mov = $movie->getMovieInfo($id);

            if (! $mov) {
                $this->show404();
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = resource_path().'/covers/movies/'.$id.'-cover.jpg';
                    $backdropLoc = resource_path().'covers/movies/'.$id.'-backdrop.jpg';

                    if ($_FILES['cover']['size'] > 0) {
                        $tmpName = $_FILES['cover']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                        }
                    }

                    if ($_FILES['backdrop']['size'] > 0) {
                        $tmpName = $_FILES['backdrop']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['backdrop']['tmp_name'], $backdropLoc);
                        }
                    }

                    $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                    $request->merge(['backdrop' => file_exists($backdropLoc) ? 1 : 0]);

                    $movie->update([
                        'actors'   => $request->input('actors'),
                        'backdrop' => $request->input('backdrop'),
                        'cover'    => $request->input('cover'),
                        'director' => $request->input('director'),
                        'genre'    => $request->input('genre'),
                        'imdbid'   => $id,
                        'language' => $request->input('language'),
                        'plot'     => $request->input('plot'),
                        'rating'   => $request->input('rating'),
                        'tagline'  => $request->input('tagline'),
                        'title'    => $request->input('title'),
                        'year'     => $request->input('year'),
                    ]);

                    return redirect('admin/movie-list');
                    break;
                case 'view':
                default:
                    $title = 'Movie Edit';
                    $this->smarty->assign('movie', $mov);
                    break;
            }
        }

        $content = $this->smarty->fetch('movie-edit.tpl');

        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
            ]
        );

        $this->adminrender();
    }
}
