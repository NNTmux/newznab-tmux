<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminReleaseListRequest;
use App\Models\Category;
use App\Models\Release;
use App\Services\Releases\ReleaseManagementService;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

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
    public function index(AdminReleaseListRequest $request): mixed
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Release List';

        $page = (int) $request->input('page', 1);
        $search = $request->searchTerm();
        $categoryId = $request->categoryId();

        $releaseList = Release::getReleasesRange($page, $search, $categoryId);
        $releaseList->appends($request->only(['search', 'category_id']));

        return view('admin.releases.index', [
            'releaselist' => $releaseList,
            'catlist' => Category::getForSelect(true),
            'title' => $title,
            'meta_title' => $meta_title,
        ]);
    }

    public function bulkCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guids' => 'required|array|min:1',
            'guids.*' => 'string',
            'categories_id' => 'required|integer|min:1|exists:categories,id',
        ]);

        $count = $this->releaseManagement->bulkUpdateCategory(
            $validated['guids'],
            (int) $validated['categories_id'],
        );

        return back()->with('success', $count.' release(s) re-categorised.');
    }

    /**
     * @return \Illuminate\Contracts\Foundation\Application|Application|RedirectResponse|Redirector|View
     *
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        // Set the current action.
        $action = $this->formAction($request);

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
                Release::clearAdminReleasesRangeCache();

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
