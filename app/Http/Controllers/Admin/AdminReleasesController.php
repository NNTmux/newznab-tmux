<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Category;
use App\Models\Release;
use App\Services\Releases\ReleaseManagementService;
use Illuminate\Http\Request;

class AdminReleasesController extends BasePageController
{
    private ReleaseManagementService $releaseManagement;

    public function __construct(ReleaseManagementService $releaseManagement)
    {
        parent::__construct();
        $this->releaseManagement = $releaseManagement;
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request): mixed
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Release List';

        $page = $request->input('page', 1);
        $releaseList = Release::getReleasesRange($page);

        return view('admin.releases.index', [
            'releaselist' => $releaseList,
            'title' => $title,
            'meta_title' => $meta_title,
        ]);
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        // Set the current action.
        $action = ($request->input('action') ?? 'view');

        switch ($action) {
            case 'submit':
                Release::updateRelease(
                    $request->input('id'),
                    $request->input('name'),
                    $request->input('searchname'),
                    $request->input('fromname'),
                    $request->input('category'),
                    $request->input('totalpart'),
                    $request->input('grabs'),
                    $request->input('size'),
                    $request->input('postdate'),
                    $request->input('adddate'),
                    $request->input('videos_id'),
                    $request->input('tv_episodes_id'),
                    $request->input('imdbid'),
                    $request->input('anidbid')
                );

                $release = Release::getByGuid($request->input('guid'));

                return redirect('details/'.$release['guid'])->with('success', 'Release updated successfully');

            case 'view':
            default:
                $id = $request->input('id');
                $release = Release::getByGuid($id);
                break;
        }

        $yesno_ids = [1, 0];
        $yesno_names = ['Yes', 'No'];
        $catlist = Category::getForSelect(false);

        return view('admin.releases.edit', [
            'release' => $release,
            'yesno_ids' => $yesno_ids,
            'yesno_names' => $yesno_names,
            'catlist' => $catlist,
            'title' => 'Release Edit',
            'meta_title' => 'Release Edit',
        ]);
    }

    public function destroy(mixed $id): mixed
    {
        try {
            if ($id) {
                $this->releaseManagement->deleteMultiple($id);

                // Handle AJAX requests
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Release deleted successfully',
                    ]);
                }

                session()->flash('success', 'Release deleted successfully');
            }

            // Handle AJAX requests
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No release ID provided',
                ], 400);
            }

            // Check if request is coming from the NZB details page
            $referer = request()->headers->get('referer');
            if ($referer && str_contains($referer, '/details/')) {
                // If coming from details page, redirect to home page
                return redirect()->route('All');
            }

            // Default redirection logic for other cases
            $redirectUrl = session('intended_redirect') ?? route('admin.release-list');
            session()->forget('intended_redirect');

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            // Handle AJAX requests
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error deleting release: '.$e->getMessage(),
                ], 500);
            }

            session()->flash('error', 'Error deleting release: '.$e->getMessage());

            return redirect()->route('admin.release-list');
        }
    }
}
