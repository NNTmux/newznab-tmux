<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use Blacklight\Genres;
use Blacklight\Music;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminMusicController extends BasePageController
{
    /**
     * Display a listing of music
     */
    public function index(Request $request)
    {
        $meta_title = $title = 'Music List';

        // Get search parameter
        $search = $request->input('musicsearch', '');

        if (! empty($search)) {
            $musicList = Utility::getRange('musicinfo', $search);
            $lastSearch = $search;
        } else {
            $musicList = Utility::getRange('musicinfo');
            $lastSearch = '';
        }

        return view('admin.music.index', compact('title', 'meta_title', 'musicList', 'lastSearch'));
    }

    /**
     * Show the form for editing music
     */
    public function edit(Request $request)
    {
        $music = new Music;
        $gen = new Genres;

        $meta_title = $title = 'Music Edit';

        // Set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $mus = $music->getMusicInfo($id);

            if (! $mus) {
                abort(404, 'Music not found');
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/music/'.$id.'.jpg');

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $file = $request->file('cover');
                        $file_info = getimagesize($file->getPathname());
                        if (! empty($file_info)) {
                            $file->move(storage_path('covers/music/'), $id.'.jpg');
                        }
                    }

                    $cover = file_exists($coverLoc) ? 1 : 0;
                    $salesrank = (empty($request->input('salesrank')) || ! ctype_digit($request->input('salesrank'))) ? null : $request->input('salesrank');
                    $releasedate = (empty($request->input('releasedate')) || ! strtotime($request->input('releasedate')))
                        ? $mus['releasedate']
                        : Carbon::parse($request->input('releasedate'))->timestamp;

                    $music->update(
                        $id,
                        $request->input('title'),
                        $request->input('asin'),
                        $request->input('url'),
                        $salesrank,
                        $request->input('artist'),
                        $request->input('publisher'),
                        $releasedate,
                        $request->input('year'),
                        $request->input('tracks'),
                        $cover,
                        $request->input('genre')
                    );

                    return redirect()->route('admin.music-list')->with('success', 'Music updated successfully');

                case 'view':
                default:
                    $genres = $gen->getGenres(Genres::MUSIC_TYPE);

                    return view('admin.music.edit', compact('title', 'meta_title', 'mus', 'genres'));
            }
        }

        abort(404, 'Music ID required');
    }
}
