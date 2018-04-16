<?php

namespace App\Http\Controllers;

use Blacklight\Books;
use App\Models\Category;
use Illuminate\Http\Request;

class BooksController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setPrefs();
        $book = new Books(['Settings' => $this->settings]);

        $boocats = Category::getChildren(Category::BOOKS_ROOT);

        $btmp = [];
        foreach ($boocats as $bcat) {
            $btmp[$bcat['id']] = $bcat;
        }
        $category = Category::BOOKS_ROOT;

        $catarray = [];
        $catarray[] = $category;

        $this->smarty->assign('catlist', $btmp);
        $this->smarty->assign('category', $category);

        $offset = ($request->has('offset') && ctype_digit($request->input('offset'))) ? $request->input('offset') : 0;
        $ordering = $book->getBookOrdering();
        $orderby = $request->has('ob') && \in_array($request->input('ob'), $ordering, false) ? $request->input('ob') : '';

        $books = [];
        $page = $request->has('page') ? $request->input('page') : 1;
        $results = $book->getBookRange($page, $catarray, $orderby, $this->userdata['categoryexclusions']);

        $maxwords = 50;
        foreach ($results as $result) {
            if (! empty($result['overview'])) {
                $words = explode(' ', $result['overview']);
                if (\count($words) > $maxwords) {
                    $newwords = \array_slice($words, 0, $maxwords);
                    $result['overview'] = implode(' ', $newwords).'...';
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
                $this->smarty->assign('catname', $cdata->parent !== null ? $cdata->parent->title.' > '.$cdata->title : $cdata->title);
            } else {
                $this->show404();
            }
        }

        foreach ($ordering as $ordertype) {
            $this->smarty->assign('orderby'.$ordertype, WWW_TOP.'/books?t='.$category.$browseby_link.'&amp;ob='.$ordertype.'&amp;offset=0');
        }

        $this->smarty->assign('results', $books);

        $meta_title = 'Browse Books';
        $meta_keywords = 'browse,nzb,books,description,details';
        $meta_description = 'Browse for Books';
        $content = $this->smarty->fetch('books.tpl');
        $this->smarty->assign(
            [
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        $this->pagerender();
    }
}
