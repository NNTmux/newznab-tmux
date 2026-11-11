<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\ReleaseComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCommentsController extends BasePageController
{
    /**
     * Display a listing of comments
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Comments List';

        $lastSearch = trim($this->scalarInput($request, 'search'));
        $commentsList = ReleaseComment::getCommentsRange($lastSearch)->withQueryString();

        return view('admin.comments.index', compact('commentsList', 'title', 'meta_title', 'lastSearch'));
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
