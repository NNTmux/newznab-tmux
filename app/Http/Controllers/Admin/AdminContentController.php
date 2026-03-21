<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Http\Requests\Admin\AdminContentRequest;
use App\Models\Content;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminContentController extends BasePageController
{
    /**
     * Display list of all content.
     *
     * @throws \Exception
     */
    public function index(): mixed
    {
        $this->setAdminPrefs();

        $contentList = Content::query()->ordered()->get();
        $contentGroups = $contentList
            ->groupBy('contenttype')
            ->sortKeys()
            ->map(function ($items, $contentType) {
                /** @var Collection<int, Content> $items */
                return [
                    'contenttype' => (int) $contentType,
                    'label' => $items->first()?->getContentTypeLabel() ?? 'Unknown',
                    'items' => $items,
                ];
            })
            ->values();

        $this->viewData = array_merge($this->viewData, [
            'contentlist' => $contentList,
            'contentGroups' => $contentGroups,
            'meta_title' => 'Content List',
            'title' => 'Content List',
        ]);

        return view('admin.content.index', $this->viewData);
    }

    /**
     * Show form to create or edit content.
     *
     * @return \Illuminate\Contracts\Foundation\Application|Application|RedirectResponse|Redirector|View
     *
     * @throws \Exception
     */
    public function create(AdminContentRequest $request)
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
                $validated = $request->validated();

                if ($request->missing('id') || empty($request->input('id'))) {
                    $returnid = $this->addContent($validated);
                    $message = 'Content created successfully';
                } else {
                    $this->updateContent($validated);
                    $returnid = (int) $request->input('id');
                    $message = 'Content updated successfully';
                }

                return redirect()
                    ->route('admin.content-add', ['id' => $returnid])
                    ->with('success', $message);

            case 'view':
            default:
                if ($request->has('id')) {
                    $meta_title = 'Content Edit';
                    $id = (int) $request->input('id');
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
    public function toggleStatus(Request $request): mixed
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
     * Persist a new global content order.
     */
    public function reorder(Request $request): mixed
    {
        $validated = $request->validate([
            'contenttype' => ['required', 'integer'],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct', 'exists:content,id'],
        ]);

        $contentType = (int) $validated['contenttype'];
        $orderedIds = array_map('intval', $validated['ordered_ids']);
        $expectedIds = Content::query()
            ->where('contenttype', $contentType)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (
            count($orderedIds) !== count($expectedIds)
            || array_diff($orderedIds, $expectedIds) !== []
            || array_diff($expectedIds, $orderedIds) !== []
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Reorder payload must include every content row for the selected group.',
            ], 422);
        }

        $timestamp = now();
        $ordinals = [];

        DB::transaction(function () use ($contentType, $orderedIds, $timestamp, &$ordinals): void {
            foreach ($orderedIds as $index => $contentId) {
                $ordinal = $index + 1;

                Content::query()
                    ->whereKey($contentId)
                    ->where('contenttype', $contentType)
                    ->update([
                        'ordinal' => $ordinal,
                        'updated_at' => $timestamp,
                    ]);

                $ordinals[$contentId] = $ordinal;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Content order updated successfully',
            'contenttype' => $contentType,
            'ordinals' => $ordinals,
        ]);
    }

    /**
     * Delete content by ID.
     */
    public function destroy(Request $request): mixed
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
     *
     * @param  array<string, mixed>  $data
     */
    protected function addContent(array $data): int
    {
        // Normalize URL
        $data = $this->normalizeContentUrl($data);

        return Content::query()->insertGetId([
            'role' => $data['role'] ?? Content::ROLE_EVERYONE,
            'title' => $data['title'] ?? '',
            'url' => $data['url'] ?? '/',
            'body' => $data['body'] ?? '',
            'metadescription' => $data['metadescription'] ?? '',
            'metakeywords' => $data['metakeywords'] ?? '',
            'contenttype' => $data['contenttype'] ?? Content::TYPE_USEFUL,
            'status' => $data['status'] ?? Content::STATUS_ENABLED,
            'ordinal' => $this->nextBottomOrdinal((int) ($data['contenttype'] ?? Content::TYPE_USEFUL)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update existing content.
     *
     * @param  array<string, mixed>  $data
     */
    protected function updateContent(array $data): int
    {
        // Normalize URL
        $data = $this->normalizeContentUrl($data);

        $content = Content::query()->findOrFail($data['id']);
        $updatedContentType = (int) ($data['contenttype'] ?? $content->contenttype);
        $ordinal = $content->contenttype === $updatedContentType
            ? $content->ordinal
            : $this->nextBottomOrdinal($updatedContentType);

        return Content::query()
            ->whereKey($content->id)
            ->update([
                'role' => $data['role'] ?? Content::ROLE_EVERYONE,
                'title' => $data['title'] ?? '',
                'url' => $data['url'] ?? '/',
                'body' => $data['body'] ?? '',
                'metadescription' => $data['metadescription'] ?? '',
                'metakeywords' => $data['metakeywords'] ?? '',
                'contenttype' => $updatedContentType,
                'status' => $data['status'] ?? Content::STATUS_ENABLED,
                'ordinal' => $ordinal,
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
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
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

    /**
     * Get the ordinal that places new content at the bottom of the list.
     */
    protected function nextBottomOrdinal(int $contentType): int
    {
        $maximumOrdinal = Content::query()
            ->where('contenttype', $contentType)
            ->max('ordinal');

        if ($maximumOrdinal === null) {
            return 1;
        }

        return (int) $maximumOrdinal + 1;
    }
}
