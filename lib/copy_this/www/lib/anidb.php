<?php
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/category.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/site.php");

/**
 * Class to handle the querying of anidb, and storage/retrieval of anime
 * data from the database
 */
class AniDB
{
	const CLIENT = 'newznab';
	const CLIENTVER = 2;
	const ANIMETITLESURL = 'http://anidb.net/api/anime-titles.xml.gz';
	const ANIMERETRYMINS = 60;

	/**
	 * Default constructor.
	 */
	function AniDB($echooutput = false)
	{
		$this->echooutput = $echooutput;
		$this->imgSavePath = WWW_DIR . 'covers/anime/';
	}

	/**
	 * Retrieve a list of all anime titles from anidb API.
	 */
	public function animetitlesUpdate()
	{
		$db = new DB();

		$animeStatus = $db->queryOneRow("SELECT * FROM site WHERE setting='animetitle_banned'");
		$lastUpdate = $db->queryOneRow("SELECT createddate FROM animetitles LIMIT 1");

		// Wait an hour before trying again.
		if ($animeStatus['value'] != 0) {
			if ((time() - strtotime($animeStatus['updateddate'])) < (self::ANIMERETRYMINS * 60)) {
				if ($this->echooutput)
					echo "AniDB   : Error in previous attempt, " . number_format(self::ANIMERETRYMINS - (time() - strtotime($animeStatus['updateddate'])) / 60, 0) . " mins remaining before retry.\n";

				return false;
			} else {
				$db->exec("update site SET `value` = '0', `updateddate` = NOW() WHERE `setting` = 'animetitle_banned'");
			}
		}

		if (isset($lastUpdate['createddate']) && (time() - strtotime($lastUpdate['createddate'])) < 604800) {
			return false;
		}

		if ($this->echooutput)
			echo "AniDB   : Updating animetitles.";

		$animeXml = Utility::getUrl(self::ANIMETITLESURL, '', '', 'gzip');
		if (!$animeXml) {
			$db->exec("update site SET `value` = '1', `updateddate` = NOW() WHERE `setting` = 'animetitle_banned'");
			if ($this->echooutput)
				echo "AniDB   : Error fetching data, will retry in " . self::ANIMERETRYMINS . "mins.\n";

			return false;
		}

		$animetitles = simplexml_load_string($animeXml);
		if (!$animetitles) {
			$db->exec("update site SET `value` = '1', `updateddate` = NOW() WHERE `setting` = 'animetitle_banned'");
			if ($this->echooutput)
				echo "AniDB   : Error fetching data, will retry in " . self::ANIMERETRYMINS . "mins.\n";

			return false;
		}

		if ($this->echooutput)
			echo ".";

		$db->exec("truncate table animetitles");

		$title = $animetitles->xpath('//title[@type="official" and @xml:lang="en"]');
		foreach ($title as $t) {
			$p = $t->xpath("..");
			$aid = $p[0]->attributes()->aid;
			$title = $t;
			$db->queryInsert(sprintf("INSERT INTO animetitles (anidbID, title, createddate) VALUES (%d, %s, now())", $aid, $db->escapeString($title)));
		}

		if ($this->echooutput)
			echo " done.\n";
	}

	/**
	 * Insert an anime title to the database.
	 */
	public function addTitle($AniDBAPIArray)
	{
		$db = new DB();

		$query = sprintf("INSERT INTO anidb (anidbID, title, type, startdate, enddate, related, creators, description, rating, picture, categories, characters, epnos, airdates, episodetitles, createddate) VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, now())",
			$AniDBAPIArray['anidbID'], $db->escapeString($AniDBAPIArray['title']), $db->escapeString($AniDBAPIArray['type']), $db->escapeString($AniDBAPIArray['startdate']),
			$db->escapeString($AniDBAPIArray['enddate']), $db->escapeString($AniDBAPIArray['related']), $db->escapeString($AniDBAPIArray['creators']),
			$db->escapeString($AniDBAPIArray['description']), $db->escapeString($AniDBAPIArray['rating']), $db->escapeString($AniDBAPIArray['picture']),
			$db->escapeString($AniDBAPIArray['categories']), $db->escapeString($AniDBAPIArray['characters']), $db->escapeString($AniDBAPIArray['epnos']),
			$db->escapeString($AniDBAPIArray['airdates']), $db->escapeString($AniDBAPIArray['episodetitles'])
		);

		$db->queryInsert($query);
	}

	/**
	 * Update an anime title in the database.
	 */
	public function updateTitle($anidbID, $title, $type, $startdate, $enddate, $related, $creators, $description, $rating, $categories, $characters, $epnos, $airdates, $episodetitles)
	{
		$db = new DB();

		$db->exec(sprintf("update anidb
		SET title=%s, type=%s, startdate=%s, enddate=%s, related=%s, creators=%s, description=%s, rating=%s, categories=%s, characters=%s, epnos=%s, airdates=%s, episodetitles=%s, createddate=now()
		WHERE anidbID = %d", $db->escapeString($title), $db->escapeString($type), $db->escapeString($startdate), $db->escapeString($enddate), $db->escapeString($related),
				$db->escapeString($creators), $db->escapeString($description), $db->escapeString($rating), $db->escapeString($categories), $db->escapeString($characters),
				$db->escapeString($epnos), $db->escapeString($airdates), $db->escapeString($episodetitles), $anidbID
			)
		);
	}

	/**
	 * Delete an anime title from the database.
	 */
	public function deleteTitle($anidbID)
	{
		$db = new DB();

		$db->exec(sprintf("DELETE FROM anidb WHERE anidbID = %d", $anidbID));
	}

	/**
	 * Retrieve an anime title by name.
	 */
	public function getanidbID($title)
	{
		$db = new DB();

		$anidbID = $db->queryOneRow(sprintf("SELECT anidbID as anidbID FROM animetitles WHERE title REGEXP %s LIMIT 1", $db->escapeString('^' . $title . '$')));

		return $anidbID['anidbID'];
	}

	/**
	 * Retrieve a list of all anime, filterable by initial letter.
	 */
	public function getAnimeList($letter = '', $animetitle = '')
	{
		$db = new DB();

		$rsql = '';
		if ($letter != '') {
			if ($letter == '0-9')
				$letter = '[0-9]';

			$rsql .= sprintf("AND anidb.title REGEXP %s", $db->escapeString('^' . $letter));
		}

		$tsql = '';
		if ($animetitle != '') {
			$tsql .= sprintf("AND anidb.title LIKE %s", $db->escapeString("%" . $animetitle . "%"));
		}

		$sql = sprintf(" SELECT anidb.ID, anidb.anidbID, anidb.title, anidb.type, anidb.categories, anidb.rating, anidb.startdate, anidb.enddate
			FROM anidb WHERE anidb.anidbID > 0 %s %s GROUP BY anidb.anidbID ORDER BY anidb.title ASC", $rsql, $tsql
		);

		return $db->query($sql);
	}

	/**
	 * Retrieve a list anime by limit range.
	 */
	public function getAnimeRange($start, $num, $animetitle = '')
	{
		$db = new DB();

		if ($start === false)
			$limit = '';
		else
			$limit = " LIMIT " . $start . "," . $num;

		$rsql = '';
		if ($animetitle != '')
			$rsql .= sprintf("AND anidb.title LIKE %s ", $db->escapeString("%" . $animetitle . "%"));

		return $db->query(sprintf(" SELECT ID, anidbID, title, description FROM anidb WHERE 1=1 %s ORDER BY anidbID ASC" . $limit, $rsql));
	}

	/**
	 * Retrieve a count of anime.
	 */
	public function getAnimeCount($animetitle = '')
	{
		$db = new DB();

		$rsql = '';
		if ($animetitle != '')
			$rsql .= sprintf("AND anidb.title LIKE %s ", $db->escapeString("%" . $animetitle . "%"));

		$res = $db->queryOneRow(sprintf("SELECT count(ID) AS num FROM anidb where 1=1 %s ", $rsql));

		return $res["num"];
	}

	/**
	 * Retrieve an anime row.
	 */
	public function getAnimeInfo($anidbID)
	{
		$db = new DB();
		$animeInfo = $db->query(sprintf("SELECT * FROM anidb WHERE anidbID = %d", $anidbID));

		return isset($animeInfo[0]) ? $animeInfo[0] : false;
	}

	/**
	 * Strip an anime title to a cleaned version.
	 */
	public function cleanFilename($searchname)
	{
		$noforeign = 'English|Japanese|German|Danish|Flemish|Dutch|French|Swe(dish|sub)|Deutsch|Norwegian';

		// commented out original
//		$searchname = preg_replace('/^Arigatou[._ ]|\]BrollY\]|[._ ]v(er[._ ]?)?\d|Complete[._ ](?=Movie)|((HD)?DVD|B(luray|[dr])(rip)?)|Rs?\d|[xh][._ ]?264|A(C3|52)| \d+[pi]\s|[SD]ub(bed)?|Creditless/i', ' ', $searchname);

		// removed detection for blu ray prefix Br in titles, was causing problems with real words like, 'Brothers' - turning into ' others'
		$searchname = preg_replace('/^Arigatou[._ ]|\]BrollY\]|[._ ]v(er[._ ]?)?\d|Complete[._ ](?=Movie)|((HD)?DVD|B(luray|lu-ray)(rip)?)|Rs?\d|[xh][._ ]?264|A(C3|52)| \d+[pi]\s|[SD]ub(bed)?|Creditless/i', ' ', $searchname);

		$searchname = preg_replace('/(\[|\()(?!\d{4}\b)[^\]\)]+(\]|\))/', '', $searchname);
		$searchname = (preg_match_all("/[._ ]-[._ ]/", $searchname, $count) >= 2) ? preg_replace('/[^-]+$/i', '', $searchname) : $searchname;
		$searchname = preg_replace('/[._]| ?~ ?|\s{2,}| [-:]+ ?| 0(?=[1-9])| (Part|CD) ?\d*( ?(of|\/|\|) ?)?\d* ?$/i', ' ', $searchname);
		$searchname = preg_replace('/ (\d+) ?x ?(\d+)/i', ' S${1}E${2}', $searchname);
		$searchname = preg_replace('/( S\d+ ?E\d+| \d+ ?x ?\d+|Movie ?(\d+|[ivx]+))(.*$)/i', '${1}', $searchname);
		$searchname = preg_replace('/ ([12][890]\d{2})\b/i', ' (${1})', $searchname);
		$searchname = str_ireplace('\'', '`', $searchname);

		$cleanFilename = preg_replace('/ (NC)?Opening ?/i', ' OP', $searchname);
		$cleanFilename = preg_replace('/ (NC)?(Ending|Credits|Closing) ?/i', ' ED', $cleanFilename);
		$cleanFilename = preg_replace('/ (Trailer|TR(?= ?\d)) ?/i', ' T', $cleanFilename);
		$cleanFilename = preg_replace('/ (Promo|P(?= ?\d)) ?/i', ' PV', $cleanFilename);
		$cleanFilename = preg_replace('/ (Special|Extra|SP(?= ?\d)) ?(?! ?[a-z])/i', ' S', $cleanFilename);
		$cleanFilename = preg_replace('/ Vol(ume)? ?(?=\d)/i', ' Vol', $cleanFilename);
		$cleanFilename = preg_replace('/ (?:NC)?(OP|ED|[ST](?! ?[a-z])|PV)( ?v(er)? ?\d)? (?!\d )/i', ' ${1}1', $cleanFilename);
		$cleanFilename = preg_replace('/ (?:NC)?(OP|ED|[STV]|PV|O[AV][AV])(?: ?v(?:er)? ?\d+)? (?:(?:[A-Z]{2,3}(?:i?sode)?)?(\d+[a-z]?))/i', ' ${1}${2}', $cleanFilename);

		preg_match('/^(?P<title>.+) (?P<epno>(?:NC)?(?:[A-Z](?=\d)|[A-Z]{2,3})?(?![A-Z]| [A-Z]|$) ?(?:(?<![&+] | and | v| ver|\w Movie )\d{1,3}(?!\d)(?:-\d{1,3}(?!\d))?)(?:[a-z])?)/i', $cleanFilename, $cleanFilename);

		$cleanFilename['title'] = (isset($cleanFilename['title'])) ? trim($cleanFilename['title']) : trim($searchname);

		// titles often have leftover " - " at the end. remove it and trim spaces before it.
		$cleanFilename['title'] = preg_replace('/ +- *$/i', '', $cleanFilename['title']);

		// animetitles db uses format SeriesName: SubName instead of SeriesName - Subname
		$cleanFilename['title'] = preg_replace('/ +- +/i', ': ', $cleanFilename['title']);

		$cleanFilename['title'] = preg_replace('/([^a-z0-9\s])/i', '[${1}]?', $cleanFilename['title']);
		$cleanFilename['title'] = preg_replace('/( (The |Movie|O[AV][AV]|TV|\[\(\]\d{4}\[\)\]|Ep(isode)?|Vol(ume)?|Part|Phase|Chapter|Mission|(Director[`\']?s )?(Un)?C(ut|hoice)|Rem(aster|[iu]xx?)(ed)?|\w+ Arc|' . $noforeign . '))/i', '(${1})?', $cleanFilename['title']);

		$cleanFilename['epno'] = (isset($cleanFilename['epno'])) ? preg_replace('/^(NC|E(?!D)p?0*)|(?<=^|-|[a-z])0+|(?<!P)v(er)?(\d+)?$/i', '', $cleanFilename['epno']) : 1;
		if (preg_match('/S\d+ ?[ED]\d+/i', $searchname)) {
			preg_match('/S(\d+) ?([ED])(\d+)/i', $searchname, $epno);
			$cleanFilename['epno'] = 'S' . (int)$epno[1] . $epno[2] . (int)$epno[3];
		}

		return $cleanFilename;
	}

	/**
	 * Process all untagged releases in the anime category.
	 */
	public function processAnimeReleases()
	{
		$numtoProcess = 100;
		$db = new DB();
		$ri = new ReleaseImage();
		$s = new Sites();
		$site = $s->get();

		/**
		 * Grab a batch of 1000 releases and process until we have finished them, or hit the max configured
		 * API requests in an attempt to avoid flooding AniDB and then getting throttled.
		 *
		 * Most requests will be hitting our local DB, so we want to go through those as quick as possible.
		 */
		$numApiRequests = 0;
		$maxApiRequests = 5;
		$numSuccess = 0;
		$totalProcessed = 0;

		$results = $db->query(sprintf("SELECT searchname, ID FROM releases WHERE anidbID IS NULL AND categoryID IN ( SELECT ID FROM category WHERE categoryID = %d ) ORDER BY postdate DESC limit %d ", Category::CAT_TV_ANIME, $numtoProcess));

		$numResults = count($results);

		if (count($results) > 0) {
			if ($this->echooutput) {
				echo "AniDB   : Processing " . $numResults . " anime releases\n";
			}

			foreach ($results as $arr) {
				if ($numApiRequests >= $maxApiRequests) {
					break;
				}

				$totalProcessed++;

				/**
				 * Anime NZB filenames during imports get their [] brackets stripped from around the release group name.
				 * This causes problems with the regexes finding the real anime title.
				 * This section uses the NFO file, if it exists, to look up the "Complete name" field to use instead of the filename.
				 */

				if ($site->lookupnfo == 1) {
					// Look up the nfo file for this release.
					$nfoRes = $db->queryOneRow(sprintf("select uncompress(nfo) as nfo from releasenfo where releaseID = %d", $arr['ID']));

					// If there is one, then check if there is a "Complete name : <name>" entry.
					if (!empty($nfoRes)) {
						preg_match("/Complete name[ ]*:[ ]*(?P<completeName>.+)/i", $nfoRes['nfo'], $matches);

						$completeName = array_key_exists('completeName', $matches) ? $matches['completeName'] : "";

						if (!empty($completeName)) {
							// Found a complete name, use this instead of the searchname.
							$arr['searchname'] = $completeName;
						}
					}
				}

				$cleanFilename = $this->cleanFilename($arr['searchname']);

				$anidbID = $this->getanidbID($cleanFilename['title']);
				if (!$anidbID) {
					$db->exec(sprintf("update releases SET anidbID = %d, rageID = %d WHERE ID = %d", -1, -2, $arr["ID"]));
					continue;
				}

				if ($this->echooutput) {
					echo 'AniDB   : Looking up: ' . htmlentities($arr['searchname']) . "\n";
				}

				$AniDBAPIArray = $this->getAnimeInfo($anidbID);
				$lastUpdate = ((isset($AniDBAPIArray['createddate']) && (time() - strtotime($AniDBAPIArray['createddate'])) > 604800));

				if (!$AniDBAPIArray || $lastUpdate) {
					$numApiRequests++;
					$AniDBAPIArray = $this->AniDBAPI($anidbID);

					if (!$lastUpdate)
						$this->addTitle($AniDBAPIArray);
					else {
						$this->updateTitle($AniDBAPIArray['anidbID'], $AniDBAPIArray['title'], $AniDBAPIArray['type'], $AniDBAPIArray['startdate'],
							$AniDBAPIArray['enddate'], $AniDBAPIArray['related'], $AniDBAPIArray['creators'], $AniDBAPIArray['description'],
							$AniDBAPIArray['rating'], $AniDBAPIArray['categories'], $AniDBAPIArray['characters'], $AniDBAPIArray['epnos'],
							$AniDBAPIArray['airdates'], $AniDBAPIArray['episodetitles']
						);
					}

					if ($AniDBAPIArray['picture'])
						$ri->saveImage($AniDBAPIArray['anidbID'], 'http://img7.anidb.net/pics/anime/' . $AniDBAPIArray['picture'], $this->imgSavePath);
				}

				if ($AniDBAPIArray['anidbID']) {
					$epno = explode('|', $AniDBAPIArray['epnos']);
					$airdate = explode('|', $AniDBAPIArray['airdates']);
					$episodetitle = explode('|', $AniDBAPIArray['episodetitles']);

					$offset = -1;
					for ($i = 0; $i < count($epno); $i++) {
						if ($cleanFilename['epno'] == $epno[$i]) {
							$offset = $i;
							break;
						}
					}

					$airdate = isset($airdate[$offset]) ? $airdate[$offset] : $AniDBAPIArray['startdate'];
					$episodetitle = isset($episodetitle[$offset]) ? $episodetitle[$offset] : $cleanFilename['epno'];
					$tvtitle = ($episodetitle !== 'Complete Movie' && $episodetitle !== $cleanFilename['epno']) ? $cleanFilename['epno'] . " - " . $episodetitle : $episodetitle;

					if ($this->echooutput) {
						echo 'AniDB   : Found ' . $AniDBAPIArray['anidbID'] . " - " . $AniDBAPIArray['title'] . "\n";
					}

					$db->exec(sprintf("update releases SET episode=%s, tvtitle=%s, tvairdate=%s, anidbID=%d, rageID=%d WHERE ID = %d",
							$db->escapeString($cleanFilename['epno']), $db->escapeString($tvtitle), $db->escapeString($airdate), $AniDBAPIArray['anidbID'], -2, $arr["ID"]
						)
					);
					$numSuccess++;
				}
			}

			if ($this->echooutput) {
				echo "AniDB   : " . $numApiRequests . " AniDB API requests performed.\n";
				echo "AniDB   : " . $numSuccess . " anidbIDs parsed successfully.\n";
				echo "AniDB   : " . $totalProcessed . " anime releases processed.\n";
			}
		}
	}

	/**
	 * Issue a request to the anidb API.
	 */
	public function AniDBAPI($anidbID)
	{
		$s = new Sites();
		$site = $s->get();
		$db = new DB();

		$timeBetweenRequests = 10; //seconds, not sure how aggressive we can be with anidb.

		if ($site->anidb_banned) {
			$anidb_banned_diff = $db->queryOneRow("SELECT TIMESTAMPDIFF(HOUR,updateddate, NOW()) AS hours FROM site WHERE setting='anidb_banned'");
			if ($anidb_banned_diff['hours'] < 24) {
				echo "AniDB   : Banned from AniDB, " . (24 - $anidb_banned_diff['hours']) . " hours left to wait to try again...\n";

				return false;
			} else {
				$db->exec("update site SET `value` = '0', `updateddate` = NOW() WHERE `setting` = 'anidb_banned'");
			}
		}

		//to comply with flooding rule.
		echo "AniDB   : Requesting data from AniDB in ";

		for ($i = $timeBetweenRequests; $i > 0; $i--) {
			echo $i . ".";
			sleep(1);
		}
		echo "\n";

		$apiUrl = 'http://api.anidb.net:9001/httpapi?request=anime&client=' . self::CLIENT . '&clientver=' . self::CLIENTVER . '&protover=1&aid=' . $anidbID;

		$apiresponse = Utility::getUrl($apiUrl, '', '', 'gzip');

		if (!$apiresponse) {
			echo "AniDB   : Error getting response.\n";

			return false;
		} else {
			if (preg_match('/error.*Banned.*error/', $apiresponse, $valid)) {
				$db->exec("update site SET `value` = '1', `updateddate` = NOW() WHERE `setting` = 'anidb_banned'");
				echo "AniDB   : Banned reply from AniDB. Waiting 24 hours until retrying.";

				return false;
			} else {
				if (!preg_match('/anime id="\d+"/', $apiresponse, $valid)) {
					echo "AniDB   : No 'anime id' field found in response.\n";

					return false;
				}
			}
		}

		preg_match('/<title xml:lang="en" type="official">([^<]+)<\/title>/', $apiresponse, $safeTitle);
		if (!$safeTitle)
			preg_match('/<title xml:lang="x-jat" type="main">([^<]+)<\/title>/', $apiresponse, $safeTitle);

		$apiresponse = preg_replace('/<title xml:lang="(?!en\").*/', '', $apiresponse);

		$AniDBAPIXML = new SimpleXMLElement($apiresponse);
		if (!$AniDBAPIXML)
			return false;

		if ($AniDBAPIXML->relatedanime)
			foreach ($AniDBAPIXML->relatedanime as $related)
				$relatedArray[] = (string)$related->anime;

		if ($AniDBAPIXML->creators->name)
			foreach ($AniDBAPIXML->creators->name as $creator)
				$creatorsArray[] = (string)$creator;

		if ($AniDBAPIXML->categories->category)
			foreach ($AniDBAPIXML->categories->category as $category)
				$categoriesArray[] = (string)$category->name;

		if ($AniDBAPIXML->characters->character)
			foreach ($AniDBAPIXML->characters->character as $character)
				$charactersArray[] = (string)$character->name;

		foreach ($AniDBAPIXML->episodes->episode as $episode) {
			$epnosArray[] = (string)$episode->epno;
			$airdatesArray[] = (string)$episode->airdate;
			$episodetitlesArray[] = $episode->title[0];
		}

		$AniDBAPIArray = array(
			'anidbID'       => $anidbID,
			'title'         => $safeTitle[1],
			'type'          => (string)$AniDBAPIXML->type[0],
			'startdate'     => (string)$AniDBAPIXML->startdate[0],
			'enddate'       => (string)$AniDBAPIXML->enddate[0],
			'related'       => isset($relatedArray) ? implode($relatedArray, '|') : '',
			'creators'      => isset($creatorsArray) ? implode($creatorsArray, '|') : '',
			'description'   => (string)$AniDBAPIXML->description,
			'rating'        => (string)$AniDBAPIXML->ratings->permanent ? (string)$AniDBAPIXML->ratings->permanent : (string)$AniDBAPIXML->ratings->temporary,
			'picture'       => (string)$AniDBAPIXML->picture[0],
			'categories'    => isset($categoriesArray) ? implode($categoriesArray, '|') : '',
			'characters'    => isset($charactersArray) ? implode($charactersArray, '|') : '',
			'epnos'         => implode($epnosArray, '|'),
			'airdates'      => $airdatesArray ? implode($airdatesArray, '|') : '',
			'episodetitles' => implode($episodetitlesArray, '|'),
		);

		return $AniDBAPIArray;
	}
}