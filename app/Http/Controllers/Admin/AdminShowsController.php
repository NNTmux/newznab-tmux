<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Models\Video;
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
