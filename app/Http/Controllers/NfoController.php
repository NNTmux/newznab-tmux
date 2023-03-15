<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\ReleaseNfo;
use Blacklight\utility\Utility;
use Illuminate\Http\Request;

class NfoController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function showNfo(Request $request, string $id = ''): void
    {
        $this->setPreferences();

        if ($id) {
            $rel = Release::getByGuid($id);

            if (! $rel) {
                abort(404, 'Release does not exist');
            }

            $nfo = ReleaseNfo::getReleaseNfo($rel['id']);

            if ($nfo !== null) {
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
                    $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description'));
                    $this->pagerender();
                }
            } else {
                abort(404, 'NFO does not exist');
            }
        }
    }
}
