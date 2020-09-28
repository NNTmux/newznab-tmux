<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\ReleaseComment;

class AdminCommentsController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Comments List';

        $commentsList = ReleaseComment::getCommentsRange();
        $this->smarty->assign('commentslist', $commentsList);

        $content = $this->smarty->fetch('comments-list.tpl');
        $this->smarty->assign(compact('title', 'meta_title', 'content'));
        $this->adminrender();
    }

    /**
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        if ($id) {
            ReleaseComment::deleteComment($id);
        }

        return redirect()->back();
    }
}
