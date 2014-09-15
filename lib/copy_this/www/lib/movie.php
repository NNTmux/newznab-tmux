<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/TMDb.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/releaseimage.php");
require_once(WWW_DIR."/lib/rottentomato.php");

/**
 * This class looks up movie info from external sources and stores/retrieves movieinfo data.
 */
class Movie
{
	const SRC_BOXOFFICE = 1;
	const SRC_INTHEATRE = 2;
	const SRC_OPENING = 3;
	const SRC_UPCOMING = 4;
	const SRC_DVD = 5;

	/**
	 * Default constructor.
	 */
	function Movie($echooutput=false)
	{
		$this->echooutput = $echooutput;
		$s = new Sites();
		$site = $s->get();
		$this->apikey = $site->tmdbkey;
		$this->lookuplanguage = $site->lookuplanguage;

		$this->imgSavePath = WWW_DIR.'covers/movies/';
	}

	/**
	 * Get a movieinfo row by its imdbID.
	 */
	public function getMovieInfo($imdbId)
	{
		$db = new Settings();
		return $db->queryOneRow(sprintf("SELECT * FROM movieinfo where imdbID = %d", $imdbId));
	}

	/**
	 * Get movieinfo rows by array of imdbIDs.
	 */
	public function getMovieInfoMultiImdb($imdbIds)
	{
		$db = new Settings();
		$allids = implode(",", array_filter($imdbIds));
		$sql = sprintf("SELECT DISTINCT movieinfo.*, releases.imdbID AS relimdb FROM movieinfo LEFT OUTER JOIN releases ON releases.imdbID = movieinfo.imdbID WHERE movieinfo.imdbID IN (%s)", $allids);
		return $db->query($sql);
	}

	/**
	 * Delete movieinfo row by its imdbID.
	 */
	public function delete($imdbId)
	{
		$db = new Settings();
		@unlink($this->imgSavePath.$imdbId.'-cover.jpg');
		@unlink($this->imgSavePath.$imdbId.'-backdrop.jpg');
		return $db->queryOneRow(sprintf("delete FROM movieinfo where imdbID = %d", $imdbId));
	}

	/**
	 * Find an imdb URL in an nfo string.
	 */
	public function parseImdbFromNfo($str)
	{
		preg_match('/imdb.*?(tt|Title\?)(\d{7})/i', $str, $matches);
		if (isset($matches[2]) && !empty($matches[2]))
			return trim($matches[2]);
		return false;
	}

	/**
	 * Get movieinfo rows by limit.
	 */
	public function getRange($start, $num, $moviename="")
	{
		$db = new Settings();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		$rsql = '';
		if ($moviename != "")
			$rsql .= sprintf("and movieinfo.title like %s ", $db->escapeString("%".$moviename."%"));

		return $db->query(sprintf(" SELECT * FROM movieinfo where 1=1 %s ORDER BY createddate DESC".$limit, $rsql));
	}

	/**
	 * Get count of all movieinfo rows.
	 */
	public function getCount($moviename="")
	{
		$db = new Settings();

		$rsql = '';
		if ($moviename != "")
			$rsql .= sprintf("and movieinfo.title like %s ", $db->escapeString("%".$moviename."%"));

		$res = $db->queryOneRow(sprintf("select count(ID) as num from movieinfo where 1=1 %s ", $rsql));
		return $res["num"];
	}

	/**
	 * Get count of all movieinfo rows filter by category.
	 */
	public function getMovieCount($cat, $maxage=-1, $excludedcats=array())
	{
		$db = new Settings();

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
							$chlist.=", ".$child["ID"];

						if ($chlist != "-99")
								$catsrch .= " r.categoryID in (".$chlist.") or ";
					}
					else
					{
						$catsrch .= sprintf(" r.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch.= "1=2 )";
		}
		else
			$catsrch = " 1=1 ";

		if ($maxage > 0)
			$maxage = sprintf(" and r.postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and r.categoryID not in (".implode(",", $excludedcats).")";

		$sql = sprintf("select count(distinct r.imdbID) as num from releases r inner join movieinfo m on m.imdbID = r.imdbID and m.title != '' where r.passwordstatus <= (select value from site where setting='showpasswordedrelease') and %s %s %s %s ", $browseby, $catsrch, $maxage, $exccatlist);
		$res = $db->queryOneRow($sql, true);
		return $res["num"];
	}

	/**
	 * Get movieinfo rows for browse list by limit.
	 */
	public function getMovieRange($cat, $start, $num, $orderby, $maxage=-1, $excludedcats=array())
	{
		$db = new Settings();

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
							$chlist.=", ".$child["ID"];

						if ($chlist != "-99")
								$catsrch .= " r.categoryID in (".$chlist.") or ";
					}
					else
					{
						$catsrch .= sprintf(" r.categoryID = %d or ", $category);
					}
				}
			}
			$catsrch.= "1=2 )";
		}
		else
			$catsrch = " 1=1 ";

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and r.postdate > now() - interval %d day ", $maxage);

		$exccatlist = "";
		if (count($excludedcats) > 0)
			$exccatlist = " and r.categoryID not in (".implode(",", $excludedcats).")";

		$order = $this->getMovieOrder($orderby);
		$sql = sprintf(" SELECT r.imdbID, max(r.postdate) as postdate, m.* from releases r inner join movieinfo m on m.imdbID = r.imdbID where r.passwordstatus <= (select value from site where setting='showpasswordedrelease') and m.title != '' and r.imdbID != 0000000 and %s %s %s %s group by r.imdbID order by %s %s".$limit, $browseby, $catsrch, $maxagesql, $exccatlist, $order[0], $order[1]);
		$rows = $db->query($sql, true);

		//
		//get a copy of all the imdbs
		//
		$imdbds = "";
		foreach ($rows as $row)
			$imdbds .= $row["imdbID"]. ", ";

		if (strlen($imdbds) > 0)
		{
			$imdbds = substr($imdbds,0,-2);

			//
			// get all releases matching these ids
			//
			$sql = sprintf("select r.*, releasenfo.ID as nfoID, groups.name as grpname, concat(cp.title, ' > ', c.title) as categoryName from releases r left outer join category c on c.ID = r.categoryID left outer join category cp on cp.ID = c.parentID left outer join releasenfo on releasenfo.releaseID = r.ID left outer join groups on groups.ID = r.groupID where imdbID in (%s) and %s %s %s order by r.postdate desc", $imdbds, $catsrch, $maxagesql, $exccatlist);
			$allrows = $db->query($sql, true);
			$arr = array();

			//
			// build array indexed by imdbid
			//
			foreach ($allrows as &$allrow)
			{
				$arr[$allrow["imdbID"]]["ID"] = (isset($arr[$allrow["imdbID"]]["ID"]) ? $arr[$allrow["imdbID"]]["ID"] : "") . $allrow["ID"] . ",";
				$arr[$allrow["imdbID"]]["rarinnerfilecount"] = (isset($arr[$allrow["imdbID"]]["rarinnerfilecount"]) ? $arr[$allrow["imdbID"]]["rarinnerfilecount"] : "") . $allrow["rarinnerfilecount"] . ",";
				$arr[$allrow["imdbID"]]["haspreview"] = (isset($arr[$allrow["imdbID"]]["haspreview"]) ? $arr[$allrow["imdbID"]]["haspreview"] : "") . $allrow["haspreview"] . ",";
				$arr[$allrow["imdbID"]]["passwordstatus"] = (isset($arr[$allrow["imdbID"]]["passwordstatus"]) ? $arr[$allrow["imdbID"]]["passwordstatus"] : "") . $allrow["passwordstatus"] . ",";
				$arr[$allrow["imdbID"]]["guid"] = (isset($arr[$allrow["imdbID"]]["guid"]) ? $arr[$allrow["imdbID"]]["guid"] : "") . $allrow["guid"] . ",";
				$arr[$allrow["imdbID"]]["nfoID"] = (isset($arr[$allrow["imdbID"]]["nfoID"]) ? $arr[$allrow["imdbID"]]["nfoID"] : "") . $allrow["nfoID"] . ",";
				$arr[$allrow["imdbID"]]["grpname"] = (isset($arr[$allrow["imdbID"]]["grpname"]) ? $arr[$allrow["imdbID"]]["grpname"] : "") . $allrow["grpname"] . ",";
				$arr[$allrow["imdbID"]]["searchname"] = (isset($arr[$allrow["imdbID"]]["searchname"]) ? $arr[$allrow["imdbID"]]["searchname"] : "") . $allrow["searchname"] . "#";
				$arr[$allrow["imdbID"]]["postdate"] = (isset($arr[$allrow["imdbID"]]["postdate"]) ? $arr[$allrow["imdbID"]]["postdate"] : "") . $allrow["postdate"] . ",";
				$arr[$allrow["imdbID"]]["size"] = (isset($arr[$allrow["imdbID"]]["size"]) ? $arr[$allrow["imdbID"]]["size"] : "") . $allrow["size"] . ",";
				$arr[$allrow["imdbID"]]["totalpart"] = (isset($arr[$allrow["imdbID"]]["totalpart"]) ? $arr[$allrow["imdbID"]]["totalpart"] : "") . $allrow["totalpart"] . ",";
				$arr[$allrow["imdbID"]]["comments"] = (isset($arr[$allrow["imdbID"]]["comments"]) ? $arr[$allrow["imdbID"]]["comments"] : "") . $allrow["comments"] . ",";
				$arr[$allrow["imdbID"]]["grabs"] = (isset($arr[$allrow["imdbID"]]["grabs"]) ? $arr[$allrow["imdbID"]]["grabs"] : "") . $allrow["grabs"] . ",";
				$arr[$allrow["imdbID"]]["categoryID"] = (isset($arr[$allrow["imdbID"]]["categoryID"]) ? $arr[$allrow["imdbID"]]["categoryID"] : "") . $allrow["categoryID"] . ",";
				$arr[$allrow["imdbID"]]["categoryName"] = (isset($arr[$allrow["imdbID"]]["categoryName"]) ? $arr[$allrow["imdbID"]]["categoryName"] : "") . $allrow["categoryName"] . ",";
			}

			//
			// stuff back into the results set
			//
			foreach ($rows as &$row)
			{
				$row["grp_release_id"] = substr($arr[$row["imdbID"]]["ID"], 0, -1);
				$row["grp_rarinnerfilecount"] = substr($arr[$row["imdbID"]]["rarinnerfilecount"], 0, -1);
				$row["grp_haspreview"] = substr($arr[$row["imdbID"]]["haspreview"], 0, -1);
				$row["grp_release_password"] = substr($arr[$row["imdbID"]]["passwordstatus"], 0, -1);
				$row["grp_release_guid"] = substr($arr[$row["imdbID"]]["guid"], 0, -1);
				$row["grp_release_nfoID"] = substr($arr[$row["imdbID"]]["nfoID"], 0, -1);
				$row["grp_release_grpname"] = substr($arr[$row["imdbID"]]["grpname"], 0, -1);
				$row["grp_release_name"] = substr($arr[$row["imdbID"]]["searchname"], 0, -1);
				$row["grp_release_postdate"] = substr($arr[$row["imdbID"]]["postdate"], 0, -1);
				$row["grp_release_size"] = substr($arr[$row["imdbID"]]["size"], 0, -1);
				$row["grp_release_totalparts"] = substr($arr[$row["imdbID"]]["totalpart"], 0, -1);
				$row["grp_release_comments"] = substr($arr[$row["imdbID"]]["comments"], 0, -1);
				$row["grp_release_grabs"] = substr($arr[$row["imdbID"]]["grabs"], 0, -1);
				$row["grp_release_categoryID"] = substr($arr[$row["imdbID"]]["categoryID"], 0, -1);
				$row["grp_release_categoryName"] = substr($arr[$row["imdbID"]]["categoryName"], 0, -1);
			}
		}
		return $rows;

	}

	/**
	 * Get movieinfo orderby column sql.
	 */
	public function getMovieOrder($orderby)
	{
		$order = ($orderby == '') ? 'max(r.postdate)' : $orderby;
		$orderArr = explode("_", $order);
		switch($orderArr[0]) {
			case 'title':
				$orderfield = 'm.title';
			break;
			case 'year':
				$orderfield = 'm.year';
			break;
			case 'rating':
				$orderfield = 'm.rating';
			break;
			case 'posted':
			default:
				$orderfield = 'max(r.postdate)';
			break;
		}
		$ordersort = (isset($orderArr[1]) && preg_match('/^asc|desc$/i', $orderArr[1])) ? $orderArr[1] : 'desc';
		return array($orderfield, $ordersort);
	}

	/**
	 * Get movieinfo orderby columns.
	 */
	public function getMovieOrdering()
	{
		return array('title_asc', 'title_desc', 'year_asc', 'year_desc', 'rating_asc', 'rating_desc');
	}

	/**
	 * Get movieinfo filter columns.
	 */
	public function getBrowseByOptions()
	{
		return array('title', 'director', 'actors', 'genre', 'rating', 'year', 'imdb');
	}

	/**
	 * Get movieinfo sql column for users selected filter.
	 */
	public function getBrowseBy()
	{
		$db = new Db;

		$browseby = ' ';
		$browsebyArr = $this->getBrowseByOptions();
		foreach ($browsebyArr as $bb) {
			if (isset($_REQUEST[$bb]) && !empty($_REQUEST[$bb])) {
				$bbv = stripslashes($_REQUEST[$bb]);
				if ($bb == 'rating') { $bbv .= '.'; }
				if ($bb == 'imdb') {
					$browseby .= "m.{$bb}ID = $bbv AND ";
				} else {
					$browseby .= "m.$bb LIKE(".$db->escapeString('%'.$bbv.'%').") AND ";
				}
			}
		}
		return $browseby;
	}

	/**
	 * Create links for data like actor/director so used can requery.
	 */
	public function makeFieldLinks($data, $field)
	{
		if ($data[$field] == "")
			return "";

		$tmpArr = explode(', ',$data[$field]);
		$newArr = array();
		$i = 0;
		foreach($tmpArr as $ta) {
			if ($i > 5) { break; } //only use first 6
			$newArr[] = '<a href="'.WWW_TOP.'/movies?'.$field.'='.urlencode($ta).'" title="'.$ta.'">'.$ta.'</a>';
			$i++;
		}
		return implode(', ', $newArr);
	}

	/**
	 * Update movieinfo row.
	 */
	public function update($id, $title, $tagline, $plot, $year, $rating, $genre, $director, $actors, $language, $cover, $backdrop)
	{
		$db = new Settings();

		$db->queryExec(sprintf("update movieinfo SET title=%s, tagline=%s, plot=%s, year=%s, rating=%s, genre=%s, director=%s, actors=%s, language=%s, cover=%d, backdrop=%d, updateddate=NOW() WHERE imdbID = %d",
			$db->escapeString($title), $db->escapeString($tagline), $db->escapeString($plot), $db->escapeString($year), $db->escapeString($rating), $db->escapeString($genre), $db->escapeString($director), $db->escapeString($actors), $db->escapeString($language), $cover, $backdrop, $id));
	}

	/**
	 * Update movieinfo row by querying external sources and updating known properties/images.
	 */
	public function updateMovieInfo($imdbId)
	{

		if ($imdbId + 0 == 0)
		{
			return;
		}

		$ri = new ReleaseImage();

		//check themoviedb for imdb info
		$tmdb = $this->fetchTmdbProperties($imdbId);

		//check imdb for movie info
		$imdb = $this->fetchImdbProperties($imdbId);

		if (!$imdb && !$tmdb) {
			return false;
		}

		$mov = array();
		$mov['imdb_id'] = $imdbId;
		$mov['tmdb_id'] = (!isset($tmdb['tmdb_id']) || $tmdb['tmdb_id'] == '') ? "NULL" : $tmdb['tmdb_id'];

		//prefer tmdb cover over imdb cover
		$mov['cover'] = 0;
		if (isset($tmdb['cover']) && $tmdb['cover'] != '') {
			$mov['cover'] = $ri->saveImage($imdbId.'-cover', $tmdb['cover'], $this->imgSavePath);
		} elseif (isset($imdb['cover']) && $imdb['cover'] != '') {
			$mov['cover'] = $ri->saveImage($imdbId.'-cover', $imdb['cover'], $this->imgSavePath);
		}

		$mov['backdrop'] = 0;
		if (isset($tmdb['backdrop']) && $tmdb['backdrop'] != '') {
			$mov['backdrop'] = $ri->saveImage($imdbId.'-backdrop', $tmdb['backdrop'], $this->imgSavePath, 1024, 768);
		}

		$mov['title'] = '';
		if (isset($tmdb['title']) && $tmdb['title'] != '') {
			$mov['title'] = $tmdb['title'];
		} elseif (isset($imdb['title']) && $imdb['title'] != '') {
			$mov['title'] = $imdb['title'];
		}
		$mov['title'] = html_entity_decode($mov['title'], ENT_QUOTES, 'UTF-8');

        $mov['trailer'] = '';
        if (isset($tmdb['trailer']) && $tmdb['trailer'] != '') {
            $mov['trailer'] = $tmdb['trailer'];
        }

        $mov['rating'] = '';
		if (isset($imdb['rating']) && $imdb['rating'] != '') {
			$mov['rating'] = $imdb['rating'];
		} elseif (isset($tmdb['rating']) && $tmdb['rating'] != '') {
			$mov['rating'] = $tmdb['rating'];
		}
		$mov['rating'] = str_replace(',','.',$mov['rating']);

		$mov['tagline'] = '';
		if (isset($tmdb['tagline']) && $tmdb['tagline'] != '') {
			$mov['tagline'] = $tmdb['tagline'];
		} elseif (isset($imdb['tagline']) && $imdb['tagline'] != '') {
			$mov['tagline'] = $imdb['tagline'];
		}
		$mov['tagline'] = html_entity_decode($mov['tagline'], ENT_QUOTES, 'UTF-8');

		$mov['plot'] = '';
		if (isset($tmdb['plot']) && $tmdb['plot'] != '') {
			$mov['plot'] = $tmdb['plot'];
		} elseif (isset($imdb['plot']) && $imdb['plot'] != '') {
			$mov['plot'] = $imdb['plot'];
		}
		$mov['plot'] = html_entity_decode($mov['plot'], ENT_QUOTES, 'UTF-8');

		$mov['year'] = '';
		if (isset($imdb['year']) && $imdb['year'] != '') {
			$mov['year'] = $imdb['year'];
		} elseif (isset($tmdb['year']) && $tmdb['year'] != '') {
			$mov['year'] = $tmdb['year'];
		}

		$mov['genre'] = '';
		if (isset($tmdb['genre']) && $tmdb['genre'] != '') {
			$mov['genre'] = $tmdb['genre'];
		} elseif (isset($imdb['genre']) && $imdb['genre'] != '') {
			$mov['genre'] = $imdb['genre'];
		}
		if (is_array($mov['genre'])) {
			$mov['genre'] = implode(', ', array_unique($mov['genre']));
		}
		$mov['genre'] = html_entity_decode($mov['genre'], ENT_QUOTES, 'UTF-8');

		$mov['director'] = '';
		if (isset($imdb['director']) && $imdb['director'] != '') {
			$mov['director'] = (is_array($imdb['director'])) ? implode(', ', array_unique($imdb['director'])) : $imdb['director'];
		}
		$mov['director'] = html_entity_decode($mov['director'], ENT_QUOTES, 'UTF-8');

		$mov['actors'] = '';
		if (isset($imdb['actors']) && $imdb['actors'] != '') {
			$mov['actors'] = (is_array($imdb['actors'])) ? implode(', ', array_unique($imdb['actors'])) : $imdb['actors'];
		}
		$mov['actors'] = html_entity_decode($mov['actors'], ENT_QUOTES, 'UTF-8');

		$mov['language'] = '';
		if (isset($imdb['language']) && $imdb['language'] != '') {
			$mov['language'] = (is_array($imdb['language'])) ? implode(', ', array_unique($imdb['language'])) : $imdb['language'];
		}
		$mov['language'] = html_entity_decode($mov['language'], ENT_QUOTES, 'UTF-8');

		$db = new Settings();
		$query = sprintf("
			INSERT INTO movieinfo
				(imdbID, tmdbID, title, rating, tagline, trailer, plot, year, genre, director, actors, language, cover, backdrop, createddate, updateddate)
			VALUES
				(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				imdbID=%d, tmdbID=%s, title=%s, rating=%s, tagline=%s, trailer=%s, plot=%s, year=%s, genre=%s, director=%s, actors=%s, language=%s, cover=%d, backdrop=%d, updateddate=NOW()",
		$mov['imdb_id'], $mov['tmdb_id'], $db->escapeString($mov['title']), $db->escapeString($mov['rating']), $db->escapeString($mov['tagline']), $db->escapeString($mov['trailer']), $db->escapeString($mov['plot']), $db->escapeString($mov['year']), $db->escapeString($mov['genre']), $db->escapeString($mov['director']), $db->escapeString($mov['actors']), $db->escapeString($mov['language']), $mov['cover'], $mov['backdrop'],
		$mov['imdb_id'], $mov['tmdb_id'], $db->escapeString($mov['title']), $db->escapeString($mov['rating']), $db->escapeString($mov['tagline']), $db->escapeString($mov['trailer']), $db->escapeString($mov['plot']), $db->escapeString($mov['year']), $db->escapeString($mov['genre']), $db->escapeString($mov['director']), $db->escapeString($mov['actors']), $db->escapeString($mov['language']), $mov['cover'], $mov['backdrop']);

		$movieId = $db->queryInsert($query);

		return $movieId;
	}

	/**
	 * Lookup a movie on tmdb by ID
	 */
	public function fetchTmdbProperties($id, $isImdbId=true)
	{
		$tmdb = new TMDb($this->apikey, $this->lookuplanguage);
		$lookupId = ($isImdbId) ? 'tt'.$id : $id;

		try {
			$movie = $tmdb->getMovie($lookupId);
		} catch (Exception $e) {
			return false;
		}
		if (!$movie || !is_array($movie)) { return false; }
		if (isset($movie['status_code']) && $movie['status_code'] > 1) { return false; }

		$ret = array();
		$ret['title'] = $movie['title'];
		$ret['tmdb_id'] = $movie['id'];
		$ret['imdb_id'] = str_replace('tt', '', $movie['imdb_id']);
		$ret['rating'] = ($movie['vote_average'] == 0) ? '' : $movie['vote_average'];
		$ret['plot'] = $movie['overview'];
		if (isset($movie['tagline']))
			$ret['tagline'] = $movie['tagline'];
		if (isset($movie['release_date']))
			$ret['year'] = date("Y", strtotime($movie['release_date']));
		if (isset($movie['genres']) && sizeof($movie['genres']) > 0)
		{
			$genres = array();
			foreach($movie['genres'] as $genre)
			{
				$genres[] = $genre['name'];
			}
			$ret['genre'] = $genres;
		}
        if (isset($movie['trailers']) && isset($movie['trailers']['youtube']) && sizeof($movie['trailers']['youtube']) > 0)
        {
            foreach($movie['trailers']['youtube'] as $trailer)
            {
                $ret['trailer'] = $trailer['source'];
                break;
            }
        }

        if (isset($movie['poster_path']))
            $ret['cover'] = $tmdb->getImageUrl($movie['poster_path'], TMDb::IMAGE_POSTER, "w185");

        if (isset($movie['backdrop_path']))
            $ret['backdrop'] = $tmdb->getImageUrl($movie['backdrop_path'], TMDb::IMAGE_BACKDROP, "w300");

		return $ret;
	}

	/**
	 * Search for a movie on tmdb by querystring.
	 */
	public function searchTmdb($search, $limit=10)
	{
		$tmdb = new TMDb($this->apikey, $this->lookuplanguage);
		try {
			$movies = $tmdb->searchMovie($search);
		} catch (Exception $e) {
			return false;
		}
		if (!$movies || !isset($movies['results'])) { return false; }

		$ret = array();
		$c=0;
		foreach($movies['results'] as $movie)
		{
			$c++;
			if ($c >= $limit)
				continue;

			$m = $this->fetchTmdbProperties($movie['id'], false);
			if ($m !== false)
				$ret[] = $m;
		}
		return $ret;
	}

	/**
	 * Scrape a movie from imdb.
	 */
    public function fetchImdbProperties($imdbId)
    {
        $imdb_regex = array(
            'title'    => '/<meta property=["\']og:title["\'] content="(.*?) \(/iS',
			'tagline'  => '/taglines:<\/h4>\s([^<]+)/iS',
            'plot'     => '/<meta name="description" content="(.*?)"/iS',
			'rating'   => '/<span.*?ratingValue">([0-9]{1,2}[\.,][0-9]{1,2})<\/span>/iS',
			'year'     => '/<title>.*?\(.*?(\d{4}).*?<\/title>/iS',
			'cover'    => '/<a.*?href="\/media\/.*?>\s*<img[^>]*?src="(.*?)"/iS'
        );

        $imdb_regex_multi = array(
        	'genre'    => '/<span.*?itemprop=\"genre\">(.*?)<\/span>/iS',
			'language' => '/<a.*?href="\/language\/.* itemprop=["\']url["\'].*?>(.*?)<\/a>/iS'
        );

		$buffer = getUrl("http://www.imdb.com/title/tt$imdbId/", 'get', '', '', '', '', '', $this->lookuplanguage);

        // make sure we got some data
        if ($buffer !== false && strlen($buffer))
        {
        	$ret = array();
            foreach ($imdb_regex as $field => $regex)
            {
                if (preg_match($regex, $buffer, $matches))
                {
                    $match = $matches[1];
                    $match = strip_tags(trim(rtrim($match)));
                    $ret[$field] = $match;
                    //echo 'Found '.$field.' : '.$ret[$field]."\n";

                }
            }

            foreach ($imdb_regex_multi as $field => $regex)
            {
                if (preg_match_all($regex, $buffer, $matches))
                {
                    $match = $matches[1];
                    $match = array_map("trim", $match);
                    $ret[$field] = $match;
                    //echo 'Found '.$field.' : '.implode(" | ", $ret[$field])."\n";
                }
            }


	        //directors
	        if (preg_match('/<div.*?itemprop="director".*?>(.*?)<\/div>/is', $buffer, $hit))
			{
				if (preg_match_all('/<span.*?itemprop="name".*?>(.*?)<\/span>/is', $hit[1], $results, PREG_PATTERN_ORDER))
				{
					$ret['director'] = $results[1];
				}
			}


	        //actors
	        if (preg_match('/<h4.*?Stars?:<\/h4>(.*?)<\/div>/is', $buffer, $hit))
	        {
				if (preg_match_all('/<span.*?itemprop=\"name\".*?>(.*?)<\/span>/is', $hit[1], $results, PREG_PATTERN_ORDER))
				{
					$ret['actors'] = $results[1];
				}
	        }

	        return $ret;
	    }

	    return false;
	}
	/**
	 * Process all untagged movies to link them to a movieinfo row.
	 */
    public function processMovieReleases()
	{
		$ret = 0;
		$db = new Settings();
		$nfo = new Nfo();

		$res = $db->queryDirect(sprintf("SELECT searchname, ID from releases where imdbID IS NULL and categoryID in ( select ID from category where parentID = %d ) ORDER BY postdate DESC LIMIT 100", Category::CAT_PARENT_MOVIE));
		if ($db->getNumRows($res) > 0)
		{
			if ($this->echooutput)
				echo "MovProc : Processing " . $db->getNumRows($res) . " movie releases\n";

			while ($arr = $db->getAssocArray($res))
			{
				$imdbID = false;
				/* Preliminary IMDB ID Detection from NFO file */
				$rawnfo = '';
				if($nfo->getNfo($arr['ID'], $rawnfo))
					$imdbID = $this->parseImdbFromNfo($rawnfo);

				if($imdbID !== false){
					// Set IMDB (if found in nfo) and move along
					$db->queryExec(sprintf("update releases set imdbID = %s where ID = %d",  $db->escapeString($imdbID), $arr["ID"]));
					//check for existing movie entry
					$movCheck = $this->getMovieInfo($imdbID);
					if ($movCheck === false || (isset($movCheck['updateddate']) && (time() - strtotime($movCheck['updateddate'])) > 2592000))
					{
						$movieId = $this->updateMovieInfo($imdbID);
					}
					continue;
				}

				$moviename = $this->parseMovieName($arr['searchname']);
				if ($moviename !== false)
				{
					if ($this->echooutput)
						echo 'MovProc : '.$moviename.' ['.$arr['searchname'].']'."\n";

					//$buffer = getUrl("https://www.google.com/search?source=ig&hl=en&rlz=&btnG=Google+Search&aq=f&oq=&q=".urlencode($moviename.' site:imdb.com'));
                    $buffer = getUrl("http://www.bing.com/search?&q=".urlencode($moviename.' site:imdb.com'));

			        // make sure we got some data
			        if ($buffer !== false && strlen($buffer))
			        {
						$imdbId = $this->parseImdbFromNfo($buffer);
						if ($imdbId !== false)
						{
							//update release with imdb id
							$db->queryExec(sprintf("update releases SET imdbID = %s WHERE ID = %d", $db->escapeString($imdbId), $arr["ID"]));

							//check for existing movie entry
							$movCheck = $this->getMovieInfo($imdbId);
							if ($movCheck === false || (isset($movCheck['updateddate']) && (time() - strtotime($movCheck['updateddate'])) > 2592000))
							{
								$movieId = $this->updateMovieInfo($imdbId);
							}

						} else {
							//no imdb id found, set to all zeros so we dont process again
							$db->queryExec(sprintf("update releases SET imdbID = %d WHERE ID = %d", 0, $arr["ID"]));
						}

					} else {
						//url fetch failed, will try next run
					}


				} else {
					//no valid movie name found, set to all zeros so we dont process again
					$db->queryExec(sprintf("update releases SET imdbID = %d WHERE ID = %d", 0, $arr["ID"]));
				}
			}
		}
	}

	/**
	 * Strip a movie name from a release.
	 */
	public function parseMovieName($releasename)
	{
		$cat = new Category;
		if (!$cat->isMovieForeign($releasename))
		{
			preg_match('/^(?P<name>.*)[\.\-_\( ](?P<year>19\d{2}|20\d{2})/i', $releasename, $matches);
			if (!isset($matches['year']))
				preg_match('/^(?P<name>.*)[\.\-_ ](?:dvdrip|bdrip|brrip|bluray|hdtv|divx|xvid|proper|repack|real\.proper|sub\.?fix|sub\.?pack|ac3d|unrated|1080i|1080p|720p|810p)/i', $releasename, $matches);

			if (isset($matches['name']))
			{
				$name = preg_replace('/\(.*?\)|\.|_/i', ' ', $matches['name']);
				$year = (isset($matches['year'])) ? ' ('.$matches['year'].')' : '';
				return trim($name).$year;
			}
		}
		return false;
	}

	/**
	 * Get rows from upcoming table by type.
	 */
	public function getUpcoming($type, $source="rottentomato")
	{
		$db = new Settings();
		$sql = sprintf("select * from upcoming where source = %s and typeid = %d", $db->escapeString($source), $type);
		return $db->queryOneRow($sql);
	}

	/**
	 * Retrieve upcoming movie data from rottentomatoes API.
	 */
	public function updateUpcoming()
	{
		$s = new Sites();
		$site = $s->get();
		if (isset($site->rottentomatokey))
		{
			$rt = new RottenTomato($site->rottentomatokey);

			$ret = $rt->getBoxOffice();
			if ($ret != "")
				$this->updateInsUpcoming('rottentomato', Movie::SRC_BOXOFFICE, $ret);

			$ret = $rt->getInTheaters();
			if ($ret != "")
				$this->updateInsUpcoming('rottentomato', Movie::SRC_INTHEATRE, $ret);

			$ret = $rt->getOpening();
			if ($ret != "")
				$this->updateInsUpcoming('rottentomato', Movie::SRC_OPENING, $ret);

			$ret = $rt->getUpcoming();
			if ($ret != "")
				$this->updateInsUpcoming('rottentomato', Movie::SRC_UPCOMING, $ret);

			$ret = $rt->getDVDReleases();
			if ($ret != "")
				$this->updateInsUpcoming('rottentomato', Movie::SRC_DVD, $ret);
		}
	}

	/**
	 * Add/Update upcoming row.
	 */
	public function updateInsUpcoming($source, $type, $info)
	{
		$db = new Settings();

		$sql = sprintf("INSERT into upcoming (source,typeID,info,updateddate) VALUES (%s, %d, %s, now()) ON DUPLICATE KEY UPDATE info = %s",
						$db->escapeString($source), $type, $db->escapeString($info), $db->escapeString($info));
		$db->queryInsert($sql);
	}

	/**
	 * Get list of standard movie genres.
	 */
	public function getGenres()
	{
		return array(
			'Action',
			'Adventure',
			'Animation',
			'Biography',
			'Comedy',
			'Crime',
			'Documentary',
			'Drama',
			'Family',
			'Fantasy',
			'Film-Noir',
			'Game-Show',
			'History',
			'Horror',
			'Music',
			'Musical',
			'Mystery',
			'News',
			'Reality-TV',
			'Romance',
			'Sci-Fi',
			'Sport',
			'Talk-Show',
			'Thriller',
			'War',
			'Western'
		);
	}
}
