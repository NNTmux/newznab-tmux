<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\MovieInfo;
use App\Models\Release;
use App\Services\MovieService;
use Illuminate\Http\Request;

class AdminMovieController extends BasePageController
{
    protected MovieService $movieService;

    public function __construct(MovieService $movieService)
    {
        parent::__construct();
        $this->movieService = $movieService;
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request): mixed
    {
        $lastSearch = $request->input('moviesearch', '');

        if ($request->has('moviesearch')) {
            $movielist = MovieInfo::getAll($request->input('moviesearch'));
        } else {
            $movielist = MovieInfo::getAll();
        }

        $title = 'Movie List';

        return view('admin.movies.index', compact('title', 'movielist', 'lastSearch'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function create(Request $request)
    {
        if (! \defined('STDOUT')) {
            \define('STDOUT', fopen('php://stdout', 'wb'));
        }

        $title = 'Movie Add';

        // If no ID provided, show the add form
        if (! $request->has('id')) {
            return view('admin.movies.add', compact('title'));
        }

        // Validate the IMDB ID
        $id = $request->input('id');

        if (! is_numeric($id)) {
            return redirect()->back()
                ->with('error', 'Invalid IMDB ID. Please enter only numeric digits (without the "tt" prefix).')
                ->withInput();
        }

        // Check if movie already exists
        $movCheck = $this->movieService->getMovieInfo($id);

        if ($movCheck !== null) {
            return redirect()->to('/admin/movie-edit?id='.$id)
                ->with('warning', 'Movie already exists in the database. Redirected to edit page.');
        }

        // Try to fetch and add the movie from TMDB
        try {
            $movieInfo = $this->movieService->updateMovieInfo($id);

            if ($movieInfo) {
                // Link any existing releases to this movie
                $forUpdate = Release::query()->where('imdbid', $id)->get(['id']);
                if ($forUpdate !== null && $forUpdate->count() > 0) {
                    $movieInfoId = MovieInfo::query()->where('imdbid', $id)->first(['id']);
                    if ($movieInfoId !== null) {
                        foreach ($forUpdate as $rel) {
                            Release::query()->where('id', $rel->id)->update(['movieinfo_id' => $movieInfoId->id]);
                        }
                    }
                }

                return redirect()->to('/admin/movie-list')
                    ->with('success', 'Movie successfully added! IMDB ID: '.$id);
            } else {
                return redirect()->back()
                    ->with('error', 'Could not fetch movie information from TMDB/IMDB. Please verify the IMDB ID is correct.')
                    ->withInput();
            }
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error adding movie: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $title = 'Movie Edit';

        // Check if ID is provided
        if (! $request->has('id')) {
            return redirect()->to('admin/movie-list')
                ->with('error', 'No movie ID provided.');
        }

        $id = $request->input('id');
        $mov = $this->movieService->getMovieInfo($id);

        if ($mov === null) {
            return redirect()->to('admin/movie-list')
                ->with('error', 'Movie with IMDB ID '.$id.' does not exist in database.');
        }

        // Handle update from TMDB
        if ($request->has('update') && (int) $request->input('update') === 1) {
            try {
                if (! \defined('STDOUT')) {
                    \define('STDOUT', fopen('php://stdout', 'wb'));
                }

                $movieInfo = $this->movieService->updateMovieInfo($id);

                if ($movieInfo) {
                    return redirect()->back()
                        ->with('success', 'Movie information updated successfully from TMDB!');
                } else {
                    return redirect()->back()
                        ->with('error', 'Failed to update movie information from TMDB. Please try again.');
                }
            } catch (\Exception $e) {
                return redirect()->back()
                    ->with('error', 'Error updating movie: '.$e->getMessage());
            }
        }

        // Handle form submission
        $action = $request->input('action') ?? 'view';

        if ($action === 'submit') {
            try {
                $coverLoc = public_path('covers/movies/'.$id.'-cover.jpg');
                $backdropLoc = public_path('covers/movies/'.$id.'-backdrop.jpg');

                // Ensure directory exists
                if (! file_exists(public_path('covers/movies'))) {
                    mkdir(public_path('covers/movies'), 0755, true);
                }

                // Handle cover upload
                if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                    $coverFile = $request->file('cover');
                    $coverFile->move(public_path('covers/movies'), $id.'-cover.jpg');
                }

                // Handle backdrop upload
                if ($request->hasFile('backdrop') && $request->file('backdrop')->isValid()) {
                    $backdropFile = $request->file('backdrop');
                    $backdropFile->move(public_path('covers/movies'), $id.'-backdrop.jpg');
                }

                $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                $request->merge(['backdrop' => file_exists($backdropLoc) ? 1 : 0]);

                $this->movieService->update([
                    'actors' => $request->input('actors'),
                    'backdrop' => $request->input('backdrop'),
                    'cover' => $request->input('cover'),
                    'director' => $request->input('director'),
                    'genre' => $request->input('genre'),
                    'imdbid' => $id,
                    'language' => $request->input('language'),
                    'plot' => $request->input('plot'),
                    'rating' => $request->input('rating'),
                    'tagline' => $request->input('tagline'),
                    'title' => $request->input('title'),
                    'year' => $request->input('year'),
                ]);

                // Link releases to this movie
                $movieInfo = MovieInfo::query()->where('imdbid', $id)->first(['id']);
                if ($movieInfo !== null) {
                    Release::query()->where('imdbid', $id)->update(['movieinfo_id' => $movieInfo->id]);
                }

                return redirect()->to('admin/movie-list')
                    ->with('success', 'Movie updated successfully!');
            } catch (\Exception $e) {
                return redirect()->back()
                    ->with('error', 'Error saving movie: '.$e->getMessage())
                    ->withInput();
            }
        }

        return view('admin.movies.edit', compact('title', 'mov'))->with('movie', $mov);
    }
}
