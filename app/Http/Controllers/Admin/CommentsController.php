<?php

namespace App\Http\Controllers\Admin;

use App\Models\ReleaseComment;
use App\Http\Controllers\BasePageController;

class CommentsController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $title = 'Comments List';

        $commentsList = ReleaseComment::getCommentsRange();
        $this->smarty->assign('commentslist', $commentsList);

        $content = $this->smarty->fetch('comments-list.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'meta_title' => $title,
                'content' => $content,
            ]
        );
        $this->adminrender();
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        if ($id) {
            ReleaseComment::deleteComment($id);
        }

        return redirect()->back();
    }
}
