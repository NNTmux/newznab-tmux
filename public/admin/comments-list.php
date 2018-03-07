<?php

use App\Models\ReleaseComment;
use Blacklight\http\AdminPage;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

$page = new AdminPage();

$page->title = 'Comments List';

$commentcount = ReleaseComment::getCommentCount();
$offset = request()->input('offset') ?? 0;
$page->smarty->assign([
        'pagertotalitems' => $commentcount,
        'pageroffset' => $offset,
        'pageritemsperpage' => config('nntmux.items_per_page'),
        'pagerquerybase' => WWW_TOP.'/comments-list.php?offset=',
        'pagerquerysuffix' => '', ]);
$pager = $page->smarty->fetch('pager.tpl');
$page->smarty->assign('pager', $pager);

$commentslist = ReleaseComment::getCommentsRange($offset, config('nntmux.items_per_page'));
$page->smarty->assign('commentslist', $commentslist);

$page->content = $page->smarty->fetch('comments-list.tpl');
$page->render();
