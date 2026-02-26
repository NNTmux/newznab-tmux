<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\GamesService;
use App\Services\GenreService;
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
        $search = $request->input('gamesearch', '');

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
                    $coverLoc = storage_path('covers/games/').$id.'.jpg';

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $file = $request->file('cover');
                        $file_info = getimagesize($file->getPathname());
                        if (! empty($file_info)) {
                            $file->move(storage_path('covers/games/'), $id.'.jpg');
                        }
                    }

                    $cover = file_exists($coverLoc) ? 1 : 0;
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
