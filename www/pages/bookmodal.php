<?php

use newznab\controllers\Books;

$b = new Books;

if (!$page->users->isLoggedIn())
	$page->show403();

if (isset($_GET["id"]) && ctype_digit($_GET["id"]))
{
	$book = $b->getBookInfo($_GET['id']);
	if (!$book)
		$page->show404();

	$page->smarty->assign('book', $book);

	$page->title = "Info for ".$book['title'];
	$page->meta_title = "";
	$page->meta_keywords = "";
	$page->meta_description = "";
	$page->smarty->registerPlugin('modifier', 'ss', 'stripslashes');

	$modal = false;
	if (isset($_GET['modal']))
	{
		$modal = true;
		$page->smarty->assign('modal', true);
	}

	$page->content = $page->smarty->fetch('viewbook.tpl');

	if ($modal)
		echo $page->content;
	else
		$page->render();
}

