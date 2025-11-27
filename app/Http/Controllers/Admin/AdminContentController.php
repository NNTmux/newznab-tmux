<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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
                $content['contenttype'] = Content::TYPE_ARTICLE;
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
            Content::TYPE_ARTICLE => 'Article',
            Content::TYPE_INDEX => 'Homepage'
        ];

        $rolelist = [
            Content::ROLE_EVERYONE => 'Everyone',
            Content::ROLE_LOGGED_IN => 'Logged in Users',
            Content::ROLE_ADMIN => 'Admins'
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
     * Delete content by ID.
     */
    public function destroy(Request $request): \Illuminate\Routing\Redirector|RedirectResponse|\Illuminate\Contracts\Foundation\Application
    {
        if ($request->has('id')) {
            Content::query()->where('id', $request->input('id'))->delete();
        }

        $referrer = $request->server('HTTP_REFERER');

        return redirect()->to($referrer);
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
            'contenttype' => $data['contenttype'] ?? Content::TYPE_ARTICLE,
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
                'contenttype' => $data['contenttype'] ?? Content::TYPE_ARTICLE,
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
        if (isset($data['url'])) {
            // Ensure URL starts with /
            if ($data['url'] !== '/' && !str_starts_with($data['url'], '/')) {
                $data['url'] = '/'.$data['url'];
            }

            // Ensure URL ends with /
            if (!str_ends_with($data['url'], '/')) {
                $data['url'] .= '/';
            }
        }

        return $data;
    }
}
