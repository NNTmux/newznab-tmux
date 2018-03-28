<?php

use Blacklight\NZB;
use Blacklight\db\DB;
use App\Models\Release;

$pdo = new DB();
$nzb = new NZB();

if (request()->has('id')) {
    $rel = Release::getByGuid(request()->input('id'));
    if (! $rel) {
        $page->show404();
    }

    $nzbpath = $nzb->NZBPath(request()->input('id'));

    if (! file_exists($nzbpath)) {
        $page->show404();
    }

    ob_start();
    @readgzfile($nzbpath);
    $nzbfile = ob_get_contents();
    ob_end_clean();

    $ret = $nzb->nzbFileList($nzbfile);

    $page->smarty->assign('rel', $rel);
    $page->smarty->assign('files', $ret);

    $page->title = 'File List';
    $page->meta_title = 'View Nzb file list';
    $page->meta_keywords = 'view,nzb,file,list,description,details';
    $page->meta_description = 'View Nzb File List';

    $modal = false;
    if (request()->has('modal')) {
        $modal = true;
        $page->smarty->assign('modal', true);
    }

    $page->content = $page->smarty->fetch('viewfilelist.tpl');

    if ($modal) {
        echo $page->content;
    } else {
        $page->pagerender();
    }
}
