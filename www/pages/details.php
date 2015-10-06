<?php

if (!$page->users->isLoggedIn())
	$page->show403();

if (isset($_GET["id"]))
{
	$releases = new Releases(['Settings' => $page->settings]);
	$rc = new ReleaseComments;
	$re = new ReleaseExtra;
	$df = new DnzbFailures(['Settings' => $page->settings]);
	$data = $releases->getByGuid($_GET["id"]);

	if (!$data)
		$page->show404();

	if ($page->isPostBack())
		$rc->addComment($data["id"], $data["gid"], $_POST["txtAddComment"], $page->users->currentUserId(), $_SERVER['REMOTE_ADDR']);

	$nfo = $releases->getReleaseNfo($data["id"], false);
	$reVideo = $re->getVideo($data["id"]);
	$reAudio = $re->getAudio($data["id"]);
	$reSubs = $re->getSubs($data["id"]);
	$comments = $rc->getCommentsByGid($data["gid"]);
	$similars = $releases->searchSimilar($data['id'],
		$data['searchname'],
		6,
		$page->userdata['categoryexclusions']);
	$failed = $df->getFailedCount($data['guid']);

	$rage = '';
	if ($data["rageid"] != '')
	{
		$tvrage = new TvRage();

		$rageinfo = $tvrage->getByRageID($data["rageid"]);
		if (count($rageinfo) > 0)
		{
			$seriesnames = $seriesdescription = $seriescountry = $seriesgenre = $seriesid = $hascover = [];
			foreach($rageinfo as $r)
			{
				$seriesnames[] = $r['releasetitle'];
				if (!empty($r['description']))
					$seriesdescription[] = $r['description'];

				if (!empty($r['country']))
					$seriescountry[] = $r['country'];

				if (!empty($r['genre']))
					$seriesgenre[] = $r['genre'];

				if (!empty($r['id'])) {
					$seriesid[] = $r['id'];
					$hascover[] = $r['hascover'];
				}
			}
			$rage = [
				'releasetitle' => array_shift($seriesnames),
				'description'  => array_shift($seriesdescription),
				'country'      => array_shift($seriescountry),
				'genre'        => array_shift($seriesgenre),
				'hascover'     => array_shift($hascover),
				'id'           => array_shift($seriesid)
			];
		}
	}

	$episodeArray = '';
	if ($data['episodeinfoid'] > 0)
	{
		$episode = new Episode();
		$episodeArray = $episode->getEpisodeInfoByID($data['episodeinfoid']);
	}

	$mov = '';
	if ($data['imdbid'] != '' && $data['imdbid'] != 0000000) {
		$movie = new Movie(['Settings' => $page->settings]);
		$mov   = $movie->getMovieInfo($data['imdbid']);
		if ($mov && isset($mov['title'])) {
			$mov['title']    = str_replace(['/', '\\'], '', $mov['title']);
			$mov['actors']   = $movie->makeFieldLinks($mov, 'actors');
			$mov['genre']    = $movie->makeFieldLinks($mov, 'genre');
			$mov['director'] = $movie->makeFieldLinks($mov, 'director');
			if ($page->settings->getSetting('trailers_display')) {
				$trailer = (!isset($mov['trailer']) || empty($mov['trailer']) || $mov['trailer'] == '' ? $movie->getTrailer($data['imdbid']) : $mov['trailer']);
				if ($trailer) {
					$mov['trailer'] = sprintf(
						"<iframe width=\"%d\" height=\"%d\" src=\"%s\"></iframe>",
						$page->settings->getSetting('trailers_size_x'),
						$page->settings->getSetting('trailers_size_y'),
						$trailer
					);
				}
			}
		}
	}

	$xxx = '';
	if ($data['xxxinfo_id'] != '' && $data['xxxinfo_id'] != 0) {
		$x = new XXX();
		$xxx = $x->getXXXInfo($data['xxxinfo_id']);

		if (isset($xxx['trailers'])) {
			$xxx['trailers'] = $x->insertSwf($xxx['classused'], $xxx['trailers']);
		}

		if ($xxx && isset($xxx['title'])) {
			$xxx['title'] = str_replace(array('/', '\\'), '', $xxx['title']);
			$xxx['actors'] = $x->makeFieldLinks($xxx, 'actors');
			$xxx['genre'] = $x->makeFieldLinks($xxx, 'genre');
			$xxx['director'] = $x->makeFieldLinks($xxx, 'director');
		} else {
			$xxx = false;
		}
	}

	$game = '';
	if ($data['gamesinfo_id'] != '') {
		$g = new Games();
		$game = $g->getGamesInfo($data['gamesinfo_id']);
	}

	$mus = '';
	if ($data['musicinfoid'] != '') {
		$music = new Music(['Settings' => $page->settings]);
		$mus = $music->getMusicInfo($data['musicinfoid']);
	}

	$book = '';
	if ($data['bookinfoid'] != '') {
		$b = new Books();
		$book = $b->getBookInfo($data['bookinfoid']);
	}

	$con = '';
	if ($data['consoleinfoid'] != '') {
		$c = new Console();
		$con = $c->getConsoleInfo($data['consoleinfoid']);
	}

	$AniDBAPIArray = '';
	if ($data["anidbid"] > 0)
	{
		$AniDB = new AniDB(['Settings' => $releases->pdo]);
		$AniDBAPIArray = $AniDB->getAnimeInfo($data["anidbid"]);
	}

	$predbQuery = '';
	if ($data["preid"] > 0)
	{
		$PreDB = new PreDB();
		$predbQuery = $PreDB->getByID($data["preid"]);
	}

	$prehash = new PreHash();
	$pre = $prehash->getForRelease($data["prehashid"]);

	$rf = new ReleaseFiles;
	$releasefiles = $rf->get($data["id"]);

	$page->smarty->assign('releasefiles',$releasefiles);
	$page->smarty->assign('release',$data);
	$page->smarty->assign('reVideo',$reVideo);
	$page->smarty->assign('reAudio',$reAudio);
	$page->smarty->assign('reSubs',$reSubs);
	$page->smarty->assign('nfo',$nfo);
	$page->smarty->assign('rage',$rage);
	$page->smarty->assign('movie',$mov);
	$page->smarty->assign('xxx', $xxx);
	$page->smarty->assign('episode',$episodeArray);
	$page->smarty->assign('anidb',$AniDBAPIArray);
	$page->smarty->assign('music',$mus);
	$page->smarty->assign('con',$con);
	$page->smarty->assign('game', $game);
	$page->smarty->assign('book',$book);
	$page->smarty->assign('predb',$predbQuery);
	$page->smarty->assign('prehash', $pre);
	$page->smarty->assign('comments',$comments);
	$page->smarty->assign('searchname',$releases->getSimilarName($data['searchname']));
	$page->smarty->assign('similars', $similars);
	$page->smarty->assign('privateprofiles', ($page->settings->getSetting('privateprofiles') == 1) ? true : false );
	$page->smarty->assign('failed', $failed);

	$page->meta_title = "View NZB";
	$page->meta_keywords = "view,nzb,description,details";
	$page->meta_description = "View NZB for".$data["searchname"] ;

	$page->content = $page->smarty->fetch('viewnzb.tpl');
	$page->render();
}