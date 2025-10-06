<?php

namespace App\Http\Controllers;

use Blacklight\Contents;
use Illuminate\Http\Request;

class ContentController extends BasePageController
{
    /**
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     *
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $contents = new Contents;

        $role = $this->userdata->role ?? 0;

        /* The role column in the content table values are :
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
        $isAdmin = ($role === 2 || $role === 4);

        $contentId = 0;
        if ($request->has('id')) {
            $contentId = $request->input('id');
        }

        $contentPage = false;
        if ($request->has('page')) {
            $contentPage = $request->input('page');
        }

        if ($contentId === 0 && $contentPage === 'content') {
            $content = $contents->getAllButFront();
            $isFront = false;
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } elseif ($contentId !== 0 && $contentPage !== false) {
            $content = [$contents->getByID($contentId, $role)];
            $isFront = false;
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } else {
            $content = $contents->getFrontPage();
            $index = $contents->getIndex();
            $isFront = true;
            $meta_title = $index->title ?? 'Contents page';
            $meta_keywords = $index->metakeyword ?? 'contents';
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
}
