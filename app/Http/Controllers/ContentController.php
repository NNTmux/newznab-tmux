<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Blacklight\Contents;
use Illuminate\Http\Request;

class ContentController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws \Exception
     */
    public function show(Request $request)
    {
        $this->setPreferences();
        $contents = new Contents();

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
        $this->smarty->assign('admin', (($role === 2 || $role === 4) ? 'true' : 'false'));

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
            $this->smarty->assign('front', false);
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } elseif ($contentId !== 0 && $contentPage !== false) {
            $content = [$contents->getByID($contentId, $role)];
            $this->smarty->assign('front', false);
            $meta_title = 'Contents page';
            $meta_keywords = 'contents';
            $meta_description = 'This is the contents page.';
        } else {
            $content = $contents->getFrontPage();
            $index = $contents->getIndex();
            $this->smarty->assign('front', true);
            $meta_title = $index->title ?? 'Contents page';
            $meta_keywords = $index->metakeyword ?? 'contents';
            $meta_description = $index->metadescription ?? 'This is the contents page.';
        }

        if (empty($content)) {
            return response()->json(['message' => 'There is nothing to see here, no content provided.'], 404);
        }

        $this->smarty->assign('content', $content);

        $content = $this->smarty->fetch('content.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }
}
