<?php

$b = new Book;
$cat = new Category(['Settings' => $page->settings]);

if (!$page->users->isLoggedIn())
	$page->show403();

$boocats = $cat->getChildren(Category::CAT_PARENT_BOOK);
$btmp = [];
foreach ($boocats as $bcat) {
	$btmp[$bcat['id']] = $bcat;
}
$category = Category::CAT_PARENT_BOOK;
if (isset($_REQUEST["t"]) && array_key_exists($_REQUEST['t'], $btmp)) {
	$category = $_REQUEST["t"] + 0;
}

$catarray = [];
$catarray[] = $category;

$page->smarty->assign('catlist', $btmp);
$page->smarty->assign('category', $category);

$browsecount = $b->getBookCount();

$offset = (isset($_REQUEST["offset"]) && ctype_digit($_REQUEST['offset'])) ? $_REQUEST["offset"] : 0;
$ordering = $b->getBookOrdering();
$orderby = isset($_REQUEST["ob"]) && in_array($_REQUEST['ob'], $ordering) ? $_REQUEST["ob"] : '';

$results = $b->getBookRange($offset, ITEMS_PER_COVER_PAGE, $orderby);

$title = (isset($_REQUEST['title']) && !empty($_REQUEST['title'])) ? stripslashes($_REQUEST['title']) : '';
$page->smarty->assign('title', $title);

$author = (isset($_REQUEST['author']) && !empty($_REQUEST['author'])) ? stripslashes($_REQUEST['author']) : '';
$page->smarty->assign('author', $author);

$browseby_link = '?title='.$title.'&amp;author='.$author;

$page->smarty->assign('pagertotalitems',$browsecount);
$page->smarty->assign('pageroffset',$offset);
$page->smarty->assign('pageritemsperpage',ITEMS_PER_COVER_PAGE);
$page->smarty->assign('pagerquerybase', WWW_TOP."/books".$browseby_link."&amp;ob=".$orderby."&amp;offset=");
$page->smarty->assign('pagerquerysuffix', "#results");

$pager = $page->smarty->fetch("pager.tpl");
$page->smarty->assign('pager', $pager);

if ($category == -1)
	$page->smarty->assign("catname","All");
else
{
	$cat = new Category();
	$cdata = $cat->getById($category);
	if ($cdata)
		$page->smarty->assign('catname',$cdata["title"]);
	else
		$page->show404();
}

foreach($ordering as $ordertype)
	$page->smarty->assign('orderby'.$ordertype, WWW_TOP."/books".$browseby_link."&amp;ob=".$ordertype."&amp;offset=0");

$page->smarty->assign('results',$results);

$page->meta_title = "Browse Books";
$page->meta_keywords = "browse,nzb,ebooks,books,e-books";
$page->meta_description = "Browse for Books";

$page->content = $page->smarty->fetch('books.tpl');
$page->render();