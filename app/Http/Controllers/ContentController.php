<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;

class ContentController extends BasePageController
{
    /**
     * Display content page(s).
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $role = $this->userdata->role ?? 0;

        /* The role column in the content table values are:
         * 1 = logged in users
         * 2 = admins
         *
         * The user role values are:
         * 1 = user
         * 2 = admin
         * 3 = disabled
         * 4 = moderator
         *
         * Admins and mods should be the only ones to see admin content.
         */
        $isAdmin = \in_array($role, [User::ROLE_ADMIN, User::ROLE_MODERATOR], true);

        $contentId = $request->input('id', 0);
        $contentPage = $request->input('page', false);

        if ($contentId === 0 && $contentPage === 'content') {
            // Show all content except front page
            $content = $this->getAllButFront()->all();
            $isFront = false;
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } elseif ($contentId !== 0 && $contentPage !== false) {
            // Show specific content by ID
            $contentItem = $this->getContentById($contentId, $role);
            $content = $contentItem ? [$contentItem] : [];
            $isFront = false;
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } else {
            // Show front page content
            $content = $this->getFrontPageContent()->all();
            $index = $this->getIndexContent();
            $isFront = true;
            $meta_title = $index?->title ?? 'Contents page';
            $meta_keywords = $index?->metakeywords ?? 'contents';
            $meta_description = $index?->metadescription ?? 'This is the contents page.';
        }

        if (empty($content)) {
            return response()->json(['message' => 'There is nothing to see here, no content provided.'], 404);
        }

        $this->viewData = array_merge($this->viewData, [
            'content' => $content,
            'admin' => $isAdmin,
            'front' => $isFront,
            'meta_title' => $meta_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
        ]);

        return view('content.index', $this->viewData);
    }

    /**
     * Get all active content ordered by type and ordinal.
     */
    protected function getActiveContent(): \Illuminate\Database\Eloquent\Collection
    {
        return Content::active()
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * Get all content except the front page.
     */
    protected function getAllButFront(): \Illuminate\Database\Eloquent\Collection
    {
        return Content::query()
            ->where('id', '<>', 1)
            ->orderByRaw('contenttype, COALESCE(ordinal, 1000000)')
            ->get();
    }

    /**
     * Get content by ID with role-based access control.
     */
    protected function getContentById(int $id, int $role): ?Content
    {
        return Content::query()
            ->where('id', $id)
            ->forRole($role)
            ->first();
    }

    /**
     * Get front page content.
     */
    protected function getFrontPageContent(): \Illuminate\Database\Eloquent\Collection
    {
        return Content::frontPage()->get();
    }

    /**
     * Get index content metadata.
     */
    protected function getIndexContent(): ?Content
    {
        return Content::active()->ofType(Content::TYPE_INDEX)->first();
    }
}
