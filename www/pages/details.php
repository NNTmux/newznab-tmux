<?php

if (!$page->users->isLoggedIn())
	$page->show403();

if (isset($_GET["id"]))
{
	$releases = new Releases(['Settings' => $page->settings]);
	$rc = new ReleaseComments;
	$re = new ReleaseExtra;
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

	$rage = '';
	if ($data["rageid"] != '')
	{
		$tvrage = new TvAnger();

		$rageinfo = $tvrage->getByRageID($data["rageid"]);
		if (count($rageinfo) > 0)
		{
			$seriesnames = $seriesdescription = $seriescountry = $seriesgenre = $seriesimg = $seriesid = array();
			foreach($rageinfo as $r)
			{
				$seriesnames[] = $r['releasetitle'];
				if (!empty($r['description']))
					$seriesdescription[] = $r['description'];

				if (!empty($r['country']))
					$seriescountry[] = $r['country'];

				if (!empty($r['genre']))
					$seriesgenre[] = $r['genre'];

				if (!empty($r['imgdata'])) {
					$seriesimg[] = $r['imgdata'];
					$seriesid[] = $r['id'];
				}
			}
			$rage = array(
				'releasetitle' => array_shift($seriesnames),
				'description'  => array_shift($seriesdescription),
				'country'      => array_shift($seriescountry),
				'genre'        => array_shift($seriesgenre),
				'imgdata'      => array_shift($seriesimg),
				'id'=>array_shift($seriesid)
			);
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
		$movie = new Film();
		$mov = $movie->getMovieInfo($data['imdbid']);

		$trakt = new TraktTv();
		$traktSummary = $trakt->movieSummary('tt' . $data['imdbid'], 'full');
		if ($traktSummary !== false &&
			isset($traktSummary['trailer']) &&
			$traktSummary['trailer'] !== '' &&
			preg_match('/[\/?]v[\/\=](\w+)$/i', $traktSummary['trailer'], $youtubeM)
		) {
			$mov['trailer'] =
				'<embed width="480" height="345" src="' .
				'https://www.youtube.com/v/' . $youtubeM[1] .
				'" type="application/x-shockwave-flash"></embed>';
		} else {
			$mov['trailer'] = \newznab\utility\Utility::imdb_trailers($data['imdbid']);
		}

		if ($mov && isset($mov['title'])) {
			$mov['title'] = str_replace(array('/', '\\'), '', $mov['title']);
			$mov['actors'] = $movie->makeFieldLinks($mov, 'actors');
			$mov['genre'] = $movie->makeFieldLinks($mov, 'genre');
			$mov['director'] = $movie->makeFieldLinks($mov, 'director');
		} else if ($traktSummary !== false) {
			$mov['title'] = str_replace(array('/', '\\'), '', $traktSummary['title']);
		} else {
			$mov = false;
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
		$music = new Musik(['Settings' => $page->settings]);
		$mus = $music->getMusicInfo($data['musicinfoid']);
	}

	$book = '';
	if ($data['bookinfoid'] != '') {
		$b = new Book();
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
	$page->smarty->assign('privateprofiles', ($page->settings->getSetting('privateprofiles') == 1) ? true : false );

	$page->meta_title = "View NZB";
	$page->meta_keywords = "view,nzb,description,details";
	$page->meta_description = "View NZB for".$data["searchname"] ;

	$page->content = $page->smarty->fetch('viewnzb.tpl');
	$page->render();
}