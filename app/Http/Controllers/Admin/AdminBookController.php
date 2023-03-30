<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\BookInfo;
use Blacklight\Books;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminBookController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(): void
    {
        $this->setAdminPrefs();

        $meta_title = $title = 'Book List';

        $bookList = BookInfo::query()->orderByDesc('created_at')->paginate(config('nntmux.items_per_page'));

        $this->smarty->assign('booklist', $bookList);

        $content = $this->smarty->fetch('book-list.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }

    /**
     * @throws \Exception
     */
    public function edit(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $this->setAdminPrefs();
        $book = new Books();

        $meta_title = $title = 'Book Edit';

        // set the current action
        $action = $request->input('action') ?? 'view';

        if ($request->has('id')) {
            $id = $request->input('id');
            $b = $book->getBookInfo($id);

            if (! $b) {
                $this->show404();
            }

            switch ($action) {
                case 'submit':
                    $coverLoc = storage_path('covers/book/'.$id.'.jpg');

                    if ($_FILES['cover']['size'] > 0) {
                        $tmpName = $_FILES['cover']['tmp_name'];
                        $file_info = getimagesize($tmpName);
                        if (! empty($file_info)) {
                            move_uploaded_file($_FILES['cover']['tmp_name'], $coverLoc);
                        }
                    }

                    $request->merge(['cover' => file_exists($coverLoc) ? 1 : 0]);
                    $request->merge(['publishdate' => (empty($request->input('publishdate')) || ! strtotime($request->input('publishdate'))) ? $con['publishdate'] : Carbon::parse($request->input('publishdate'))->timestamp]);
                    $book->update($id, $request->input('title'), $request->input('asin'), $request->input('url'), $request->input('author'), $request->input('publisher'), $request->input('publishdate'), $request->input('cover'));

                    return redirect()->to('admin/book-list');
                    break;
                case 'view':
                default:
                    $this->smarty->assign('book', $b);
                    break;
            }
        }

        $content = $this->smarty->fetch('book-edit.tpl');

        $this->smarty->assign(compact('title', 'meta_title', 'content'));

        $this->adminrender();
    }
}
