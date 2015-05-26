<?php

if (!$users->isLoggedIn())
	$page->show403();

if (isset($_REQUEST["add"]))
{
	if (isset($_GET['add']) && !empty($_GET['add']))
		$ids = array($_GET['add']);
	elseif (isset($_POST['add']) && is_array($_POST['add']))
		$ids = $_POST['add'];

	$releases = new Releases;
	$data = $releases->getByGuid($ids);

	if (!$data)
		$page->show404();

	foreach($data as $d)
		$users->addCart($users->currentUserId(), $d["id"]);
}
elseif (isset($_REQUEST["delete"]))
{
	if (isset($_GET['delete']) && !empty($_GET['delete']))
		$ids = array($_GET['delete']);
	elseif (isset($_POST['delete']) && is_array($_POST['delete']))
		$ids = $_POST['delete'];

	if (isset($ids))
		$users->delCartByGuid($ids, $users->currentUserId());

	if (!isset($_POST['delete']))
		header("Location: ".WWW_TOP."/cart");

	die();
}
else
{
	$page->meta_title = "My Nzb Cart";
	$page->meta_keywords = "search,add,to,cart,nzb,description,details";
	$page->meta_description = "Manage Your Nzb Cart";

	$results = $users->getCart($users->currentUserId());
	$page->smarty->assign('results', $results);

	$page->content = $page->smarty->fetch('cart.tpl');
	$page->render();
}


