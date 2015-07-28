<?php
require_once NN_LIBS . 'AmazonProductAPI.php';

use newznab\db\Settings;

/**
 * This class looks up metadata about console releases and handles
 * storage/retrieval from the database.
 */
class Console
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
	 * Default constructor
	 *
	 * @param bool $echooutput
	 */
	public function __construct($echooutput=false)
	{
		$this->echooutput = (NN_ECHOCLI && $echooutput);
		$this->pdo = new Settings();
		$this->pubkey = $this->pdo->getSetting('amazonpubkey');
		$this->privkey = $this->pdo->getSetting('amazonprivkey');
		$this->asstag = $this->pdo->getSetting('amazonassociatetag');
		$this->imgSavePath = WWW_DIR.'covers/console/';
	}

	/**
	 * Get consoleinfo row by id.
	 */
	public function getConsoleInfo($id)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT consoleinfo.*, genres.title as genres FROM consoleinfo left outer join genres on genres.id = consoleinfo.genreid where consoleinfo.id = %d ", $id));
	}

	/**
	 * Get consoleinfo row by name and platform.
	 */
	public function getConsoleInfoByName($title, $platform)
	{
		return $this->pdo->queryOneRow(sprintf("SELECT * FROM consoleinfo where title like %s and platform like %s", $this->pdo->escapeString("%".$title."%"),  $this->pdo->escapeString("%".$platform."%")));
	}

	/**
	 * Get range of consoleinfo row by limit.
	 */
	public function getRange($start, $num)
	{

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $this->pdo->query(" SELECT * FROM consoleinfo ORDER BY createddate DESC".$limit);
	}

	/**
	 * Get count of all consoleinfo rows.
	 */
	public function getCount()
	{
		$res = $this->pdo->queryOneRow("select count(id) as num from consoleinfo");
		return $res["num"];
	}

	/**
	 * Get count of all consoleinfo rows for browse list.
	 */
	public function getConsoleCount($cat, $maxage=-1, $excludedcats=[])
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

		$sql = sprintf("select count(r.id) as num from releases r inner join consoleinfo con on con.id = r.consoleinfoid and con.title != '' where r.passwordstatus <= (select value from settings where setting='showpasswordedrelease') and %s %s %s %s", $browseby, $catsrch, $maxage, $exccatlist);
		$res = $this->pdo->queryOneRow($sql, true);
		return $res["num"];
	}

	/**
	 * Get range of consoleinfo rows for browse list.
	 */
	public function getConsoleRange($cat, $start, $num, $orderby, $maxage=-1, $excludedcats=[])
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

		$order = $this->getConsoleOrder($orderby);
		$sql = sprintf(" SELECT r.*, r.id as releaseid, con.*, g.title as genre, groups.name as group_name, concat(cp.title, ' > ', c.title) as category_name, concat(cp.id, ',', c.id) as category_ids, rn.id as nfoid from releases r left outer join groups on groups.id = r.groupid inner join consoleinfo con on con.id = r.consoleinfoid left outer join releasenfo rn on rn.releaseid = r.id and rn.nfo is not null left outer join category c on c.id = r.categoryid left outer join category cp on cp.id = c.parentid left outer join genres g on g.id = con.genreid where r.passwordstatus <= (select value from settings where setting='showpasswordedrelease') and %s %s %s %s order by %s %s".$limit, $browseby, $catsrch, $maxagesql, $exccatlist, $order[0], $order[1]);
		return $this->pdo->query($sql, true);
	}

	/**
	 * Get orderby column for console browse list.
	 */
	public function getConsoleOrder($orderby)
	{
		$order = ($orderby == '') ? 'r.postdate' : $orderby;
		$orderArr = explode("_", $order);
		switch($orderArr[0]) {
			case 'title':
				$orderfield = 'con.title';
			break;
			case 'platform':
				$orderfield = 'con.platform';
			break;
			case 'releasedate':
				$orderfield = 'con.releasedate';
			break;
			case 'genre':
				$orderfield = 'con.genreid';
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
			case 'posted':
			default:
				$orderfield = 'r.postdate';
			break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';
		return array($orderfield, $ordersort);
	}

	/**
	 * Get available orderby columns for console browse list.
	 */
	public function getConsoleOrdering()
	{
		return array('title_asc', 'title_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc', 'platform_asc', 'platform_desc', 'releasedate_asc', 'releasedate_desc', 'genre_asc', 'genre_desc');
	}

	/**
	 * Get available filter columns for console browse list.
	 */
	public function getBrowseByOptions()
	{
		return array('platform'=>'platform', 'title'=>'title', 'genre'=>'genreid');
	}

	/**
	 * Get sql for selected filter columns for console browse list.
	 */
	public function getBrowseBy()
	{

		$browseby = ' ';
		$browsebyArr = $this->getBrowseByOptions();
		foreach ($browsebyArr as $bbk=>$bbv) {
			if (isset($_REQUEST[$bbk]) && !empty($_REQUEST[$bbk])) {
				$bbs = stripslashes($_REQUEST[$bbk]);
				$browseby .= "con.$bbv LIKE(".$this->pdo->escapeString('%'.$bbs.'%').") AND ";
			}
		}
		return $browseby;
	}

	/**
	 * Update consoleinfo row.
	 */
	public function update($id, $title, $asin, $url, $salesrank, $platform, $publisher, $releasedate, $esrb, $cover, $genreID)
	{

		$this->pdo->queryExec(sprintf("update consoleinfo SET title=%s, asin=%s, url=%s, salesrank=%s, platform=%s, publisher=%s, releasedate='%s', esrb=%s, cover=%d, genreid=%d, updateddate=NOW() WHERE id = %d",
		$this->pdo->escapeString($title), $this->pdo->escapeString($asin), $this->pdo->escapeString($url), $salesrank, $this->pdo->escapeString($platform), $this->pdo->escapeString($publisher), $releasedate, $this->pdo->escapeString($esrb), $cover, $genreID, $id));
	}

	/**
	 * Check whether a title is available at Amazon and store its metadata.
	 */
	public function updateConsoleInfo($gameInfo)
	{
		$gen = new Genres();
		$ri = new ReleaseImage();

		$con = [];
		$amaz = $this->fetchAmazonProperties($gameInfo['title'], $gameInfo['node']);
		if (!$amaz)
			return false;

		//load genres
		$defaultGenres = $gen->getGenres(Genres::CONSOLE_TYPE);
		$genreassoc = [];
		foreach($defaultGenres as $dg) {
			$genreassoc[$dg['id']] = strtolower($dg['title']);
		}

		//
		// get game properties
		//
		$con['coverurl'] = (string) $amaz->Items->Item->LargeImage->URL;
		if ($con['coverurl'] != "")
			$con['cover'] = 1;
		else
			$con['cover'] = 0;

		$con['title'] = (string) $amaz->Items->Item->ItemAttributes->Title;
		if (empty($con['title']))
			$con['title'] = $gameInfo['title'];

		$con['platform'] = (string) $amaz->Items->Item->ItemAttributes->Platform;
		if (empty($con['platform']))
			$con['platform'] = $gameInfo['platform'];

		//Beginning of Recheck Code
		//This is to verify the result back from amazon was at least somewhat related to what was intended.

		//Some of the Platforms don't match Amazon's exactly. This code is needed to facilitate rechecking.
		if (preg_match('/^X360$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('X360', 'Xbox 360', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^XBOX360$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('XBOX360', 'Xbox 360', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^NDS$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('NDS', 'Nintendo DS', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^PS3$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('PS3', 'PlayStation 3', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^PSP$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('PSP', 'Sony PSP', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^Wii$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('Wii', 'Nintendo Wii', $gameInfo['platform']);    // baseline single quote
			$gameInfo['platform'] = str_replace('WII', 'Nintendo Wii', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^N64$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('N64', 'Nintendo 64', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/^NES$/i', $gameInfo['platform']))
		{
			$gameInfo['platform'] = str_replace('NES', 'Nintendo NES', $gameInfo['platform']);    // baseline single quote
		}
		if (preg_match('/Super/i', $con['platform']))
		{
			$con['platform'] = str_replace('Super Nintendo', 'SNES', $con['platform']);    // baseline single quote
			$con['platform'] = str_replace('Nintendo Super NES', 'SNES', $con['platform']);    // baseline single quote
		}
		//Remove Online Game Code So Titles Match Properly.
		if (preg_match('/\[Online Game Code\]/i', $con['title']))
		{
			$con['title'] = str_replace(' [Online Game Code]', '', $con['title']);    // baseline single quote
		}

		//Basically the XBLA names contain crap, this is to reduce the title down far enough to be usable
		if (preg_match('/xbla/i', $gameInfo['platform']))
		{
			 	$gameInfo['title'] = substr($gameInfo['title'],0,10);
				$con['substr'] = $gameInfo['title'];
		}

		//This actual compares the two strings and outputs a percentage value.
		$titlepercent ='';
		$platformpercent ='';
		similar_text(strtolower($gameInfo['title']), strtolower($con['title']), $titlepercent);
		similar_text(strtolower($gameInfo['platform']), strtolower($con['platform']), $platformpercent);

		//Since Wii Ware games and XBLA have inconsistent original platforms, as long as title is 50% its ok.
		if (preg_match('/(wiiware|xbla)/i', $gameInfo['platform']))
		{
			 if ($titlepercent >= 50)
			 {
			 	$platformpercent = 100;
			 }
		}

		//If the release is DLC matching sucks, so assume anything over 50% is legit.
		if (isset($gameInfo['dlc']) && $gameInfo['dlc'] == 1)
		{
			 if ($titlepercent >= 50)
			 {
			 	$titlepercent = 100;
			 	$platformpercent = 100;
			 }
		}

		//Show the Percentages
		//echo("Matched: Title Percentage: $titlepercent%");
		//echo("Matched: Platform Percentage: $platformpercent%");

		//If the Title is less than 80% Platform must be 100% unless it is XBLA
		if ($titlepercent < 70)
		{
			if ($platformpercent != 100)
			{
				return false;
			}
		}

		//If title is less than 80% then its most likely not a match
		if ($titlepercent < 70)
		return false;

		//Platform must equal 100%
		if ($platformpercent != 100)
			return false;

		$con['asin'] = (string) $amaz->Items->Item->ASIN;
		$con['url'] = (string) $amaz->Items->Item->DetailPageURL;

		$con['salesrank'] = (string) $amaz->Items->Item->SalesRank;
		if ($con['salesrank'] == "")
			$con['salesrank'] = 'null';

		$con['publisher'] = (string) $amaz->Items->Item->ItemAttributes->Publisher;

		$con['esrb'] = (string) $amaz->Items->Item->ItemAttributes->ESRBAgeRating;

		$con['releasedate'] = $this->pdo->escapeString((string) $amaz->Items->Item->ItemAttributes->ReleaseDate);
		if ($con['releasedate'] == "''")
			$con['releasedate'] = 'null';

		$con['review'] = "";
		if (isset($amaz->Items->Item->EditorialReviews))
			$con['review'] = trim(strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content));

		$genreKey = -1;
		$genreName = '';
		if (isset($amaz->Items->Item->BrowseNodes) || isset($amaz->Items->Item->ItemAttributes->Genre))
		{
			if (isset($amaz->Items->Item->BrowseNodes))
			{
				//had issues getting this out of the browsenodes obj
				//workaround is to get the xml and load that into its own obj
				$amazGenresXml = $amaz->Items->Item->BrowseNodes->asXml();
				$amazGenresObj = simplexml_load_string($amazGenresXml);
				$amazGenres = $amazGenresObj->xpath("//Name");
				foreach($amazGenres as $amazGenre)
				{
					$currName = trim($amazGenre[0]);
					if (empty($genreName))
					{
						$genreMatch = $this->matchBrowseNode($currName);
						if ($genreMatch !== false)
						{
							$genreName = $genreMatch;
							break;
						}
					}
				}
			}

			if (empty($genreName) && isset($amaz->Items->Item->ItemAttributes->Genre))
			{
				$tmpGenre = (string) $amaz->Items->Item->ItemAttributes->Genre;
				$tmpGenre = str_replace('-', ' ', $tmpGenre);
				$tmpGenre = explode(' ', $tmpGenre);
				foreach($tmpGenre as $tg)
				{
					$genreMatch = $this->matchBrowseNode(ucwords($tg));
					if ($genreMatch !== false)
					{
						$genreName = $genreMatch;
						break;
					}
				}
			}
		}

		if (empty($genreName))
		{
			$genreName = 'Unknown';
		}

		if (in_array(strtolower($genreName), $genreassoc)) {
			$genreKey = array_search(strtolower($genreName), $genreassoc);
		} else {
			$genreKey = $this->pdo->queryInsert(sprintf("INSERT INTO genres (`title`, `type`) VALUES (%s, %d)", $this->pdo->escapeString($genreName), Genres::CONSOLE_TYPE));
		}
		$con['consolegenre'] = $genreName;
		$con['consolegenreID'] = $genreKey;

		$query = sprintf("
		INSERT INTO consoleinfo  (`title`, `asin`, `url`, `salesrank`, `platform`, `publisher`, `genreid`, `esrb`, `releasedate`, `review`, `cover`, `createddate`, `updateddate`)
		VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, now(), now())
			ON DUPLICATE KEY UPDATE  `title` = %s,  `asin` = %s,  `url` = %s,  `salesrank` = %s,  `platform` = %s,  `publisher` = %s,  `genreid` = %s,  `esrb` = %s,  `releasedate` = %s,  `review` = %s, `cover` = %d,  createddate = now(),  updateddate = now()",
		$this->pdo->escapeString($con['title']), $this->pdo->escapeString($con['asin']), $this->pdo->escapeString($con['url']),
		$con['salesrank'], $this->pdo->escapeString($con['platform']), $this->pdo->escapeString($con['publisher']), ($con['consolegenreID']==-1?"null":$con['consolegenreID']), $this->pdo->escapeString($con['esrb']),
		$con['releasedate'], $this->pdo->escapeString($con['review']), $con['cover'],
		$this->pdo->escapeString($con['title']), $this->pdo->escapeString($con['asin']), $this->pdo->escapeString($con['url']),
		$con['salesrank'], $this->pdo->escapeString($con['platform']), $this->pdo->escapeString($con['publisher']), ($con['consolegenreID']==-1?"null":$con['consolegenreID']), $this->pdo->escapeString($con['esrb']),
		$con['releasedate'], $this->pdo->escapeString($con['review']), $con['cover'] );

		$consoleId = $this->pdo->queryInsert($query);

		if ($consoleId)
		{
			$con['cover'] = $ri->saveImage($consoleId, $con['coverurl'], $this->imgSavePath, 250, 250);
		}

		return $consoleId;
	}

	/**
	 * Retrieve properties for an item from Amazon.
	 */
	public function fetchAmazonProperties($title, $node)
	{
		$obj = new AmazonProductAPI($this->pubkey, $this->privkey, $this->asstag);
		try
		{
			$result = $obj->searchProducts($title, AmazonProductAPI::GAMES, "NODE", $node);
		}
		catch(Exception $e)
		{
			$result = false;
		}
		return $result;
	}

	/**
	 * Check all untagged console releases for their extended metadata.
	 */
	public function processConsoleReleases()
	{
		$ret = 0;
		$numlookedup = 0;

		$res = $this->pdo->queryDirect(sprintf("SELECT searchname, id from releases where consoleinfoid IS NULL and categoryid in ( select id from category where parentid = %d ) ORDER BY postdate DESC LIMIT 100", Category::CAT_PARENT_GAME));
		if ( $this->pdo->getNumRows($res) > 0)
		{
			if ($this->echooutput)
				echo "ConsPrc : Processing " . $this->pdo->getNumRows($res) . " console releases\n";

			while ($arr = $this->pdo->getAssocArray($res))
			{
				if ($numlookedup > Console::NUMTOPROCESSPERTIME)
					return;

				$gameInfo = $this->parseTitle($arr['searchname']);
				if ($gameInfo !== false)
				{

					if ($this->echooutput)
						echo 'ConsPrc : '.$gameInfo["title"].' ('.$gameInfo["platform"].')'."\n";

					//check for existing console entry
					$gameCheck = $this->getConsoleInfoByName($gameInfo["title"], $gameInfo["platform"]);

					if ($gameCheck === false)
					{
						$numlookedup++;
						$gameId = $this->updateConsoleInfo($gameInfo);
						if ($gameId === false)
						{
							$gameId = -2;
						}
					}
					else
					{
						$gameId = $gameCheck["id"];
					}

					//update release
					$this->pdo->queryExec(sprintf("update releases SET consoleinfoid = %d WHERE id = %d", $gameId, $arr["id"]));

				}
				else {
					//could not parse release title
					$this->pdo->queryExec(sprintf("update releases SET consoleinfoid = %d WHERE id = %d", -2, $arr["id"]));
				}
			}
		}
	}

	/**
	 * Strip a title from a releasename.
	 */
	function parseTitle($releasename)
	{
		$result = [];

		//get name of the game from name of release
		preg_match('/^(?P<title>.*?)[\.\-_ ](v\.?\d\.\d|PAL|NTSC|EUR|USA|JP|ASIA|JAP|JPN|AUS|MULTI\.?5|MULTI\.?4|MULTI\.?3|PATCHED|FULLDVD|DVD5|DVD9|DVDRIP|PROPER|REPACK|RETAIL|DEMO|DISTRIBUTION|REGIONFREE|READ\.?NFO|NFOFIX|PS2|PS3|PSP|WII|X\-?BOX|XBLA|X360|NDS|N64|NGC)/i', $releasename, $matches);
		if (isset($matches['title']))
		{
			$title = $matches['title'];
			//replace dots or underscores with spaces
			$result['title'] = preg_replace('/(\.|_|\%20)/', ' ', $title);
			//Needed to add code to handle DLC Properly
			if (preg_match('/dlc/i', $result['title']))
			{
				$result['dlc'] = '1';
				if (preg_match('/Rock Band Network/i', $result['title']))
				{
					$result['title'] = 'Rock Band';
				}
				else if (preg_match('/\-/i', $result['title']))
				{
					$dlc = explode("-", $result['title']);
					$result['title'] = $dlc[0];
				}
				else
				{
					preg_match('/(.*? .*?) /i', $result['title'], $dlc);
					$result['title'] = $dlc[0];
				}
			}
		}

		//get the platform of the release
		preg_match('/[\.\-_ ](?P<platform>XBLA|WiiWARE|N64|SNES|NES|PS2|PS3|PS 3|PSP|WII|XBOX360|X\-?BOX|X360|NDS|NGC)/i', $releasename, $matches);
		if (isset($matches['platform']))
		{
			$platform = $matches['platform'];
			if (preg_match('/^(XBLA)$/i', $platform))
			{
				if (preg_match('/DLC/i', $title))
				{
					$platform = str_replace('XBLA', 'XBOX360', $platform);	   // baseline single quote
				}
			}
			$browseNode = $this->getBrowseNode($platform);
			$result['platform'] = $platform;
			$result['node'] = $browseNode;
		}
		$result['release'] = $releasename;
		array_map("trim", $result);
		//make sure we got a title and platform otherwise the resulting lookup will probably be shit
		//other option is to pass the $release->categoryid here if we dont find a platform but that would require an extra lookup to determine the name
		//in either case we should have a title at the minimum
		return (isset($result['title']) && !empty($result['title']) && isset($result['platform'])) ? $result : false;
	}

	/**
	 * Translate Amazon browse nodes for console types.
	 */
	function getBrowseNode($platform)
	{
		switch($platform)
		{
			case 'PS2':
				$nodeId = '301712';
			break;
			case 'PS3':
				$nodeId = '14210751';
			break;
			case 'PSP':
				$nodeId = '11075221';
			break;
			case 'WII':
			case 'Wii':
				$nodeId = '14218901';
			break;
			case 'XBOX360':
			case 'X360':
				$nodeId = '14220161';
			break;
			case 'XBOX':
			case 'X-BOX':
				$nodeId = '537504';
			break;
			case 'NDS':
				$nodeId = '11075831';
			break;
			case 'N64':
				$nodeId = '229763';
			break;
			case 'SNES':
				$nodeId = '294945';
			break;
			case 'NES':
				$nodeId = '566458';
			break;
			case 'NGC':
				$nodeId = '541022';
			break;
			default:
				$nodeId = '468642';
			break;
		}

		return $nodeId;
	}

	/**
	 * Match an Amazon browse node with internal genre types .
	 */
	public function matchBrowseNode($nodeName)
	{
		$str = '';

		switch($nodeName)
		{
			case 'Action':
			case 'Adventure':
			case 'Arcade':
			case 'Board Games':
			case 'Cards':
			case 'Casino':
			case 'Flying':
			case 'Puzzle':
			case 'Racing':
			case 'Rhythm':
			case 'Role-Playing':
			case 'Simulation':
			case 'Sports':
			case 'Strategy':
			case 'Trivia':
				$str = $nodeName;
				break;
		}
		return ($str != '') ? $str : false;
	}
}
