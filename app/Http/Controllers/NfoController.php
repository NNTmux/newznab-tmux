<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseNfo;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class NfoController extends BasePageController
{
    /**
     * @param \Illuminate\Http\Request $request
     */
    public function showNfo(Request $request)
    {
        if ($request->has('id')) {
            $rel = Release::getByGuid($request->input('id'));

            if (! $rel) {
                $this->show404();
            }

            $nfo = ReleaseNfo::getReleaseNfo($rel['id']);
            $nfo['nfoUTF'] = Utility::cp437toUTF($nfo['nfo']);

            $this->smarty->assign('rel', $rel);
            $this->smarty->assign('nfo', $nfo);

            $title = 'NFO File';
            $meta_title = 'View Nfo';
            $meta_keywords = 'view,nzb,nfo,description,details';
            $meta_description = 'View Nfo File';

            $modal = false;
            if ($request->has('modal')) {
                $modal = true;
                $this->smarty->assign('modal', true);
            }

            $content = $this->smarty->fetch('viewnfo.tpl');

            if ($modal) {
                echo $content;
            } else {
                $this->smarty->assign(
                    [
                        'title' => $title,
                        'content' => $content,
                        'meta_title' => $meta_title,
                        'meta_keywords' => $meta_keywords,
                        'meta_description' => $meta_description,
                    ]
                );
                $this->pagerender();
            }
        }
    }
}
