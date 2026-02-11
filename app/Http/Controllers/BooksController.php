<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BookService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BooksController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request, string $id = ''): mixed
    {
        $bookService = new BookService;

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

        $ordering = $bookService->getBookOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $books = [];
        $page = $request->has('page') && is_numeric($request->input('page')) ? $request->input('page') : 1;
        $offset = ($page - 1) * config('nntmux.items_per_cover_page');
        $rslt = $bookService->getBookRange($page, $catarray, $offset, config('nntmux.items_per_cover_page'), $orderby, (array) $this->userdata->categoryexclusions); // @phpstan-ignore argument.type
        $results = $this->paginate($rslt, $rslt[0]->_totalcount ?? 0, config('nntmux.items_per_cover_page'), $page, $request->url(), $request->query());
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

        $title = ($request->has('title') && ! empty($request->input('title'))) ? stripslashes($request->input('title')) : '';

        $browseby_link = '&title='.$title.'&author='.$author;

        if ((int) $category === -1) {
            $catname = 'All';
        } else {
            $cdata = Category::find($category);
            if ($cdata !== null) {
                $catname = $cdata->title;
            } else {
                $catname = 'All';
            }
        }

        // Build order by URLs
        $orderByUrls = [];
        foreach ($ordering as $ordertype) {
            $orderByUrls['orderby'.$ordertype] = url('/Books/'.($id ?: 'All').'?t='.$category.$browseby_link.'&ob='.$ordertype.'&offset=0');
        }

        $this->viewData = array_merge($this->viewData, [
            'catlist' => $btmp,
            'category' => $category,
            'categorytitle' => $id,
            'catname' => $catname,
            'author' => $author,
            'title' => $title,
            'resultsadd' => $books,
            'results' => $results,
            'covgroup' => 'books',
            'meta_title' => 'Browse Books',
            'meta_keywords' => 'browse,nzb,books,description,details',
            'meta_description' => 'Browse for Books',
        ], $orderByUrls);

        return view('books.index', $this->viewData);
    }
}
