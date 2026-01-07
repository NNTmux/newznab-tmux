<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Content;
use Illuminate\Http\Request;

class AdminContentController extends BasePageController
{
    /**
     * Display list of all content.
     *
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $contentList = Content::query()
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();

        $this->viewData = array_merge($this->viewData, [
            'contentlist' => $contentList,
            'meta_title' => 'Content List',
            'title' => 'Content List',
        ]);

        return view('admin.content.index', $this->viewData);
    }

    /**
     * Show form to create or edit content.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function create(Request $request)
    {
        $this->setAdminPrefs();
        $meta_title = 'Content Add';

        // Set the current action.
        $action = $request->input('action') ?? 'view';

        $content = [
            'id' => '',
            'title' => '',
            'url' => '',
            'body' => '',
            'metadescription' => '',
            'metakeywords' => '',
            'contenttype' => '',
            'status' => '',
            'ordinal' => '',
            'created_at' => '',
            'role' => '',
        ];

        switch ($action) {
            case 'add':
                $meta_title = 'Content Add';
                $content['status'] = Content::STATUS_ENABLED;
                $content['contenttype'] = Content::TYPE_USEFUL;
                break;

            case 'submit':
                // Validate and add or update content
                if ($request->missing('id') || empty($request->input('id'))) {
                    $returnid = $this->addContent($request->all());
                } else {
                    $this->updateContent($request->all());
                    $returnid = $request->input('id');
                }

                return redirect('admin/content-add?id='.$returnid);

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = 'Content Edit';
                    $id = $request->input('id');
                    $content = $this->getContentById($id);
                }
                break;
        }

        $contenttypelist = [
            Content::TYPE_USEFUL => 'Useful Link',
            Content::TYPE_INDEX => 'Homepage',
        ];

        $rolelist = [
            Content::ROLE_EVERYONE => 'Everyone',
            Content::ROLE_LOGGED_IN => 'Logged in Users',
            Content::ROLE_ADMIN => 'Admins',
        ];

        $this->viewData = array_merge($this->viewData, [
            'status_ids' => [Content::STATUS_ENABLED, Content::STATUS_DISABLED],
            'status_names' => ['Enabled', 'Disabled'],
            'yesno_ids' => [1, 0],
            'yesno_names' => ['Yes', 'No'],
            'contenttypelist' => $contenttypelist,
            'content' => $content,
            'rolelist' => $rolelist,
            'meta_title' => $meta_title,
            'title' => $meta_title,
        ]);

        return view('admin.content.add', $this->viewData);
    }

    /**
     * Toggle content status (enable/disable).
     */
    public function toggleStatus(Request $request)
    {
        if ($request->has('id')) {
            $content = Content::query()->find($request->input('id'));

            if ($content) {
                $newStatus = $content->status === Content::STATUS_ENABLED
                    ? Content::STATUS_DISABLED
                    : Content::STATUS_ENABLED;

                $content->update(['status' => $newStatus, 'updated_at' => now()]);

                return response()->json([
                    'success' => true,
                    'status' => $newStatus,
                    'message' => $newStatus === Content::STATUS_ENABLED ? 'Content enabled' : 'Content disabled',
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Content not found',
        ], 404);
    }

    /**
     * Delete content by ID.
     */
    public function destroy(Request $request)
    {
        if ($request->has('id')) {
            $content = Content::query()->find($request->input('id'));

            if ($content) {
                $content->delete();

                // If AJAX request, return JSON
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Content deleted successfully',
                    ]);
                }

                return redirect()->route('admin.content-list')->with('success', 'Content deleted successfully');
            }

            // If AJAX request, return error JSON
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found',
                ], 404);
            }
        }

        $referrer = $request->server('HTTP_REFERER');

        return redirect()->to($referrer)->with('error', 'Invalid request');
    }

    /**
     * Add new content.
     */
    protected function addContent(array $data): int
    {
        // Normalize URL
        $data = $this->normalizeContentUrl($data);

        // If ordinal is 1, increment all existing ordinals
        if (($data['ordinal'] ?? 0) === 1) {
            Content::query()->where('ordinal', '>', 0)->increment('ordinal');
        }

        return Content::query()->insertGetId([
            'role' => $data['role'] ?? Content::ROLE_EVERYONE,
            'title' => $data['title'] ?? '',
            'url' => $data['url'] ?? '/',
            'body' => $data['body'] ?? '',
            'metadescription' => $data['metadescription'] ?? '',
            'metakeywords' => $data['metakeywords'] ?? '',
            'contenttype' => $data['contenttype'] ?? Content::TYPE_USEFUL,
            'status' => $data['status'] ?? Content::STATUS_ENABLED,
            'ordinal' => $data['ordinal'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update existing content.
     */
    protected function updateContent(array $data): int
    {
        // Normalize URL
        $data = $this->normalizeContentUrl($data);

        return Content::query()
            ->where('id', $data['id'])
            ->update([
                'role' => $data['role'] ?? Content::ROLE_EVERYONE,
                'title' => $data['title'] ?? '',
                'url' => $data['url'] ?? '/',
                'body' => $data['body'] ?? '',
                'metadescription' => $data['metadescription'] ?? '',
                'metakeywords' => $data['metakeywords'] ?? '',
                'contenttype' => $data['contenttype'] ?? Content::TYPE_USEFUL,
                'status' => $data['status'] ?? Content::STATUS_ENABLED,
                'ordinal' => $data['ordinal'] ?? 0,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get content by ID for admin viewing.
     */
    protected function getContentById(int $id): ?Content
    {
        return Content::query()->find($id);
    }

    /**
     * Normalize content URL to ensure proper formatting.
     */
    protected function normalizeContentUrl(array $data): array
    {
        if (isset($data['url']) && $data['url'] !== '') {
            $url = $data['url'];

            // Check if URL is external (has protocol or is a domain pattern)
            $hasProtocol = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
            $isDomain = ! str_starts_with($url, '/') && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]*\.[a-zA-Z]{2,}/', $url);

            // Only normalize internal URLs (those starting with / or root)
            if (! $hasProtocol && ! $isDomain) {
                // Ensure internal URL starts with /
                if ($url !== '/' && ! str_starts_with($url, '/')) {
                    $data['url'] = '/'.$url;
                }

                // Ensure internal URL ends with /
                if (! str_ends_with($data['url'], '/')) {
                    $data['url'] .= '/';
                }
            }
            // External URLs are left as-is
        }

        return $data;
    }
}
