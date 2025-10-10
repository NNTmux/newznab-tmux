<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\ReleaseComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminCommentsController extends BasePageController
{
    /**
     * Display a listing of comments
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Comments List';

        $commentsList = ReleaseComment::getCommentsRange();

        return view('admin.comments.index', compact('commentsList', 'title', 'meta_title'));
    }

    /**
     * Delete a comment
     */
    public function destroy(int $id): RedirectResponse
    {
        if ($id) {
            ReleaseComment::deleteComment($id);
        }

        return redirect()->back()->with('success', 'Comment deleted successfully');
    }
}
