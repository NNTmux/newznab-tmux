<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Services\GenreService;
use App\Services\MusicService;
use App\Services\ReleaseImageService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminMusicController extends BasePageController
{
    /**
     * Display a listing of music
     */
    public function index(Request $request): mixed
    {
        $meta_title = $title = 'Music List';

        $lastSearch = trim($this->scalarInput($request, 'musicsearch'));

        $musicList = DB::table('musicinfo')
            ->when($lastSearch !== '', static function (Builder $query) use ($lastSearch): void {
                $query->where(static function (Builder $query) use ($lastSearch): void {
                    $query->whereLike('title', '%'.$lastSearch.'%')
                        ->orWhereLike('artist', '%'.$lastSearch.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(config('nntmux.items_per_page'));

        return view('admin.music.index', compact('title', 'meta_title', 'musicList', 'lastSearch'));
    }

    /**
     * Show the form for editing music
     */
    public function edit(Request $request): mixed
    {
        $music = new MusicService;
        $gen = new GenreService;

        $meta_title = $title = 'Music Edit';

        // Set the current action
        $action = $this->formAction($request);

        if ($request->has('id')) {
            $id = (int) $request->input('id');
            $mus = $music->getMusicInfo($id);

            if (! $mus) {
                abort(404, 'Music not found');
            }

            switch ($action) {
                case 'submit':
                    $coverDirectory = storage_path('covers/music/');
                    $imageService = app(ReleaseImageService::class);

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $imageService->saveUploadedImage((string) $id, $request->file('cover'), $coverDirectory);
                    }

                    $cover = (int) $imageService->imageExists($coverDirectory, (string) $id);
                    $salesrankInput = $request->input('salesrank');
                    $salesrank = (empty($salesrankInput) || ! ctype_digit((string) $salesrankInput)) ? null : (int) $salesrankInput;
                    $releasedateInput = $request->input('releasedate');
                    $releasedate = (empty($releasedateInput) || ! strtotime($releasedateInput))
                        ? $mus->releasedate
                        : Carbon::parse($releasedateInput)->toDateTimeString();

                    $genreInput = $request->input('genre');
                    $genreId = ! empty($genreInput) ? (int) $genreInput : null;

                    $music->update(
                        $id,
                        (string) $request->input('title', ''),
                        $request->input('asin'),
                        $request->input('url'),
                        $salesrank,
                        $request->input('artist'),
                        $request->input('publisher'),
                        $releasedate,
                        $request->input('year'),
                        $request->input('tracks'),
                        $cover,
                        $genreId
                    );

                    return redirect()->route('admin.music-list')->with('success', 'Music updated successfully');

                case 'view':
                default:
                    $genres = $gen->getGenres((string) GenreService::MUSIC_TYPE);

                    return view('admin.music.edit', compact('title', 'meta_title', 'mus', 'genres'));
            }
        }

        abort(404, 'Music ID required');
    }
}
