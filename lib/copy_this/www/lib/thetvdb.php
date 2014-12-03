<?php
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/postprocess.php");
require_once(WWW_DIR."/lib/episode.php");
require_once(WWW_DIR."/lib/category.php");


class TheTVDB
{
	const PROJECT	= 'newznab';
	const APIKEY	= '5F84ECB91B42D719';

	public function TheTVDB($echooutput=true)
	{
		$this->echooutput = $echooutput;
		$this->MIRROR = 'http://www.thetvdb.com';
	}

	public function addSeries($TheTVDBAPIArray)
	{
		$db = new DB();

		$airstime = $TheTVDBAPIArray['airstime'];
		if($airstime != "")
			$airstime = $db->escapeString(date('H:i:s', strtotime($airstime)));
		else
			$airstime = "null";

		$firstaired = $TheTVDBAPIArray['firstaired'];
		if ($firstaired != "")
			$firstaired = $db->escapeString($firstaired);
		else
			$firstaired = "null";

		$rating = $TheTVDBAPIArray['rating'];
		if ($rating != "")
			$rating = $db->escapeString($rating);
		else
			$rating = "null";

		$db->queryInsert(sprintf("INSERT INTO thetvdb
		(tvdbID, actors, airsday, airstime, contentrating, firstaired, genre, imdbID, network, overview, rating, ratingcount, runtime, seriesname, status, createddate)
		VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %F, %d, %d, %s, %s, now())",
				$TheTVDBAPIArray['tvdbID'], $db->escapeString($TheTVDBAPIArray['actors']), $db->escapeString($TheTVDBAPIArray['airsday']),
				$airstime, $db->escapeString($TheTVDBAPIArray['contentrating']), $firstaired,
				$db->escapeString($TheTVDBAPIArray['genre']), $TheTVDBAPIArray['imdbID'], $db->escapeString($TheTVDBAPIArray['network']), $db->escapeString($TheTVDBAPIArray['overview']),
				$rating, $TheTVDBAPIArray['ratingcount'], $TheTVDBAPIArray['runtime'], $db->escapeString($TheTVDBAPIArray['seriesname']),
				$db->escapeString($TheTVDBAPIArray['status'])));
	}

	public function addEpisodes($TheTVDBAPIArray)
	{
		$db = new DB();

		for($i=0; $i < count($TheTVDBAPIArray['episodetvdbID']); $i++) {
			$airdate = strftime('%Y-%m-%d %H:%M:%S', strtotime($TheTVDBAPIArray['episodefirstaired'][$i].' '.$TheTVDBAPIArray['airstime']));
			if(!$airdate)
				continue;

			$db->queryInsert(sprintf('INSERT INTO episodeinfo
			(rageID, tvdbID, imdbID, showtitle, airdate, fullep, eptitle, director, gueststars, overview, rating, writer, epabsolute)
			VALUES (0, %d, %d, %s, %s, %s, %s, %s, %s, %s, %F, %s, %d)
			ON DUPLICATE KEY UPDATE
			tvdbID=%1$d, imdbID=%2$d, showtitle=%3$s, airdate=%4$s, fullep=%5$s, eptitle=%6$s, director=%7$s,
			gueststars=%8$s, overview=%9$s, rating=%10$F, writer=%11$s, epabsolute=%12$s',
					$TheTVDBAPIArray['episodetvdbID'][$i], $TheTVDBAPIArray['episodeimdbID'][$i], $db->escapeString($TheTVDBAPIArray['seriesname']), $db->escapeString($airdate),
					$db->escapeString(str_pad($TheTVDBAPIArray['episodeseason'][$i], 2, '0', STR_PAD_LEFT).'x'.str_pad($TheTVDBAPIArray['episodenumber'][$i], 2, '0', STR_PAD_LEFT)),
					$db->escapeString($TheTVDBAPIArray['episodename'][$i]), $db->escapeString($TheTVDBAPIArray['episodedirector'][$i]),
					$db->escapeString($TheTVDBAPIArray['episodegueststars'][$i]), $db->escapeString(substr($TheTVDBAPIArray['episodeoverview'][$i],0,10000)),
					$TheTVDBAPIArray['episoderating'][$i], $db->escapeString($TheTVDBAPIArray['episodewriter'][$i]), $TheTVDBAPIArray['episodeabsolutenumber'][$i]));
		}
	}

	public function updateSeries($tvdbID, $actors, $airsday, $airstime, $contentrating, $firstaired, $genre, $imdbID, $network, $overview, $rating, $ratingcount, $runtime, $seriesname, $status)
	{
		$db = new DB();
		if ($airstime != "")
			$airstime = $db->escapeString(date("H:i:s", strtotime($airstime)));
		else
			$airstime = "null";

		if ($firstaired != "")
			$firstaired = $db->escapeString($firstaired);
		else
			$firstaired = "null";

		if ($rating != "")
			$rating = $db->escapeString($rating);
		else
			$rating = "null";

		$sql = sprintf('UPDATE thetvdb
		SET actors=%s, airsday=%s, airstime=%s, contentrating=%s, firstaired=%s, genre=%s, imdbID=%d, network=%s,
		overview=%s, rating=%s, ratingcount=%d, runtime=%d, seriesname=%s, status=%s, createddate=now()
		WHERE tvdbID = %d', $db->escapeString($actors), $db->escapeString($airsday), $airstime, $db->escapeString($contentrating),
			$firstaired, $db->escapeString($genre), $imdbID, $db->escapeString($network), $db->escapeString($overview), $rating,
			$ratingcount, $runtime, $db->escapeString($seriesname), $db->escapeString($status), $tvdbID);

		$db->queryExec($sql);
	}

	public function deleteTitle($tvdbID)
	{
		$db = new DB();

		$db->queryExec(sprintf("DELETE FROM thetvdb WHERE tvdbID = %d", $tvdbID));
	}

	public function addEmptySeries($seriesname)
	{
		$db = new DB();

		$db->queryInsert(sprintf("INSERT INTO thetvdb (tvdbID, seriesname, createddate) VALUES (0, %s, now())", $db->escapeString($seriesname)));
	}

	public function getSeriesInfoByID($tvdbID)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT * FROM thetvdb WHERE tvdbID = %d", $tvdbID));
	}

	public function getSeriesInfoByName($seriesname)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT * FROM thetvdb WHERE seriesname = %s", $db->escapeString($seriesname)));
	}

	public function getSeriesRange($start, $num, $seriesname='')
	{
		$db = new DB();

		$limit = ($start === false) ? '' : " LIMIT ".$start.",".$num;

		$rsql = '';
		if ($seriesname != '')
			$rsql .= sprintf("AND thetvdb.seriesname LIKE %s ", $db->escapeString("%".$seriesname."%"));

		return $db->query(sprintf(" SELECT ID, tvdbID, seriesname, overview FROM thetvdb WHERE 1=1 %s AND tvdbID > %d ORDER BY tvdbID ASC".$limit, $rsql, 0));
	}

	public function getSeriesCount($seriesname='')
	{
		$db = new DB();

		$rsql = '';
		if ($seriesname != '')
			$rsql .= sprintf("AND thetvdb.seriesname LIKE %s ", $db->escapeString("%".$seriesname."%"));

		$res = $db->queryOneRow(sprintf("SELECT count(ID) AS num FROM thetvdb WHERE 1=1 %s ", $rsql));

		return $res["num"];
	}

	public function lookupSeriesID($seriesname)
	{

		$apiresponse = Utility::getUrl(['url' => $this->MIRROR.'/api/GetSeries.php?seriesname='.preg_replace('/\s+/', '+', $seriesname).'&language=all']);

		if(!$apiresponse)
			return false;

		$seriesidXML = @simplexml_load_string($apiresponse);
		if(!$seriesidXML)
			return false;

		$seriesid = 0;
		foreach($seriesidXML as $item)
			if(preg_match('/^'.preg_replace('/\+/', ' ', str_replace('/', '\/', $seriesname)).'$/i', (string) $item->SeriesName)) {
				$seriesid = (int) $item->seriesid;
				break;
			}

		return $seriesid;
	}

	public function notFound($seriesName, $fullep, $releaseID, $echooutput=true)
	{
		if($this->echooutput && $echooutput)
			echo 'TheTVDB : '.$seriesName.' '.$fullep." Not found\n";

		$db = new DB();
		$db->queryExec(sprintf('UPDATE releases SET episodeinfoID = -2 WHERE ID = %d', $releaseID));
	}

	public function processReleases()
	{
		$db = new DB();

		$results = $db->queryDirect(sprintf("SELECT ID, searchname, rageID, anidbid, seriesfull, season, episode, tvtitle FROM releases WHERE episodeinfoID IS NULL AND categoryID IN ( SELECT ID FROM category WHERE parentID = %d ) LIMIT 150", Category::CAT_PARENT_TV));

		if ($db->getNumRows($results) > 0)
		{
			if ($this->echooutput)
				echo "TheTVDB : Looking up last ".$db->getNumRows($results)." releases\n";

			while ($arr = $db->getAssocArray($results))
			{
				unset($TheTVDBAPIArray, $episodeArray, $fullep, $epabsolute, $additionalSql);

				$seriesName = '';
				if($arr['rageID'] > 0) {
					$seriesName = $db->queryOneRow(sprintf('SELECT releasetitle AS seriesName FROM tvrage WHERE rageID = %d', $arr['rageID']));
				}
				elseif($arr['anidbid'] > 0) {
					$seriesName = $db->queryOneRow(sprintf('SELECT title AS seriesName FROM anidb WHERE anidbid = %d', $arr['anidbid']));
				}

				if(empty($seriesName) || !$seriesName)
				{
					$this->notFound($seriesName, "", $arr['ID'], false);
					continue;
				}

				$seriesName = str_replace('`', '\'', $seriesName['seriesName']);
				if(!preg_match('/[21]\d{3}\/\d{2}\/\d{2}/', $arr['seriesfull']))
					$fullep = str_pad(str_replace('S', '', $arr['season']), 2, '0', STR_PAD_LEFT).'x'.str_pad(str_replace('E', '', $arr['episode']), 2, '0', STR_PAD_LEFT);
				else
					$fullep = str_replace('/', '-', $arr['seriesfull']);

				$TheTVDBAPIArray = $this->getSeriesInfoByName($seriesName);
				if(!$TheTVDBAPIArray)
				{
					$seriesid = $this->lookupSeriesID($seriesName);
					if($seriesid > 0)
					{
						if($TheTVDBAPIArray = $this->TheTVDBAPI($seriesid, $seriesName))
						{
							$this->addSeries($TheTVDBAPIArray);
							$this->addEpisodes($TheTVDBAPIArray);
						}
						else
						{
							$this->addEmptySeries($seriesName);
							$this->notFound($seriesName, $fullep, $arr['ID']);
							continue;
						}
					}
					else
					{
						$this->addEmptySeries($seriesName);
						$this->notFound($seriesName, $fullep, $arr['ID']);
						continue;
					}
				}
				else if($TheTVDBAPIArray['tvdbID'] > 0 && ((time() - strtotime($TheTVDBAPIArray['createddate'])) > 604800))
				{
					$TheTVDBAPIArray = $this->TheTVDBAPI($TheTVDBAPIArray['tvdbID'], $seriesName);

					$this->updateSeries($TheTVDBAPIArray['tvdbID'], $TheTVDBAPIArray['actors'], $TheTVDBAPIArray['airsday'],
						$TheTVDBAPIArray['airstime'], $TheTVDBAPIArray['contentrating'], $TheTVDBAPIArray['firstaired'], $TheTVDBAPIArray['genre'],
						$TheTVDBAPIArray['imdbID'], $TheTVDBAPIArray['network'], $TheTVDBAPIArray['overview'], $TheTVDBAPIArray['rating'],
						$TheTVDBAPIArray['ratingcount'], $TheTVDBAPIArray['runtime'], $TheTVDBAPIArray['seriesname'], $TheTVDBAPIArray['status']);

					$this->addEpisodes($TheTVDBAPIArray);
				}

				if($TheTVDBAPIArray['tvdbID'] > 0)
				{
					$epabsolute = '0';
					if($arr['anidbid'] > 0)
					{
						if(preg_match('/S(?P<season>\d+)[ED](?P<episode>\d+)/', $arr['episode'], $seasonEpisode))
						{
							$arr['season'] = $seasonEpisode['season'];
							$arr['episode'] = $seasonEpisode['episode'];
						}
						else
							$epabsolute = $arr['episode'];
					}

					$Episode = new Episode();
					$episodeArray = $Episode->getEpisodeInfoByName($seriesName, $fullep, (string) $epabsolute);
					if(!$episodeArray)
					{
						$this->notFound($seriesName, $fullep, $arr['ID']);
						continue;
					}
				}
				else
				{
					$this->notFound($seriesName, $fullep, $arr['ID']);
					continue;
				}

				$additionalSql = '';
				if($arr['anidbid'] > 0 && $episodeArray['epabsolute'] > 0)
				{
					$additionalSql = sprintf(', season = NULL, episode = %d, tvtitle = %s, tvairdate = %s',
						$episodeArray['epabsolute'],
						$db->escapeString($episodeArray['epabsolute'].' - '.str_replace('\'', '`', $episodeArray['eptitle'])),
						$db->escapeString($episodeArray['airdate']));
				}

				$db->queryExec(sprintf('UPDATE releases SET tvdbID = %d, episodeinfoID = %d %s WHERE ID = %d',
						$TheTVDBAPIArray['tvdbID'], $episodeArray['ID'], $additionalSql, $arr['ID']));

				if($this->echooutput)
				{
					echo 'TheTVDB : '.$seriesName.' '.$fullep." returned ".$episodeArray['tvdbID']."\n";
				}
			}
		}
	}

	public function TheTVDBAPI($seriesid, $seriesName)
	{
		$apiresponse = Utility::getUrl([$this->MIRROR.'/api/'.self::APIKEY.'/series/'.$seriesid.'/all/en.xml']); //.zip?

		if(!$apiresponse)
			return false;

		$TheTVDBAPIXML = @simplexml_load_string($apiresponse);
		if(!$TheTVDBAPIXML)
			return false;

		foreach($TheTVDBAPIXML->Episode as $episode) {
			$episodetvdbIDArray[] = (int) $episode->id;
			$episodenumberArray[] = (int) $episode->Combined_episodenumber;
			$episodeseasonArray[] = (int) $episode->Combined_season;
			$episodedirectorArray[] = preg_replace('/^\||\|$/', '', (string) $episode->Director);
			$episodenameArray[] = preg_replace('/^\||\|$/', '', (string) $episode->EpisodeName);
			$episodefirstairedArray[] = (string) $episode->FirstAired;
			$episodegueststarsArray[] = preg_replace('/^\||\|$/', '', (string) $episode->GuestStars);
			$episodeimdbID[] = str_replace('tt', '', (string) $episode->IMDB_ID);
			$episodeoverviewArray[] = preg_replace('/^\||\|$/', '', (string) $episode->Overview);
			$episoderatingArray[] = preg_replace('/^\||\|$/', '', (string) $episode->Rating);
			$episodewriterArray[] = preg_replace('/^\||\|$/', '', (string) $episode->Writer);
			$episodeabsolutenumberArray[] = (int) $episode->absolute_number;
		}

		$TheTVDBAPIArray = array(
			'tvdbID' => $seriesid,
			'actors' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Actors),
			'airsday' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Airs_DayOfWeek),
			'airstime' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Airs_Time),
			'contentrating' => (string) $TheTVDBAPIXML->Series->ContentRating,
			'firstaired' => (string) $TheTVDBAPIXML->Series->FirstAired,
			'genre' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Genre),
			'imdbID' => (int) preg_replace('/^[^\d]+/', '', (string) $TheTVDBAPIXML->Series->IMDB_ID),
			'network' => (string) $TheTVDBAPIXML->Series->Network,
			'overview' => (string) $TheTVDBAPIXML->Series->Overview,
			'rating' => (float) $TheTVDBAPIXML->Series->Rating,
			'ratingcount' => (int) $TheTVDBAPIXML->Series->RatingCount,
			'runtime' => (int) $TheTVDBAPIXML->Series->Runtime,
			//'seriesname' => ((string) $TheTVDBAPIXML->Series->SeriesName != '') ? (string) $TheTVDBAPIXML->Series->SeriesName : $seriesName,
			'seriesname' => $seriesName,
			'status' => (string) $TheTVDBAPIXML->Series->Status,
			'episodetvdbID' => isset($episodetvdbIDArray) ? $episodetvdbIDArray : array(),
			'episodenumber' => isset($episodenumberArray) ? $episodenumberArray : array(),
			'episodeseason' => isset($episodeseasonArray) ? $episodeseasonArray : array(),
			'episodedirector' => isset($episodedirectorArray) ? $episodedirectorArray : array(),
			'episodename' => isset($episodenameArray) ? $episodenameArray : array(),
			'episodefirstaired' => isset($episodefirstairedArray) ? $episodefirstairedArray : array(),
			'episodegueststars' => isset($episodegueststarsArray) ? $episodegueststarsArray : array(),
			'episodeimdbID' => isset($episodeimdbID) ? $episodeimdbID : array(),
			'episodeoverview' => isset($episodeoverviewArray) ? $episodeoverviewArray : array(),
			'episoderating' => isset($episoderatingArray) ? $episoderatingArray : array(),
			'episodewriter' => isset($episodewriterArray) ? $episodewriterArray : array(),
			'episodeabsolutenumber' => isset($episodeabsolutenumberArray) ? $episodeabsolutenumberArray : array(),
		);

		return $TheTVDBAPIArray;
	}
}