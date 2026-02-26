<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BasePageController;
use App\Models\Predb;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPredbController extends BasePageController
{
    /**
     * Display a listing of PreDB entries
     */
    public function index(Request $request): View
    {
        $this->setAdminPrefs();

        $lastSearch = $request->input('presearch', '');

        if ($lastSearch) {
            $results = Predb::getAll($lastSearch);
        } else {
            $results = Predb::getAll();
        }

        $title = 'Browse PreDb';
        $meta_title = 'View PreDb info';
        $meta_keywords = 'view,predb,info,description,details';
        $meta_description = 'View PreDb info';

        return view('admin.predb.index', compact(
            'results',
            'lastSearch',
            'title',
            'meta_title',
            'meta_keywords',
            'meta_description'
        ));
    }
}
