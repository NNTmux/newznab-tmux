<?php
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/releasefiles.php");
require_once(WWW_DIR."/lib/releasecomments.php");
require_once(WWW_DIR."/lib/releaseextra.php");
require_once(WWW_DIR."/lib/tvrage.php");
require_once(WWW_DIR."/lib/anidb.php");
require_once(WWW_DIR."/lib/predb.php");
require_once(WWW_DIR."/lib/episode.php");
require_once(WWW_DIR . "/lib/XXX.php");
require_once(WWW_DIR . "/lib/Games.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/Film.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/TraktTv.php");


if (!$users->isLoggedIn())
	$page->show403();

if (isset($_GET["id"]))
{
	$releases = new Releases;
	$rc = new ReleaseComments;
	$re = new ReleaseExtra;
	$data = $releases->getByGuid($_GET["id"]);

	if (!$data)
		$page->show404();

	if ($page->isPostBack())
		$rc->addComment($data["ID"], $data["gid"], $_POST["txtAddComment"], $users->currentUserId(), $_SERVER['REMOTE_ADDR']);

	$nfo = $releases->getReleaseNfo($data["ID"], false);
	$reVideo = $re->getVideo($data["ID"]);
	$reAudio = $re->getAudio($data["ID"]);
	$reSubs = $re->getSubs($data["ID"]);
	$comments = $rc->getCommentsByGid($data["gid"]);

	$rage = '';
	if ($data["rageID"] != '')
	{
		$tvrage = new TvRage();

		$rageinfo = $tvrage->getByRageID($data["rageID"]);
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
					$seriesid[] = $r['ID'];
				}
			}
			$rage = array(
				'releasetitle' => array_shift($seriesnames),
				'description'  => array_shift($seriesdescription),
				'country'      => array_shift($seriescountry),
				'genre'        => array_shift($seriesgenre),
				'imgdata'      => array_shift($seriesimg),
				'ID'=>array_shift($seriesid)
			);
		}
	}

	$episodeArray = '';
	if ($data['episodeinfoID'] > 0)
	{
		$episode = new Episode();
		$episodeArray = $episode->getEpisodeInfoByID($data['episodeinfoID']);
	}

	$mov = '';
	if ($data['imdbID'] != '' && $data['imdbID'] != 0000000) {
		$movie = new Film();
		$mov = $movie->getMovieInfo($data['imdbID']);

		$trakt = new TraktTv();
		$traktSummary = $trakt->traktMoviesummary('tt' . $data['imdbID'], true);
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
			$util = new Utility();
			$mov['trailer'] = $util->imdb_trailers($data['imdbID']);
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
	if ($data['musicinfoID'] != '') {
		require_once(WWW_DIR."/lib/music.php");
		$music = new Music();
		$mus = $music->getMusicInfo($data['musicinfoID']);
	}

	$book = '';
	if ($data['bookinfoID'] != '') {
		require_once(WWW_DIR."/lib/book.php");
		$b = new Book();
		$book = $b->getBookInfo($data['bookinfoID']);
	}

	$con = '';
	if ($data['consoleinfoID'] != '') {
		require_once(WWW_DIR."/lib/console.php");
		$c = new Console();
		$con = $c->getConsoleInfo($data['consoleinfoID']);
	}

	$AniDBAPIArray = '';
	if ($data["anidbID"] > 0)
	{
		$AniDB = new AniDB();
		$AniDBAPIArray = $AniDB->getAnimeInfo($data["anidbID"]);
	}

	$predbQuery = '';
	if ($data["preID"] > 0)
	{
		$PreDB = new PreDB();
		$predbQuery = $PreDB->getByID($data["preID"]);
	}

	$prehash = new PreHash();
	$pre = $prehash->getForRelease($data["prehashID"]);

	$rf = new ReleaseFiles;
	$releasefiles = $rf->get($data["ID"]);

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

	$page->meta_title = "View NZB";
	$page->meta_keywords = "view,nzb,description,details";
	$page->meta_description = "View NZB for".$data["searchname"] ;

	$page->content = $page->smarty->fetch('viewnzb.tpl');
	$page->render();
}