<?php

namespace App\Http\Controllers\Admin;

use App\Models\Predb;
use Illuminate\Http\Request;
use App\Http\Controllers\BasePageController;

class PredbController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->setAdminPrefs();

        if ($request->has('presearch')) {
            $lastSearch = $request->input('presearch');
            $parr = Predb::getAll(array_wrap($request->input('presearch')));
        } else {
            $lastSearch = '';
            $parr = Predb::getAll();
        }

        $this->smarty->assign('lastSearch', $lastSearch);

        $this->smarty->assign('results', $parr);

        $title = 'Browse PreDb';
        $meta_title = 'View PreDb info';
        $meta_keywords = 'view,predb,info,description,details';
        $meta_description = 'View PreDb info';

        $content = $this->smarty->fetch('predb.tpl');
        $this->smarty->assign(
            [
                'title' => $title,
                'content' => $content,
                'meta_title' => $meta_title,
                'meta_keywords' => $meta_keywords,
                'meta_description' => $meta_description,
            ]
        );

        $this->adminrender();
    }
}
