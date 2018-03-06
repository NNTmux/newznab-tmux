<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

use Blacklight\db\DB;
use Blacklight\http\AdminPage;

$page = new AdminPage();

$page->title = 'Sharing Settings';

$db = new DB();

$offset = request()->input('offset') ?? 0;

$allSites = $db->query(sprintf('SELECT * FROM sharing_sites ORDER BY id LIMIT %d OFFSET %d', 25, $offset));
if (count($allSites) === 0) {
    $allSites = false;
}

$ourSite = $db->queryOneRow('SELECT * FROM sharing');

if (! empty(request()->all())) {
    if (! empty(request()->input('sharing_name')) && ! preg_match('/\s+/', request()->input('sharing_name')) && strlen(request()->input('sharing_name')) < 255) {
        $site_name = trim(request()->input('sharing_name'));
    } else {
        $site_name = $ourSite['site_name'];
    }
    if (! empty(request()->input('sharing_maxpush')) && is_numeric(request()->input('sharing_maxpush'))) {
        $max_push = trim(request()->input('sharing_maxpush'));
    } else {
        $max_push = $ourSite['max_push'];
    }
    if (! empty(request()->input('sharing_maxpoll')) && is_numeric(request()->input('sharing_maxpush'))) {
        $max_pull = trim(request()->input('sharing_maxpoll'));
    } else {
        $max_pull = $ourSite['max_pull'];
    }
    if (! empty(request()->input('sharing_maxdownload')) && is_numeric(request()->input('sharing_maxdownload'))) {
        $max_download = trim(request()->input('sharing_maxdownload'));
    } else {
        $max_download = $ourSite['max_download'];
    }
    $db->queryExec(
        sprintf(
            '
			UPDATE sharing
			SET site_name = %s, max_push = %d, max_pull = %d, max_download = %d',
            $db->escapeString($site_name),
            $max_push,
            $max_pull,
            $max_download
        )
    );
    $ourSite = $db->queryOneRow('SELECT * FROM sharing');
}

$total = $db->queryOneRow('SELECT COUNT(id) AS total FROM sharing_sites');

$page->smarty->assign('pagertotalitems', ($total === false ? 0 : $total['total']));
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', 25);
$page->smarty->assign('pagerquerybase', WWW_TOP.'/sharing.php?offset=');

$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$page->smarty->assign(['local' => $ourSite, 'sites' => $allSites]);

$page->content = $page->smarty->fetch('sharing.tpl');
$page->render();
