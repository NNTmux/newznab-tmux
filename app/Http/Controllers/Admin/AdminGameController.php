<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\GamesService;
use App\Services\GenreService;
use App\Services\ReleaseImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminGameController extends BasePageController
{
    /**
     * Display a listing of games
     */
    public function index(Request $request): mixed
    {
        $game = new GamesService;

        $meta_title = $title = 'Game List';

        // Get search parameter
        $search = $this->scalarInput($request, 'gamesearch');

        if (! empty($search)) {
            $gamelist = $game->getRange($search);
            $lastSearch = $search;
        } else {
            $gamelist = $game->getRange();
            $lastSearch = '';
        }

        return view('admin.games.index', compact('title', 'meta_title', 'gamelist', 'lastSearch'));
    }

    /**
     * Show the form for editing a game
     */
    public function edit(Request $request): mixed
    {
        $games = new GamesService;
        $gen = new GenreService;
        $meta_title = $title = 'Game Edit';

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $game = $games->getGamesInfoById($id);

            if (! $game) {
                abort(404, 'Game not found');
            }

            switch ($action) {
                case 'submit':
                    $coverDirectory = storage_path('covers/games/');
                    $imageService = app(ReleaseImageService::class);

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $imageService->saveUploadedImage((string) $id, $request->file('cover'), $coverDirectory);
                    }

                    $cover = (int) $imageService->imageExists($coverDirectory, (string) $id);
                    $releasedate = (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate')))
                        ? $game['releasedate']
                        : Carbon::parse($request->input('releasedate'))->timestamp;

                    $games->update(
                        $id,
                        $request->input('title'),
                        $request->input('asin'),
                        $request->input('url'),
                        $request->input('publisher'),
                        $releasedate,
                        $request->input('esrb'),
                        $cover,
                        $request->input('trailerurl'),
                        $request->input('genre')
                    );

                    return redirect()->route('admin.game-list')->with('success', 'Game updated successfully');

                case 'view':
                default:
                    $genres = $gen->getGenres((string) GenreService::GAME_TYPE);

                    return view('admin.games.edit', compact('title', 'meta_title', 'game', 'genres'));
            }
        }

        abort(404, 'Game ID required');
    }
}
