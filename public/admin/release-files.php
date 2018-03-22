<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use Blacklight\NZB;
use App\Models\User;
use Blacklight\db\DB;
use App\Models\Release;
use App\Models\Settings;
use Blacklight\http\AdminPage;

$page = new AdminPage;
$pdo = new DB();
$nzb = new NZB();



if (request()->has('id')) {
    $rel = Release::getByGuid(request()->input('id'));
    if (! $rel) {
        $page->show404();
    }

    $nzbpath = $nzb->getNZBPath(request()->input('id'), Settings::settingValue('..nzbpath'));

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

    $page->content = $page->smarty->fetch('release-files.tpl');
    $page->render();
}
