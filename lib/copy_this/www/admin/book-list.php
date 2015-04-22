<?php

require_once './config.php';

$page = new AdminPage();

$book = new Book();

$page->title = "Book List";

$concount = $book->getCount();

$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
$page->smarty->assign('pagertotalitems',$concount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/book-list.php?offset=");
$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

$booklist = $book->getRange($offset, ITEMS_PER_PAGE);

$page->smarty->assign('booklist',$booklist);

$page->content = $page->smarty->fetch('book-list.tpl');
$page->render();
