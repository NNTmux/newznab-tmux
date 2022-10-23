<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Blacklight\Books;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BooksController extends BasePageController
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     *
     * @throws \Exception
     */
    public function index(Request $request, string $id = ''): void
    {
        $this->setPrefs();
        $book = new Books(['Settings' => $this->settings]);

        $boocats = Category::getChildren(Category::BOOKS_ROOT);

        $btmp = [];
        foreach ($boocats as $bcat) {
            $btmp[] =
                [
                    'id' => $bcat->id,
                    'title' => $bcat->title,
                ];
        }
        $category = $request->has('t') ? $request->input('t') : Category::BOOKS_ROOT;
        if ($id && \in_array($id, Arr::pluck($btmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::BOOKS_ROOT)
                ->first(['id']);
            $category = $cat !== null ? $cat['id'] : Category::BOOKS_ROOT;
        }

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $btmp);
        $this->smarty->assign('category', $category);
        $this->smarty->assign('categorytitle', $id);

        $ordering = $book->getBookOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $books = [];
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $rslt = $book->getBookRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt ?? [], $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());
        $maxwords = 50;
        foreach ($results as $result) {
            if (! empty($result->overview)) {
                $words = explode(' ', $result->overview);
                if (\count($words) > $maxwords) {
                    $newwords = \array_slice($words, 0, $maxwords);
                    $result->overview = implode(' ', $newwords).'...';
                }
            }
            $books[] = $result;
        }

        $author = ($request->has('author') && ! empty($request->input('author'))) ? stripslashes($request->input('author')) : '';
        $this->smarty->assign('author', $author);

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';
        $this->smarty->assign('title', $title);

        $browseby_link = '&amp;title='.$title.'&amp;author='.$author;

        if ((int) $category === -1) {
            $this->smarty->assign('catname', 'All');
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $this->smarty->assign('catname', $cdata);
            } else {
                $this->smarty->assign('catname', 'All');
            }
        }

        foreach ($ordering as $ordertype) {
            $this->smarty->assign('orderby'.$ordertype, url('/books?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0'));
        }

        $this->smarty->assign(
            [
                'resultsadd'=>  $books,
                'results' => $results,
                'covgroup' => 'books',
            ]
        );

        $meta_title = 'Browse Books';
        $meta_keywords = 'browse,nzb,books,description,details';
        $meta_description = 'Browse for Books';
        $content = $this->smarty->fetch('books.tpl');
        $this->smarty->assign(compact('content', 'meta_title', 'meta_keywords', 'meta_description'));

        $this->pagerender();
    }
}
