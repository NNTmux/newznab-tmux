<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Blacklight\NZB;

class FileListController extends BasePageController
{
    /**
     * @param $guid
     *
     * @throws \Exception
     */
    public function show($guid)
    {
        $this->setPrefs();
        $nzb = new NZB();

        if ($guid !== null) {
            $rel = Release::getByGuid($guid);
            if (! $rel) {
                $this->show404();
            }

            $nzbpath = $nzb->NZBPath($guid);

            if (! file_exists($nzbpath)) {
                $this->show404();
            }

            ob_start();
            @readgzfile($nzbpath);
            $nzbfile = ob_get_contents();
            ob_end_clean();

            $ret = $nzb->nzbFileList($nzbfile);

            $this->smarty->assign('rel', $rel);
            $this->smarty->assign('files', $ret);

            $title = 'File List';
            $meta_title = 'View Nzb file list';
            $meta_keywords = 'view,nzb,file,list,description,details';
            $meta_description = 'View Nzb File List';

            $content = $this->smarty->fetch('viewfilelist.tpl');

            $this->smarty->assign(
                compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description')
                );
            $this->pagerender();
        }
    }
}
