<?php

if (!$page->users->isLoggedIn())
	$page->show403();

$um = new UserMovies();


if (isset($_REQUEST["del"]))
{
	$usermovies = $um->delMovie($page->users->currentUserId(), $_REQUEST["del"]);
}
elseif (isset($_REQUEST["add"]))
{
	//
	// derive cats from user preferences
	//
	$cats = array();
	$cats[] = "2030";
	$cats[] = "2040";

	$m = new Movie(false);
	$mi = $m->getMovieInfo($_REQUEST["add"]);
	if (!$mi)
		$m->updateMovieInfo($_REQUEST["add"]);

	$usermovies = $um->addMovie($page->users->currentUserId(), $_REQUEST["add"], $cats);
}
else
{
	if (!isset($_REQUEST["id"]))
		$page->show404();

	$m = new Movie(false);

	if (is_numeric($_REQUEST["id"]))
	{
		$prop = $m->fetchTmdbProperties($_REQUEST["id"]);
		if ($prop !== false)
			$res = array($prop);
	}
	else
	{
		$res = $m->searchTmdb($_REQUEST["id"]);
	}

	$imdbids = array();
	if ($res)
	{
		foreach ($res as $movie)
		{
			if (isset($movie['title']) && isset($movie['imdb_id']))
			{
				$imdbids[] = str_replace("tt", "", $movie['imdb_id']);
			}
			else
			{
				// no results
			}
		}

		if (count($imdbids) == 0)
		{
			print "<h3 style='padding-top:30px;'>No results found</h3>";
		}
		else
		{
			$ourmovieimdbs = array();
			if (count($imdbids) > 0)
			{
				$m = new Movie();
				$allmovies = $m->getMovieInfoMultiImdb($imdbids);
				foreach ($allmovies as $ourmovie)
					if ($ourmovie["relimdb"] != "")
						$ourmovieimdbs[$ourmovie["imdbid"]] = $ourmovie["imdbid"];
			}

			$userimdbs = array();
			$usermovies = $um->getMovies($page->users->currentUserId());
			foreach ($usermovies as $umovie)
				$userimdbs[$umovie["imdbid"]] = $umovie["imdbid"];

			$page->smarty->assign('data', $res);
			$page->smarty->assign('ourmovies', $ourmovieimdbs);
			$page->smarty->assign('userimdbs', $userimdbs);

			print $page->smarty->fetch('mymovielist.tpl');
		}
	}
}