<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\MovieInfo;
use App\Models\Release;
use Blacklight\Movie;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class AdminMovieController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Movie List';

        $movieList = Utility::getRange('movieinfo');
        $this->smarty->assign('movielist', $movieList);

        $content = $this->smarty->fetch('movie-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function create(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        if (! \defined('STDOUT')) {
            \define('STDOUT', fopen('php://stdout', 'wb'));
        }
        $this->setAdminPrefs();
        $movie = new Movie(['Settings' => null]);

        $meta_title = $title = 'Movie Add';

        $id = $request->input('id');

        if ($request->has('id') && is_numeric($request->input('id'))) {
            $movCheck = $movie->getMovieInfo($id);
            $movieInfo = $movie->updateMovieInfo($id);
            if ($movieInfo && ($movCheck === null || ($request->has('update') && (int) $request->input('update') === 1))) {
                $forUpdate = Release::query()->where('imdbid', $id)->get(['id']);
                if ($forUpdate !== null) {
                    $movieInfoId = MovieInfo::query()->where('imdbid', $id)->first(['id']);
                    if ($movieInfoId !== null) {
                        foreach ($forUpdate as $movie) {
                            Release::query()->where('id', $movie->id)->update(['movieinfo_id' => $movieInfoId->id]);
                        }
                    }
                }
                if (($request->has('update') && (int) $request->input('update') === 1)) {
                    return back()->withInput();
                }

                return redirect('/admin/movie-list');
            }

            return redirect('/admin/movie-list');
        }

        $content = $this->smarty->fetch('movie-add.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     *
     * @throws \Exception
     */
    public function edit(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setAdminPrefs();

        $movie = new Movie();
        $meta_title = $title = 'Add Movie';

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $mov = $movie->getMovieInfo($id);

            if ($mov === null) {
                abort(404, 'Movie you requested does not exist in database');
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

                    $movieInfo = MovieInfo::query()->where('imdbid', $id)->first(['id']);
                    if ($movieInfo !== null) {
                        Release::query()->where('imdbid', $id)->update(['movieinfo_id' => $movieInfo->id]);
                    }

                    return redirect('admin/movie-list');
                    break;
                case 'view':
                default:
                    $meta_title = $title = 'Movie Edit';
                    $this->smarty->assign('movie', $mov);
                    break;
            }
        }

        $content = $this->smarty->fetch('movie-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
