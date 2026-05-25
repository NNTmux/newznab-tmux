<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Models\Video;
use App\Services\TvProcessing\TvShowAdder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminShowsController extends BasePageController
{
    /**
     * Display a listing of TV shows
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'TV Shows List';

        $showname = $request->input('showname', '');
        $tvshowlist = Video::getRange($showname);

        return view('admin.shows.index', compact('tvshowlist', 'showname', 'title', 'meta_title'));
    }

    /**
     * Show the form for editing a TV show
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $action = $request->input('action', 'view');

        if ($action === 'submit') {
            if ($request->has('from') && ! empty($request->input('from'))) {
                return redirect($request->input('from'));
            }

            return redirect()->route('admin.show-list')->with('success', 'TV show updated successfully');
        }

        $show = null;
        if ($request->has('id')) {
            $show = Video::getByVideoID($request->input('id'));
        }

        if (! $show) {
            return redirect()->route('admin.show-list')->with('error', 'TV show not found');
        }

        $meta_title = $title = 'Edit TV Show Data';

        return view('admin.shows.edit', compact('show', 'title', 'meta_title'));
    }

    /**
     * Show the "Add TV Show" form and handle submissions.
     *
     * Accepts any supported external ID (tvdb, tvmaze, tmdb, trakt, imdb),
     * resolves it via TvShowAdder and persists it through the regular TV
     * provider pipeline so the videos / tv_info / videos_aliases tables and
     * the search index all stay in sync.
     */
    public function create(Request $request, TvShowAdder $adder): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Add TV Show';
        $sources = TvShowAdder::SUPPORTED_SOURCES;

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'source' => 'required|string|in:'.implode(',', $sources),
                'external_id' => 'required|string|max:32',
                'type' => 'nullable|integer|in:0,2',
            ]);

            try {
                $result = $adder->add(
                    $data['source'],
                    (string) $data['external_id'],
                    (int) ($data['type'] ?? 0),
                );
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            } catch (\Throwable $e) {
                return back()->with('error', 'Lookup failed: '.$e->getMessage())->withInput();
            }

            if ($result['videoId'] <= 0) {
                return back()->with('error', 'No show could be added for that ID.')->withInput();
            }

            $flashKey = $result['existed'] ? 'warning' : 'success';
            $flashMsg = $result['existed']
                ? sprintf('Show "%s" already exists — opened existing record.', $result['title'] ?? '')
                : sprintf('TV show "%s" added successfully.', $result['title'] ?? '');

            return redirect()->to('/admin/show-edit?id='.$result['videoId'])->with($flashKey, $flashMsg);
        }

        return view('admin.shows.add', compact('title', 'meta_title', 'sources'));
    }

    /**
     * JSON endpoint for the optional "preview before submit" button.
     */
    public function lookup(Request $request, TvShowAdder $adder): JsonResponse
    {
        $this->setAdminPrefs();

        $data = $request->validate([
            'source' => 'required|string|in:'.implode(',', TvShowAdder::SUPPORTED_SOURCES),
            'external_id' => 'required|string|max:32',
        ]);

        try {
            $preview = $adder->preview($data['source'], (string) $data['external_id']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Lookup failed: '.$e->getMessage()], 500);
        }

        if ($preview === null) {
            return response()->json(['ok' => false, 'error' => 'No show found for that ID.'], 404);
        }

        return response()->json([
            'ok' => true,
            'show' => [
                'title' => $preview['title'] ?? null,
                'summary' => $preview['summary'] ?? null,
                'started' => $preview['started'] ?? null,
                'publisher' => $preview['publisher'] ?? null,
                'poster' => $preview['poster'] ?? null,
                'ids' => [
                    'tvdb' => $preview['tvdb'] ?? 0,
                    'tvmaze' => $preview['tvmaze'] ?? 0,
                    'tmdb' => $preview['tmdb'] ?? 0,
                    'trakt' => $preview['trakt'] ?? 0,
                    'imdb' => $preview['imdb'] ?? '',
                    'tvrage' => $preview['tvrage'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Remove video ID from releases
     */
    public function destroy(Request $request): View
    {
        $this->setAdminPrefs();

        $id = $request->route('id');
        $success = false;
        $videoid = null;

        if ($id) {
            $success = Release::removeVideoIdFromReleases($id);
            $videoid = $id;
        }

        $meta_title = $title = 'Remove Video and Episode IDs from Releases';

        return view('admin.shows.remove', compact('success', 'videoid', 'title', 'meta_title'));
    }
}
