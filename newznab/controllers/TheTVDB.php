<?php
namespace newznab\controllers;

use newznab\db\Settings;
use newznab\utility\Utility;


class TheTVDB
{
	const PROJECT	= 'newznab-tmux';
	const APIKEY	= 'E1669B52D4FBFF11';

	/**
	 * @var \newznab\db\Settings
	 */
	public $pdo;

	/**
	 * @var bool
	 */
	public $echooutput;

	/**
	 * @param bool $echooutput
	 */
	public function __construct($echooutput = true)
	{
		$this->echooutput = (NN_ECHOCLI && $echooutput);
		$this->MIRROR = 'http://www.thetvdb.com';
		$this->pdo = new Settings();
		$this->echooutput = $echooutput;
	}

	/**
	 * @param $TheTVDBAPIArray
	 */
	public function addSeries($TheTVDBAPIArray)
	{

		$airstime = $TheTVDBAPIArray['airstime'];
		if($airstime != "")
			$airstime = $this->pdo->escapeString(date('H:i:s', strtotime($airstime)));
		else
			$airstime = "null";

		$firstaired = $TheTVDBAPIArray['firstaired'];
		if ($firstaired != "")
			$firstaired = $this->pdo->escapeString($firstaired);
		else
			$firstaired = "null";

		$rating = $TheTVDBAPIArray['rating'];
		if ($rating != "")
			$rating = $this->pdo->escapeString($rating);
		else
			$rating = "null";

		$this->pdo->queryInsert(sprintf("INSERT INTO thetvdb
		(tvdbid, actors, airsday, airstime, contentrating, firstaired, genre, imdbid, network, overview, rating, ratingcount, runtime, seriesname, status, createddate)
		VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %F, %d, %d, %s, %s, now())",
				$TheTVDBAPIArray['tvdbid'], $this->pdo->escapeString($TheTVDBAPIArray['actors']), $this->pdo->escapeString($TheTVDBAPIArray['airsday']),
				$airstime, $this->pdo->escapeString($TheTVDBAPIArray['contentrating']), $firstaired,
				$this->pdo->escapeString($TheTVDBAPIArray['genre']), $TheTVDBAPIArray['imdbid'], $this->pdo->escapeString($TheTVDBAPIArray['network']), $this->pdo->escapeString($TheTVDBAPIArray['overview']),
				$rating, $TheTVDBAPIArray['ratingcount'], $TheTVDBAPIArray['runtime'], $this->pdo->escapeString($TheTVDBAPIArray['seriesname']),
				$this->pdo->escapeString($TheTVDBAPIArray['status'])));
	}

	/**
	 * @param $TheTVDBAPIArray
	 */
	public function addEpisodes($TheTVDBAPIArray)
	{

		for($i=0; $i < count($TheTVDBAPIArray['episodetvdbID']); $i++) {
			$airdate = strftime('%Y-%m-%d %H:%M:%S', strtotime($TheTVDBAPIArray['episodefirstaired'][$i].' '.$TheTVDBAPIArray['airstime']));
			if(!$airdate)
				continue;

			$this->pdo->queryInsert(sprintf('INSERT INTO episodeinfo
			(rageid, tvdbid, imdbid, showtitle, airdate, fullep, eptitle, director, gueststars, overview, rating, writer, epabsolute)
			VALUES (0, %d, %d, %s, %s, %s, %s, %s, %s, %s, %F, %s, %d)
			ON DUPLICATE KEY UPDATE
			tvdbid=%1$d, imdbid=%2$d, showtitle=%3$s, airdate=%4$s, fullep=%5$s, eptitle=%6$s, director=%7$s,
			gueststars=%8$s, overview=%9$s, rating=%10$F, writer=%11$s, epabsolute=%12$s',
					$TheTVDBAPIArray['episodetvdbID'][$i], $TheTVDBAPIArray['episodeimdbID'][$i], $this->pdo->escapeString($TheTVDBAPIArray['seriesname']), $this->pdo->escapeString($airdate),
					$this->pdo->escapeString(str_pad($TheTVDBAPIArray['episodeseason'][$i], 2, '0', STR_PAD_LEFT).'x'.str_pad($TheTVDBAPIArray['episodenumber'][$i], 2, '0', STR_PAD_LEFT)),
					$this->pdo->escapeString($TheTVDBAPIArray['episodename'][$i]), $this->pdo->escapeString($TheTVDBAPIArray['episodedirector'][$i]),
					$this->pdo->escapeString($TheTVDBAPIArray['episodegueststars'][$i]), $this->pdo->escapeString(substr($TheTVDBAPIArray['episodeoverview'][$i],0,10000)),
					$TheTVDBAPIArray['episoderating'][$i], $this->pdo->escapeString($TheTVDBAPIArray['episodewriter'][$i]), $TheTVDBAPIArray['episodeabsolutenumber'][$i]));
		}
	}

	/**
	 * @param $tvdbID
	 * @param $actors
	 * @param $airsday
	 * @param $airstime
	 * @param $contentrating
	 * @param $firstaired
	 * @param $genre
	 * @param $imdbID
	 * @param $network
	 * @param $overview
	 * @param $rating
	 * @param $ratingcount
	 * @param $runtime
	 * @param $seriesname
	 * @param $status
	 */
	public function updateSeries($tvdbID, $actors, $airsday, $airstime, $contentrating, $firstaired, $genre, $imdbID, $network, $overview, $rating, $ratingcount, $runtime, $seriesname, $status)
	{
		if ($airstime != "")
			$airstime = $this->pdo->escapeString(date("H:i:s", strtotime($airstime)));
		else
			$airstime = "null";

		if ($firstaired != "")
			$firstaired = $this->pdo->escapeString($firstaired);
		else
			$firstaired = "null";

		if ($rating != "")
			$rating = $this->pdo->escapeString($rating);
		else
			$rating = "null";

		$sql = sprintf('UPDATE thetvdb
		SET actors=%s, airsday=%s, airstime=%s, contentrating=%s, firstaired=%s, genre=%s, imdbid=%d, network=%s,
		overview=%s, rating=%s, ratingcount=%d, runtime=%d, seriesname=%s, status=%s, createddate=now()
		WHERE tvdbid = %d', $this->pdo->escapeString($actors), $this->pdo->escapeString($airsday), $airstime, $this->pdo->escapeString($contentrating),
			$firstaired, $this->pdo->escapeString($genre), $imdbID, $this->pdo->escapeString($network), $this->pdo->escapeString($overview), $rating,
			$ratingcount, $runtime, $this->pdo->escapeString($seriesname), $this->pdo->escapeString($status), $tvdbID);

		$this->pdo->queryExec($sql);
	}

	/**
	 * @param $tvdbID
	 */
	public function deleteTitle($tvdbID)
	{
		$this->pdo->queryExec(sprintf("DELETE FROM thetvdb WHERE tvdbid = %d", $tvdbID));
	}

	/**
	 * @param $seriesname
	 */
	public function addEmptySeries($seriesname)
	{
		$this->pdo->queryInsert(sprintf("INSERT INTO thetvdb (tvdbid, seriesname, createddate) VALUES (0, %s, now())", $this->pdo->escapeString($seriesname)));
	}

	/**
	 * @param $tvdbID
	 *
	 * @return array|bool
	 */
	public function getSeriesInfoByID($tvdbID)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM thetvdb WHERE tvdbid = %d", $tvdbID));
	}

	/**
	 * @param $seriesname
	 *
	 * @return array|bool
	 */
	public function getSeriesInfoByName($seriesname)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM thetvdb WHERE seriesname = %s", $this->pdo->escapeString($seriesname)));
	}

	/**
	 * @param        $start
	 * @param        $num
	 * @param string $seriesname
	 *
	 * @return array
	 */
	public function getSeriesRange($start, $num, $seriesname='')
	{
		$limit = ($start === false) ? '' : " LIMIT ".$start.",".$num;

		$rsql = '';
		if ($seriesname != '')
			$rsql .= sprintf("AND thetvdb.seriesname LIKE %s ", $this->pdo->escapeString("%".$seriesname."%"));

		return $this->pdo->query(sprintf(" SELECT id, tvdbid, seriesname, overview FROM thetvdb WHERE 1=1 %s AND tvdbid > %d ORDER BY tvdbid ASC".$limit, $rsql, 0));
	}

	/**
	 * @param string $seriesname
	 *
	 * @return mixed
	 */
	public function getSeriesCount($seriesname='')
	{

		$rsql = '';
		if ($seriesname != '')
			$rsql .= sprintf("AND thetvdb.seriesname LIKE %s ", $this->pdo->escapeString("%".$seriesname."%"));

		$res = $this->pdo->queryOneRow(sprintf("SELECT count(id) AS num FROM thetvdb WHERE 1=1 %s ", $rsql));

		return $res["num"];
	}

	/**
	 * @param $seriesname
	 *
	 * @return bool|int
	 */
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

	/**
	 * @param      $seriesName
	 * @param      $fullep
	 * @param      $releaseID
	 * @param bool $echooutput
	 */
	public function notFound($seriesName, $fullep, $releaseID, $echooutput=true)
	{
		if($this->echooutput && $echooutput)
			echo 'TheTVDB : '.$seriesName.' '.$fullep." Not found\n";
		$this->pdo->queryExec(sprintf('UPDATE releases SET episodeinfoid = -2 WHERE id = %d', $releaseID));
	}

	/**
	 *
	 */
	public function processReleases()
	{

		$results = $this->pdo->queryDirect(sprintf("SELECT id, searchname, rageid, anidbid, seriesfull, season, episode, tvtitle FROM releases WHERE episodeinfoid IS NULL AND categoryid IN ( SELECT id FROM category WHERE parentid = %d ) LIMIT 150", Category::CAT_PARENT_TV));

		if ($this->pdo->getNumRows($results) > 0)
		{
			if ($this->echooutput)
				echo "TheTVDB : Looking up last ".$this->pdo->getNumRows($results)." releases\n";

			while ($arr = $this->pdo->getAssocArray($results))
			{
				unset($TheTVDBAPIArray, $episodeArray, $fullep, $epabsolute, $additionalSql);

				$seriesName = '';
				if($arr['rageid'] > 0) {
					$seriesName = $this->pdo->queryOneRow(sprintf('SELECT releasetitle AS seriesName FROM tvrage WHERE rageid = %d', $arr['rageid']));
				}
				elseif($arr['anidbid'] > 0) {
					$seriesName = $this->pdo->queryOneRow(sprintf('SELECT title AS seriesName FROM anidb WHERE anidbid = %d', $arr['anidbid']));
				}

				if(empty($seriesName) || !$seriesName)
				{
					$this->notFound($seriesName, "", $arr['id'], false);
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
						$TheTVDBAPIArray = $this->TheTVDBAPI($seriesid, $seriesName);
						if($TheTVDBAPIArray)
						{
							$this->addSeries($TheTVDBAPIArray);
							$this->addEpisodes($TheTVDBAPIArray);
						}
						else
						{
							$this->addEmptySeries($seriesName);
							$this->notFound($seriesName, $fullep, $arr['id']);
							continue;
						}
					}
					else
					{
						$this->addEmptySeries($seriesName);
						$this->notFound($seriesName, $fullep, $arr['id']);
						continue;
					}
				}
				else if($TheTVDBAPIArray['tvdbid'] > 0 && ((time() - strtotime($TheTVDBAPIArray['createddate'])) > 604800))
				{
					$TheTVDBAPIArray = $this->TheTVDBAPI($TheTVDBAPIArray['tvdbid'], $seriesName);

					$this->updateSeries($TheTVDBAPIArray['tvdbid'], $TheTVDBAPIArray['actors'], $TheTVDBAPIArray['airsday'],
						$TheTVDBAPIArray['airstime'], $TheTVDBAPIArray['contentrating'], $TheTVDBAPIArray['firstaired'], $TheTVDBAPIArray['genre'],
						$TheTVDBAPIArray['imdbid'], $TheTVDBAPIArray['network'], $TheTVDBAPIArray['overview'], $TheTVDBAPIArray['rating'],
						$TheTVDBAPIArray['ratingcount'], $TheTVDBAPIArray['runtime'], $TheTVDBAPIArray['seriesname'], $TheTVDBAPIArray['status']);

					$this->addEpisodes($TheTVDBAPIArray);
				}

				if($TheTVDBAPIArray['tvdbid'] > 0)
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
						$this->notFound($seriesName, $fullep, $arr['id']);
						continue;
					}
				}
				else
				{
					$this->notFound($seriesName, $fullep, $arr['id']);
					continue;
				}

				$additionalSql = '';
				if($arr['anidbid'] > 0 && $episodeArray['epabsolute'] > 0)
				{
					$additionalSql = sprintf(', season = NULL, episode = %d, tvtitle = %s, tvairdate = %s',
						$episodeArray['epabsolute'],
						$this->pdo->escapeString($episodeArray['epabsolute'].' - '.str_replace('\'', '`', $episodeArray['eptitle'])),
						$this->pdo->escapeString($episodeArray['airdate']));
				}

				$this->pdo->queryExec(sprintf('UPDATE releases SET tvdbid = %d, episodeinfoid = %d %s WHERE id = %d',
						$TheTVDBAPIArray['tvdbid'], $episodeArray['id'], $additionalSql, $arr['id']));

				if($this->echooutput)
				{
					echo 'TheTVDB : '.$seriesName.' '.$fullep." returned ".$episodeArray['tvdbid']."\n";
				}
			}
		}
	}

	/**
	 * @param $seriesid
	 * @param $seriesName
	 *
	 * @return array|bool
	 */
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
			'tvdbid' => $seriesid,
			'actors' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Actors),
			'airsday' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Airs_DayOfWeek),
			'airstime' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Airs_Time),
			'contentrating' => (string) $TheTVDBAPIXML->Series->ContentRating,
			'firstaired' => (string) $TheTVDBAPIXML->Series->FirstAired,
			'genre' => preg_replace('/^\||\|$/', '', (string) $TheTVDBAPIXML->Series->Genre),
			'imdbid' => (int) preg_replace('/^[^\d]+/', '', (string) $TheTVDBAPIXML->Series->IMDB_ID),
			'network' => (string) $TheTVDBAPIXML->Series->Network,
			'overview' => (string) $TheTVDBAPIXML->Series->Overview,
			'rating' => (float) $TheTVDBAPIXML->Series->Rating,
			'ratingcount' => (int) $TheTVDBAPIXML->Series->RatingCount,
			'runtime' => (int) $TheTVDBAPIXML->Series->Runtime,
			//'seriesname' => ((string) $TheTVDBAPIXML->Series->SeriesName != '') ? (string) $TheTVDBAPIXML->Series->SeriesName : $seriesName,
			'seriesname' => $seriesName,
			'status' => (string) $TheTVDBAPIXML->Series->Status,
			'episodetvdbID' => isset($episodetvdbIDArray) ? $episodetvdbIDArray : [],
			'episodenumber' => isset($episodenumberArray) ? $episodenumberArray : [],
			'episodeseason' => isset($episodeseasonArray) ? $episodeseasonArray : [],
			'episodedirector' => isset($episodedirectorArray) ? $episodedirectorArray : [],
			'episodename' => isset($episodenameArray) ? $episodenameArray : [],
			'episodefirstaired' => isset($episodefirstairedArray) ? $episodefirstairedArray : [],
			'episodegueststars' => isset($episodegueststarsArray) ? $episodegueststarsArray : [],
			'episodeimdbID' => isset($episodeimdbID) ? $episodeimdbID : [],
			'episodeoverview' => isset($episodeoverviewArray) ? $episodeoverviewArray : [],
			'episoderating' => isset($episoderatingArray) ? $episoderatingArray : [],
			'episodewriter' => isset($episodewriterArray) ? $episodewriterArray : [],
			'episodeabsolutenumber' => isset($episodeabsolutenumberArray) ? $episodeabsolutenumberArray : [],
		);

		return $TheTVDBAPIArray;
	}
}