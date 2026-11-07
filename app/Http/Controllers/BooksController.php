<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\BookService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BooksController extends BasePageController
{
    protected BookService $bookService;

    public function __construct(BookService $bookService)
    {
        parent::__construct();
        $this->bookService = $bookService;
    }

    /**
     * @throws \Exception
     */
    public function index(Request $request, string $id = ''): mixed
    {
        $boocats = Category::getChildren(Category::BOOKS_ROOT);

        $btmp = [];
        foreach ($boocats as $bcat) {
            $btmp[] =
                [
                    'id' => $bcat->id,
                    'title' => $bcat->title,
                ];
        }
        $category = $request->has('t') ? $this->scalarInput($request, 't', (string) Category::BOOKS_ROOT) : Category::BOOKS_ROOT;
        if ($id && \in_array($id, Arr::pluck($btmp, 'title'), false)) {
            $cat = Category::query()
                ->where('title', $id)
                ->where('root_categories_id', '=', Category::BOOKS_ROOT)
                ->first(['id']);
            $category = $cat !== null ? (int) $cat['id'] : Category::BOOKS_ROOT;
        }

        $catarray = [];
        $catarray[] = (int) $category;

        $ordering = $this->bookService->getBookOrdering();
        $orderby = $this->resolveOrderBy($request, $ordering);

        $books = [];
        $page = $this->resolvePage($request);
        $perPage = (int) config('nntmux.items_per_cover_page');
        $offset = $this->paginationOffset($page, $perPage);
        $rslt = $this->bookService->getBookRange($page, $catarray, $offset, $perPage, $orderby, (array) $this->userdata->categoryexclusions);
        $results = $this->paginate($rslt, $rslt[0]->_totalcount ?? 0, $perPage, $page, $request->url(), $request->query());
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

        $authorInput = $this->scalarInput($request, 'author');
        $author = $authorInput !== '' ? stripslashes($authorInput) : '';

        $titleInput = $this->scalarInput($request, 'title');
        $title = $titleInput !== '' ? stripslashes($titleInput) : '';

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
            'ordering' => $ordering,
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
