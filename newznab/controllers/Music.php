<?php
require_once NN_LIBS . 'AmazonProductAPI.php';

use newznab\db\Settings;
use newznab\utility\Utility;

/**
 * This class looks up music info from external sources and stores/retrieves musicinfo data.
 */
class Music
{
	const NUMTOPROCESSPERTIME = 100;

	/**
	 * @var newznab\db\Settings
	 */
	public $pdo;

	/**
	 * @var bool
	 */
	public $echooutput;

	/**
	 * Default constructor.
	 *
	 * @param bool $echooutput
	 */
	public function __construct($echooutput = false)
	{
		$this->echooutput = (NN_ECHOCLI && $echooutput);
		$this->pdo = new Settings();
		$this->pubkey = $this->pdo->getSetting('amazonpubkey');
		$this->privkey = $this->pdo->getSetting('amazonprivkey');
		$this->asstag = $this->pdo->getSetting('amazonassociatetag');
		$this->imgSavePath = WWW_DIR.'covers/music/';
	}

	/**
	 * Get musicinfo row by id.
	 */
	public function getMusicInfo($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT musicinfo.*, genres.title as genres FROM musicinfo left outer join genres on genres.id = musicinfo.genreID where musicinfo.id = %d ", $id));
	}

	/**
	 * Get musicinfo row by name.
	 */
	public function getMusicInfoByName($artist, $album)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM musicinfo where artist like %s and title like %s", $this->pdo->escapeString("%".$artist."%"),  $this->pdo->escapeString("%".$album."%")));
	}

	/**
	 * Get musicinfo rows by limit.
	 */
	public function getRange($start, $num)
	{

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $this->pdo->query(" SELECT * FROM musicinfo ORDER BY createddate DESC".$limit);
	}

	/**
	 * Get count of all musicinfo rows.
	 */
	public function getCount()
	{
		$res = $this->pdo->queryOneRow("select count(id) as num from musicinfo");
		return $res["num"];
	}

	/**
	 * Get count of all musicinfo rows by filter.
	 */
	public function getMusicCount($cat, $maxage=-1, $excludedcats=[])
	{

		$browseby = $this->getBrowseBy();

		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1)
		{
			$catsrch = " (";
			foreach ($cat as $category)
			{
				if ($category != -1)
				{
					$categ = new Category();
					if ($categ->isParent($category))
					{
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist.=", ".$child["id"];

						if ($chlist != "-99")
							$catsrch .= " r.categoryid in (".$chlist.") or ";
					}
					else
					{
						$catsrch .= sprintf(" r.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch.= "1=2 )";
		}

		if ($maxage > 0)
			$maxage = sprintf(" and r.postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and r.categoryid not in (".implode(",", $excludedcats).")";

		$sql = sprintf("select count(r.id) as num from releases r inner join musicinfo m on m.id = r.musicinfoid and m.title != '' where r.passwordstatus <= (select value from settings where setting='showpasswordedrelease') and %s %s %s %s", $browseby, $catsrch, $maxage, $exccatlist);
		$res = $this->pdo->queryOneRow($sql, true);
		return $res["num"];
	}

	/**
	 * Get musicinfo rows for browse list by filters and limit.
	 */
	public function getMusicRange($cat, $start, $num, $orderby, $maxage=-1, $excludedcats=[])
	{

		$browseby = $this->getBrowseBy();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		$catsrch = "";
		if (count($cat) > 0 && $cat[0] != -1)
		{
			$catsrch = " (";
			foreach ($cat as $category)
			{
				if ($category != -1)
				{
					$categ = new Category();
					if ($categ->isParent($category))
					{
						$children = $categ->getChildren($category);
						$chlist = "-99";
						foreach ($children as $child)
							$chlist.=", ".$child["id"];

						if ($chlist != "-99")
							$catsrch .= " r.categoryid in (".$chlist.") or ";
					}
					else
					{
						$catsrch .= sprintf(" r.categoryid = %d or ", $category);
					}
				}
			}
			$catsrch.= "1=2 )";
		}

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and r.postdate > now() - interval %d day ", $maxage);

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and r.categoryid not in (".implode(",", $excludedcats).")";

		$order = $this->getMusicOrder($orderby);
		// query modified to join to musicinfo after limiting releases as performance issue prevented sane sql.
		$sql = sprintf(" SELECT r.*, r.id as releaseid, m.*, g.title as genre, groups.name as group_name, concat(cp.title, ' > ', c.title) as category_name, concat(cp.id, ',', c.id) as category_ids, rn.id as nfoid from releases r left outer join groups on groups.id = r.groupid inner join musicinfo m on m.id = r.musicinfoid and m.title != '' left outer join releasenfo rn on rn.releaseid = r.id and rn.nfo is not null left outer join category c on c.id = r.categoryid left outer join category cp on cp.id = c.parentid left outer join genres g on g.id = m.genreID inner join (select r.id from releases r inner join musicinfo m ON m.id = r.musicinfoid and m.title != '' where r.musicinfoid > 0 and r.passwordstatus <= (select value from settings where setting='showpasswordedrelease') and %s %s %s %s order by %s %s %s) x on x.id = r.id order by %s %s", $browseby, $catsrch, $maxagesql, $exccatlist, $order[0], $order[1], $limit, $order[0], $order[1]);
		return $this->pdo->query($sql, true);
	}

	/**
	 * Get musicinfo orderby column sql.
	 */
	public function getMusicOrder($orderby)
	{
		$order = ($orderby == '') ? 'r.postdate' : $orderby;
		$orderArr = explode("_", $order);
		switch($orderArr[0]) {
			case 'artist':
				$orderfield = 'm.artist';
				break;
			case 'size':
				$orderfield = 'r.size';
				break;
			case 'files':
				$orderfield = 'r.totalpart';
				break;
			case 'stats':
				$orderfield = 'r.grabs';
				break;
			case 'year':
				$orderfield = 'm.year';
				break;
			case 'genre':
				$orderfield = 'm.genreID';
				break;
			case 'posted':
			default:
				$orderfield = 'r.postdate';
				break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';
		return array($orderfield, $ordersort);
	}

	/**
	 * Get musicinfo orderby columns.
	 */
	public function getMusicOrdering()
	{
		return array('artist_asc', 'artist_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc', 'year_asc', 'year_desc', 'genre_asc', 'genre_desc');
	}

	/**
	 * Get musicinfo filter columns.
	 */
	public function getBrowseByOptions()
	{
		return array('artist'=>'artist', 'title'=>'title', 'genre'=>'genreID', 'year'=>'year');
	}

	/**
	 * Get musicinfo filter column sql for user selection.
	 */
	public function getBrowseBy()
	{
		$this->pdo = new newznab\db\Settings;

		$browseby = ' ';
		$browsebyArr = $this->getBrowseByOptions();
		foreach ($browsebyArr as $bbk=>$bbv)
		{
			if (isset($_REQUEST[$bbk]) && !empty($_REQUEST[$bbk]))
			{
				$bbs = stripslashes($_REQUEST[$bbk]);
				if (preg_match('/id/i', $bbv))
					$browseby .= "m.{$bbv} = $bbs AND ";
				else
					$browseby .= "m.$bbv LIKE(".$this->pdo->escapeString('%'.$bbs.'%').") AND ";
			}
		}
		return $browseby;
	}

	/**
	 * Update musicinfo row
	 */
	public function update($id, $title, $asin, $url, $salesrank, $artist, $publisher, $releasedate, $year, $tracks, $cover, $genreID)
	{

		$this->pdo->queryExec(sprintf("update musicinfo SET title=%s, asin=%s, url=%s, salesrank=%s, artist=%s, publisher=%s, releasedate='%s', year=%s, tracks=%s, cover=%d, genreID=%d, updateddate=NOW() WHERE id = %d",
				$this->pdo->escapeString($title), $this->pdo->escapeString($asin), $this->pdo->escapeString($url), $salesrank, $this->pdo->escapeString($artist), $this->pdo->escapeString($publisher), $releasedate, $this->pdo->escapeString($year), $this->pdo->escapeString($tracks), $cover, $genreID, $id));
	}

	/**
	 * Update musicinfo from external source
	 */
	public function updateMusicInfo($artist, $album, $year)
	{
		$gen = new Genres();
		$ri = new ReleaseImage();

		$mus = [];
		$amaz = $this->fetchAmazonProperties($artist." ".$album);
		if (!$amaz)
			return false;

		sleep(1);

		//load genres
		$defaultGenres = $gen->getGenres(Genres::MUSIC_TYPE);
		$genreassoc = [];
		foreach($defaultGenres as $dg) {
			$genreassoc[$dg['id']] = strtolower($dg['title']);
		}

		//
		// get album properties
		//
		$mus['coverurl'] = (string) $amaz->Items->Item->LargeImage->URL;
		if ($mus['coverurl'] != "")
			$mus['cover'] = 1;
		else
			$mus['cover'] = 0;

		$mus['title'] = (string) $amaz->Items->Item->ItemAttributes->Title;
		if (empty($mus['title']))
			$mus['title'] = $album;

		$mus['asin'] = (string) $amaz->Items->Item->ASIN;

		$mus['url'] = (string) $amaz->Items->Item->DetailPageURL;

		$mus['salesrank'] = (string) $amaz->Items->Item->SalesRank;
		if ($mus['salesrank'] == "")
			$mus['salesrank'] = 'null';

		$mus['artist'] = (string) $amaz->Items->Item->ItemAttributes->Artist;
		if (empty($mus['artist']))
			$mus['artist'] = $artist;

		$mus['publisher'] = (string) $amaz->Items->Item->ItemAttributes->Publisher;

		$mus['releasedate'] = $this->pdo->escapeString((string) $amaz->Items->Item->ItemAttributes->ReleaseDate);
		if ($mus['releasedate'] == "''")
			$mus['releasedate'] = 'null';

		$mus['review'] = "";
		if (isset($amaz->Items->Item->EditorialReviews))
			$mus['review'] = trim(strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content));

		$mus['year'] = $year;
		if ($mus['year'] == "")
			$mus['year'] = ($mus['releasedate'] != 'null' ? substr($mus['releasedate'], 1, 4) : date("Y"));

		$mus['tracks'] = "";
		if (isset($amaz->Items->Item->Tracks))
		{
			$tmpTracks = (array) $amaz->Items->Item->Tracks->Disc;
			$tracks = $tmpTracks['Track'];
			$mus['tracks'] = (is_array($tracks) && !empty($tracks)) ? implode('|', $tracks) : '';
		}

		//This is to verify the result back from amazon was at least somewhat related to what was intended.
		//If you are debugging releases comment out the following code to show all info

		$match = similar_text($artist, $mus['artist'], $artistpercent);
		//echo("Matched: Artist Percentage: $artistpercent%");
		$match = similar_text($album, $mus['title'], $albumpercent);
		//echo("Matched: Album Percentage: $albumpercent%");

		//If the artist is Various Artists, assume artist is 100%
		if (preg_match('/various/i', $artist))
			$artistpercent = '100';

		//If the Artist is less than 80% album must be 100%
		if ($artistpercent < '80')
		{
			if ($albumpercent != '100')
				return false;
		}

		//If the album is ever under 30%, it's probably not a match.
		if ($albumpercent < '30')
			return false;

		//This is the end of the recheck code. Comment out to this point to show all info.

		$genreKey = -1;
		$genreName = '';
		if (isset($amaz->Items->Item->BrowseNodes))
		{
			//had issues getting this out of the browsenodes obj
			//workaround is to get the xml and load that into its own obj
			$amazGenresXml = $amaz->Items->Item->BrowseNodes->asXml();
			$amazGenresObj = simplexml_load_string($amazGenresXml);
			$amazGenres = $amazGenresObj->xpath("//BrowseNodeId");

			foreach($amazGenres as $amazGenre)
			{
				$currNode = trim($amazGenre[0]);
				if (empty($genreName))
				{
					$genreMatch = $this->matchBrowseNode($currNode);
					if ($genreMatch !== false)
					{
						$genreName = $genreMatch;
						break;
					}
				}
			}
		}
		$mus['musicgenreID'] = $genreKey;

		$musicId = $this->addUpdateMusicInfo($mus['title'], $mus['asin'], $mus['url'],
			$mus['salesrank'], $mus['artist'], $mus['publisher'], $mus['releasedate'], $mus['review'],
			$mus['year'],$mus['musicgenreID'], $mus['tracks'], $mus['cover'] );

		if ($musicId)
		{
			//if ($this->echooutput)
			//	echo "added/updated album: ".$mus['title']." (".$mus['year'].")\n";

			$mus['cover'] = $ri->saveImage($musicId, $mus['coverurl'], $this->imgSavePath, 250, 250);
		}

		return $musicId;
	}

	/**
	 * Retrieve info from Amazon for a title.
	 */
	public function fetchAmazonProperties($title)
	{
		$obj = new AmazonProductAPI($this->pubkey, $this->privkey, $this->asstag);
		try
		{
			$result = $obj->searchProducts($title, AmazonProductAPI::MP3, "TITLE");
		}
		catch(Exception $e)
		{
			//if first search failed try the mp3downloads section
			try
			{
				// sleep for 1 second
				sleep(1);
				$result = $obj->searchProducts($title, AmazonProductAPI::MUSIC, "TITLE");

			}
			catch(Exception $e2)
			{
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Process all untagged releases to see if musicinfo exists for them.
	 */
	public function processMusicReleases()
	{
		$ret = 0;
		$numlookedup = 0;

		$res = $this->pdo->queryDirect(sprintf("SELECT searchname, id from releases where musicinfoid IS NULL and categoryid in ( select id from category where parentid = %d ) ORDER BY postdate DESC LIMIT 1000", Category::CAT_PARENT_MUSIC));
		if ($this->pdo->getNumRows($res) > 0)
		{
			if ($this->echooutput)
				echo "MusicPr : Processing ".$this->pdo->getNumRows($res)." audio releases\n";

			while ($arr = $this->pdo->getAssocArray($res))
			{
				if ($numlookedup > Music::NUMTOPROCESSPERTIME)
					return;

				$albumId = -2;
				$album = $this->parseArtist($arr['searchname']);
				if ($album !== false)
				{
					if ($this->echooutput)
						echo 'MusicPr : Looking up: '.$album["artist"].' - '.$album["album"]."\n";

					//check for existing music entry
					$albumCheck = $this->getMusicInfoByName($album["artist"], $album["album"]);

					if ($albumCheck === false)
					{
						//
						// get from amazon
						//
						$numlookedup++;
						$ret = $this->updateMusicInfo($album["artist"], $album["album"], $album['year']);
						if ($ret !== false)
						{
							$albumId = $ret;
						}
					}
					else
					{
						$albumId = $albumCheck["id"];
					}
				}

				$this->pdo->queryExec(sprintf("update releases SET musicinfoid = %d WHERE id = %d", $albumId, $arr["id"]));
			}
		}
	}

	/**
	 * Strip out an artist name from a release.
	 */
	public function parseArtist($releasename)
	{
		$result = [];
		/*TODO: FIX VA lookups
		if (substr($releasename, 0, 3) == 'VA-') {
				$releasename = trim(str_replace('VA-', '', $releasename));
		} elseif (substr($name, 0, 3) == 'VA ') {
				$releasename = trim(str_replace('VA ', '', $releasename));
		}
		*/
		if (preg_match('/mdvdr/i', $releasename))
			return false;

		//Replace VA with Various Artists
		$newName = preg_replace('/VA( |\-)/', 'Various Artists -', $releasename);

		//remove years, vbr etc
		$newName = preg_replace('/\(.*?\)/i', '', $newName);
		//remove double dashes
		$newName = str_replace('--', '-', $newName);
		$newName = str_replace('FLAC', '', $newName);

		$name = explode("-", $newName);
		$name = array_map("trim", $name);

		if (is_array($name) && sizeof($name) > 1)
		{
			$albumi = 1;
			if ((strlen($name[0]) <= 2 || strlen($name[1]) <= 2) && !preg_match('/Various Artists/i', $name[0]))
			{
				$name[0] = $name[0].'-'.$name[1];
				$albumi = 2;
			}
			elseif (strlen($name[1]) <= 2 && preg_match('/Various Artists/i', $name[0]) && sizeof($name) > 2)
			{
				$name[2] = $name[1].'-'.$name[2];
				$albumi = 2;
			}

			if (!isset($name[$albumi]))
				return false;

			if (preg_match('/^the /i', $name[0])) {
				$name[0] = preg_replace('/^the /i', '', $name[0]).', The';
			}
			if (preg_match('/deluxe edition|single|nmrVBR|READ NFO/i', $name[$albumi], $albumType)) {
				$name[$albumi] = preg_replace('/'.$albumType[0].'/i', '', $name[$albumi]);
			}
			$result['artist'] = trim($name[0]);
			$result['album'] = trim($name[$albumi]);
		}

		//make sure we've actually matched an album name
		if (isset($result['album']))
		{
			if (preg_match('/^(nmrVBR|VBR|WEB|SAT|20\d{2}|19\d{2}|CDM|EP)$/i',$result['album']))
			{
				$result['album'] = '';
			}
		}

		preg_match('/((?:19|20)\d{2})/i', $releasename, $year);
		$result['year'] = (isset($year[1]) && !empty($year[1])) ? $year[1] : '';

		$result['releasename'] = $releasename;

		return (!empty($result['artist']) && !empty($result['album'])) ? $result : false;
	}

	/**
	 * Process all releases tagged as musicinfoid -2 to attempt to retrieve properties from mediainfo xml.
	 */
	public function processMusicReleaseFromMediaInfo()
	{
		$res = $this->pdo->query("SELECT r.searchname, ref.releaseid, ref.mediainfo FROM releaseextrafull ref INNER JOIN releases r ON r.id = ref.releaseid WHERE r.musicinfoid = -2");

		$rescount = sizeof($res);
		if ($rescount > 0)
		{
			if ($this->echooutput)
				echo "MusicPr : Processing ".$rescount." audio releases via mediainfo\n";

			//load genres
			$gen = new Genres();
			$defaultGenres = $gen->getGenres(Genres::MUSIC_TYPE);
			$genreassoc = [];
			foreach($defaultGenres as $dg)
				$genreassoc[$dg['id']] = strtolower($dg['title']);

			foreach($res as $rel)
			{
				$albumId = -3;
				$mi = null;
				$mi = @simplexml_load_string($rel["mediainfo"]);
				if ($mi != null)
				{
					$artist = (string) $mi->File->track[0]->Performer;
					$album = (string) $mi->File->track[0]->Album;
					$year = (string) $mi->File->track[0]->Recorded_date;
					$genre = (string) $mi->File->track[0]->Genre;
					$publisher = (string) $mi->File->track[0]->Publisher;

					$albumCheck = $this->getMusicInfoByName($artist, $album);
					if ($albumCheck === false)
					{
						//
						// insert new musicinfo
						//
						$genreKey = -1;
						if ($genre != "")
							$albumId = $this->addUpdateMusicInfo($album, "", "",  "null", $artist,
								$publisher, "null", "", $year, $genreKey, "", 0 );
					}
					else
					{
						$albumId = $albumCheck["id"];
					}
				}

				$sql = sprintf("update releases set musicinfoid = %d where id = %d", $albumId, $rel["releaseid"]);

				$this->pdo->queryExec($sql);
			}
		}

		return true;
	}

	/**
	 * Insert or update a musicinfo row.
	 */
	public function addUpdateMusicInfo($title, $asin, $url, $salesrank, $artist, $publisher, $releasedate, $review, $year, $genreID, $tracks, $cover)
	{

		if (strlen($year) > 4)  {
			if (preg_match("/\d{4}/", $year, $matches))
				$year = $this->pdo->escapeString($matches[0]);
			else
				$year = "null";
		}
		else {
			$year = $this->pdo->escapeString($year);
		}

		$sql = sprintf("
		INSERT INTO musicinfo  (title, asin, url, salesrank,  artist, publisher, releasedate, review, year, genreID, tracks, cover, createddate, updateddate)
		VALUES (%s, %s, %s,  %s,  %s, %s, %s, %s, %s,   %s, %s, %d, now(), now())
			ON DUPLICATE KEY UPDATE  title = %s,  asin = %s,  url = %s,  salesrank = %s,  artist = %s,  publisher = %s,  releasedate = %s,  review = %s,  year = %s,  genreID = %s,  tracks = %s,  cover = %d,  createddate = now(),  updateddate = now()",
			$this->pdo->escapeString($title), $this->pdo->escapeString($asin), $this->pdo->escapeString($url),
			$salesrank, $this->pdo->escapeString($artist), $this->pdo->escapeString($publisher),
			$releasedate, $this->pdo->escapeString($review), $year,
			($genreID==-1?"null":$genreID), $this->pdo->escapeString($tracks), $cover,
			$this->pdo->escapeString($title), $this->pdo->escapeString($asin), $this->pdo->escapeString($url),
			$salesrank, $this->pdo->escapeString($artist), $this->pdo->escapeString($publisher),
			$releasedate, $this->pdo->escapeString($review), $this->pdo->escapeString($year),
			($genreID==-1?"null":$genreID), $this->pdo->escapeString($tracks), $cover  );

		$musicId = $this->pdo->queryInsert($sql);
		return $musicId;
	}

	/**
	 * Convert Amazon browsenodes to genres.
	 */
	public function matchBrowseNode($nodeId)
	{
		$str = '';

		//music nodes above mp3 download nodes
		switch($nodeId)
		{
			case '163420':
				$str = 'Music Video & Concerts';
				break;
			case '30':
			case '624869011':
				$str = 'Alternative Rock';
				break;
			case '31':
			case '624881011':
				$str = 'Blues';
				break;
			case '265640':
			case '624894011':
				$str = 'Broadway & Vocalists';
				break;
			case '173425':
			case '624899011':
				$str = "Children's Music";
				break;
			case '173429': //christian
			case '2231705011': //gospel
			case '624905011': //christian & gospel
				$str = 'Christian & Gospel';
				break;
			case '67204':
			case '624916011':
				$str = 'Classic Rock';
				break;
			case '85':
			case '624926011':
				$str = 'Classical';
				break;
			case '16':
			case '624976011':
				$str = 'Country';
				break;
			case '7': //dance & electronic
			case '624988011': //dance & dj
				$str = 'Dance & Electronic';
				break;
			case '32':
			case '625003011':
				$str = 'Folk';
				break;
			case '67207':
			case '625011011':
				$str = 'Hard Rock & Metal';
				break;
			case '33': //world music
			case '625021011': //international
				$str = 'World Music';
				break;
			case '34':
			case '625036011':
				$str = 'Jazz';
				break;
			case '289122':
			case '625054011':
				$str = 'Latin Music';
				break;
			case '36':
			case '625070011':
				$str = 'New Age';
				break;
			case '625075011':
				$str = 'Opera & Vocal';
				break;
			case '37':
			case '625092011':
				$str = 'Pop';
				break;
			case '39':
			case '625105011':
				$str = 'R&B';
				break;
			case '38':
			case '625117011':
				$str = 'Rap & Hip-Hop';
				break;
			case '40':
			case '625129011':
				$str = 'Rock';
				break;
			case '42':
			case '625144011':
				$str = 'Soundtracks';
				break;
			case '35':
			case '625061011':
				$str = 'Miscellaneous';
				break;
		}
		return ($str != '') ? $str : false;
	}
}