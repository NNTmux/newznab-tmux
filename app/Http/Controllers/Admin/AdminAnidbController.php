<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Release;
use App\Services\AnidbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAnidbController extends BasePageController
{
    /**
     * Display a listing of AniDB entries
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $anidbService = new AnidbService;
        $title = $meta_title = 'AniDB List';

        $animetitle = $request->input('animetitle', '');
        $anidblist = $anidbService->getAnimeRange($animetitle);

        return view('admin.anidb.index', compact('anidblist', 'animetitle', 'title', 'meta_title'));
    }

    /**
     * Show the form for editing an AniDB entry
     */
    public function edit(Request $request, int $id): View|RedirectResponse
    {
        $this->setAdminPrefs();

        $anidbService = new AnidbService;

        // Set the current action.
        $action = $request->input('action', 'view');

        switch ($action) {
            case 'submit':
                $anidbService->updateTitle(
                    $request->input('anidbid'),
                    $request->input('title'),
                    $request->input('type'),
                    $request->input('startdate'),
                    $request->input('enddate'),
                    $request->input('related'),
                    $request->input('similar'),
                    $request->input('creators'),
                    $request->input('description'),
                    $request->input('rating'),
                    $request->input('categories'),
                    $request->input('characters'),
                    $request->input('epnos'),
                    $request->input('airdates'),
                    $request->input('episodetitles')
                );

                return redirect()->route('admin.anidb-list')->with('success', 'AniDB entry updated successfully');

            case 'view':
            default:
                if (! empty($id)) {
                    $title = $meta_title = 'AniDB Edit';
                    $anime = $anidbService->getAnimeInfo($id);

                    return view('admin.anidb.edit', compact('anime', 'title', 'meta_title'));
                }
                break;
        }

        return redirect()->route('admin.anidb-list');
    }

    /**
     * Remove AniDB ID from releases
     */
    public function destroy(Request $request, int $id): View
    {
        $this->setAdminPrefs();

        $success = false;
        $anidbid = $id;

        if ($id) {
            $success = Release::removeAnidbIdFromReleases($id);
        }

        $title = $meta_title = 'Remove AniDB ID from Releases';

        return view('admin.anidb.remove', compact('success', 'anidbid', 'title', 'meta_title'));
    }
}
