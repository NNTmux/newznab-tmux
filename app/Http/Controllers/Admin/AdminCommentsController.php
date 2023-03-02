<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\ReleaseComment;

class AdminCommentsController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Comments List';

        $commentsList = ReleaseComment::getCommentsRange();
        $this->smarty->assign('commentslist', $commentsList);

        $content = $this->smarty->fetch('comments-list.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        if ($id) {
            ReleaseComment::deleteComment($id);
        }

        return redirect()->back();
    }
}
