<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use App\Models\User;
use nntmux\NZB;
use nntmux\db\DB;
use nntmux\Releases;

$page = new AdminPage;
$releases = new Releases;
$pdo = new DB();
$nzb = new NZB($pdo);

if (! User::isLoggedIn()) {
    $page->show403();
}

if (isset($_GET['id'])) {
    $rel = $releases->getByGuid($_GET['id']);
    if (! $rel) {
        $page->show404();
    }

    $nzbpath = $nzb->getNZBPath($_GET['id'], $page->getSettingValue('..nzbpath'));

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
