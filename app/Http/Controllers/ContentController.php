<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Content;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends BasePageController
{
    /**
     * Display content page(s).
     *
     * @return JsonResponse|View
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $role = $this->contentRole();

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
        $isAdmin = \in_array($role, [UserRole::ADMIN->value, UserRole::MODERATOR->value], true);

        $contentId = $this->integerInput($request, 'id');
        $contentPage = $this->scalarInput($request, 'page');

        if ($contentId === 0 && $contentPage === 'content') {
            // Show all content except front page
            $content = $this->getAllButFront($role)->all();
            $isFront = false;
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } elseif ($contentId !== 0 && $contentPage !== '') {
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
            $meta_title = filled($index?->title) ? $index->title : 'Contents page';
            $meta_keywords = $index->metakeywords ?? 'contents';
            $meta_description = $index->metadescription ?? 'This is the contents page.';
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

    private function contentRole(): int
    {
        if (! isset($this->userdata)) {
            return Content::ROLE_EVERYONE;
        }

        if ($this->userdata->hasRole('Admin')) {
            return UserRole::ADMIN->value;
        }

        if ($this->userdata->hasRole('Moderator')) {
            return UserRole::MODERATOR->value;
        }

        return Content::ROLE_LOGGED_IN;
    }

    /**
     * Get all active content ordered by type and ordinal.
     *
     * @return Collection<int, mixed>
     */
    protected function getActiveContent(): Collection
    {
        return Content::active()->ordered()->get();
    }

    /**
     * Get all content except the front page.
     *
     * @return Collection<int, mixed>
     */
    protected function getAllButFront(int $role): Collection
    {
        return Content::query()
            ->where('id', '<>', 1)
            ->active()
            ->forRole($role)
            ->ordered()
            ->get();
    }

    /**
     * Get content by ID with role-based access control.
     */
    protected function getContentById(int $id, int $role): ?Content
    {
        return Content::query()
            ->where('id', $id)
            ->active()
            ->forRole($role)
            ->first();
    }

    /**
     * Get front page content.
     *
     * @return Collection<int, mixed>
     */
    protected function getFrontPageContent(): Collection
    {
        return Content::frontPage()->get();
    }

    /**
     * Get index content metadata.
     */
    protected function getIndexContent(): ?Content
    {
        return Content::active()
            ->ofType(Content::TYPE_INDEX)
            ->ordered()
            ->first();
    }
}
