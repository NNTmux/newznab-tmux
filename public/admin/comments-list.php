<?php

use App\Models\ReleaseComment;

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'smarty.php';

$page = new AdminPage();

$page->title = 'Comments List';

$commentcount = ReleaseComment::getCommentCount();
$offset = request()->input('offset') ?? 0;
$page->smarty->assign([
        'pagertotalitems' => $commentcount,
        'pageroffset' => $offset,
        'pageritemsperpage' => env('ITEMS_PER_PAGE', 50),
        'pagerquerybase' => WWW_TOP.'/comments-list.php?offset=',
        'pagerquerysuffix' => '', ]);
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$commentslist = ReleaseComment::getCommentsRange($offset, env('ITEMS_PER_PAGE', 50));
$page->smarty->assign('commentslist', $commentslist);

$page->content = $page->smarty->fetch('comments-list.tpl');
$page->render();
