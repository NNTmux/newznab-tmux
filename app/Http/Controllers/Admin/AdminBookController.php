<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\BookInfo;
use App\Services\BookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminBookController extends BasePageController
{
    /**
     * Display a listing of books
     */
    public function index(): View
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Book List';

        $bookList = BookInfo::query()->orderByDesc('created_at')->paginate(config('nntmux.items_per_page'));

        return view('admin.books.index', compact('bookList', 'title', 'meta_title'));
    }

    /**
     * Show the form for editing a book
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $this->setAdminPrefs();
        $bookService = new BookService;

        $meta_title = $title = 'Book Edit';

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $b = $bookService->getBookInfo($id);

            if (! $b) {
                abort(404);
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/book/'.$id.'.jpg');

                    if ($request->hasFile('cover') && $request->file('cover')->isValid()) {
                        $uploadedFile = $request->file('cover');
                        $file_info = getimagesize($uploadedFile->getRealPath());
                        if (! empty($file_info)) {
                            $uploadedFile->move(storage_path('covers/book'), $id.'.jpg');
                        }
                    }

                    $hasCover = file_exists($coverLoc) ? 1 : 0;
                    $publishdate = (empty($request->input('publishdate')) || ! strtotime($request->input('publishdate')))
                        ? ($b['publishdate'] ?? null)
                        : Carbon::parse($request->input('publishdate'))->timestamp;

                    $bookService->update(
                        $id,
                        $request->input('title'),
                        $request->input('asin'),
                        $request->input('url'),
                        $request->input('author'),
                        $request->input('publisher'),
                        $publishdate,
                        $hasCover
                    );

                    return redirect()->route('admin.book-list')->with('success', 'Book updated successfully');
                case 'view':
                default:
                    return view('admin.books.edit', compact('title', 'meta_title'))->with('book', $b);
            }
        }

        return redirect()->route('admin.book-list');
    }
}
