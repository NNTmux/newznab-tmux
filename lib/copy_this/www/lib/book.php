<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/amazon.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/releaseimage.php");

/**
 * This class manages the lookup of book information and storage/retrieve of book metadata.
 */
class Book
{
	const NUMTOPROCESSPERTIME = 100;

	/**
	 * Default constructor.
	 */
	function Book($echooutput=false)
	{
		$this->echooutput = $echooutput;
		$s = new Sites();
		$site = $s->get();
		$this->pubkey = $site->amazonpubkey;
		$this->privkey = $site->amazonprivkey;
		$this->asstag = $site->amazonassociatetag;

		$this->imgSavePath = WWW_DIR.'covers/book/';
	}

	/**
	 * Get bookinfo row for ID.
	 */
	public function getBookInfo($id)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT bookinfo.*, genres.title as genres FROM bookinfo left outer join genres on genres.ID = bookinfo.genreID where bookinfo.ID = %d ", $id));
	}

	/**
	 * Get bookinfo row for title.
	 */
	public function getBookInfoByName($author, $title)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf("SELECT * FROM bookinfo where author like %s and title like %s", $db->escapeString("%".$author."%"),  $db->escapeString("%".$title."%")));
	}

	/**
	 * Get bookinfo rows by limit.
	 */
	public function getRange($start, $num)
	{
		$db = new DB();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		return $db->query(" SELECT * FROM bookinfo ORDER BY createddate DESC".$limit);
	}

	/**
	 * Get count of all bookinfos.
	 */
	public function getCount()
	{
		$db = new DB();
		$res = $db->queryOneRow("select count(ID) as num from bookinfo");
		return $res["num"];
	}

	/**
	 * Get count of all bookinfo rows for browse list.
	 */
	public function getBookCount($maxage=-1)
	{
		$db = new DB();

		$browseby = $this->getBrowseBy();

		if ($maxage > 0)
			$maxage = sprintf(" and r.postdate > now() - interval %d day ", $maxage);
		else
			$maxage = "";

		$sql = sprintf("select count(distinct r.bookinfoID) as num from releases r inner join bookinfo b on b.ID = r.bookinfoID and b.title != '' where r.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s", $browseby, $maxage);

		$res = $db->queryOneRow($sql, true);
		return $res["num"];
	}

	/**
	 * Get range of bookinfo rows for browse list.
	 */
	public function getBookRange($start, $num, $orderby, $maxage=-1)
	{
		$db = new DB();

		$browseby = $this->getBrowseBy();

		if ($start === false)
			$limit = "";
		else
			$limit = " LIMIT ".$start.",".$num;

		$maxagesql = "";
		if ($maxage > 0)
			$maxagesql = sprintf(" and r.postdate > now() - interval %d day ", $maxage);

		$order = $this->getBrowseOrder($orderby);
		$sql = sprintf(" SELECT r.bookinfoID, max(postdate), b.* from releases r inner join bookinfo b on b.ID = r.bookinfoID and b.title != '' where r.passwordstatus <= (select value from site where setting='showpasswordedrelease') %s %s group by r.bookinfoID order by %s %s".$limit, $browseby, $maxagesql, $order[0], $order[1]);
		$rows = $db->query($sql, true);

		//
		//get a copy of all the bookinfoids
		//
		$ids = "";
		foreach ($rows as $row)
			$ids .= $row["bookinfoID"]. ", ";

		if (strlen($ids) > 0)
		{
			$ids = substr($ids,0,-2);

			//
			// get all releases matching these ids
			//
			$sql = sprintf("select r.*, releasenfo.ID as nfoID, groups.name as grpname from releases r left outer join releasenfo on releasenfo.releaseID = r.ID left outer join groups on groups.ID = r.groupID where bookinfoID in (%s) %s order by r.postdate desc", $ids, $maxagesql);
			$allrows = $db->query($sql, true);
			$arr = array();

			//
			// build array indexed by bookinfoid
			//
			foreach ($allrows as &$allrow)
			{
				$arr[$allrow["bookinfoID"]]["ID"] = (isset($arr[$allrow["bookinfoID"]]["ID"]) ? $arr[$allrow["bookinfoID"]]["ID"] : "") . $allrow["ID"] . ",";
				$arr[$allrow["bookinfoID"]]["rarinnerfilecount"] = (isset($arr[$allrow["bookinfoID"]]["rarinnerfilecount"]) ? $arr[$allrow["bookinfoID"]]["rarinnerfilecount"] : "") . $allrow["rarinnerfilecount"] . ",";
				$arr[$allrow["bookinfoID"]]["haspreview"] = (isset($arr[$allrow["bookinfoID"]]["haspreview"]) ? $arr[$allrow["bookinfoID"]]["haspreview"] : "") . $allrow["haspreview"] . ",";
				$arr[$allrow["bookinfoID"]]["passwordstatus"] = (isset($arr[$allrow["bookinfoID"]]["passwordstatus"]) ? $arr[$allrow["bookinfoID"]]["passwordstatus"] : "") . $allrow["passwordstatus"] . ",";
				$arr[$allrow["bookinfoID"]]["guid"] = (isset($arr[$allrow["bookinfoID"]]["guid"]) ? $arr[$allrow["bookinfoID"]]["guid"] : "") . $allrow["guid"] . ",";
				$arr[$allrow["bookinfoID"]]["nfoID"] = (isset($arr[$allrow["bookinfoID"]]["nfoID"]) ? $arr[$allrow["bookinfoID"]]["nfoID"] : "") . $allrow["nfoID"] . ",";
				$arr[$allrow["bookinfoID"]]["grpname"] = (isset($arr[$allrow["bookinfoID"]]["grpname"]) ? $arr[$allrow["bookinfoID"]]["grpname"] : "") . $allrow["grpname"] . ",";
				$arr[$allrow["bookinfoID"]]["searchname"] = (isset($arr[$allrow["bookinfoID"]]["searchname"]) ? $arr[$allrow["bookinfoID"]]["searchname"] : "") . $allrow["searchname"] . "#";
				$arr[$allrow["bookinfoID"]]["postdate"] = (isset($arr[$allrow["bookinfoID"]]["postdate"]) ? $arr[$allrow["bookinfoID"]]["postdate"] : "") . $allrow["postdate"] . ",";
				$arr[$allrow["bookinfoID"]]["size"] = (isset($arr[$allrow["bookinfoID"]]["size"]) ? $arr[$allrow["bookinfoID"]]["size"] : "") . $allrow["size"] . ",";
				$arr[$allrow["bookinfoID"]]["totalpart"] = (isset($arr[$allrow["bookinfoID"]]["totalpart"]) ? $arr[$allrow["bookinfoID"]]["totalpart"] : "") . $allrow["totalpart"] . ",";
				$arr[$allrow["bookinfoID"]]["comments"] = (isset($arr[$allrow["bookinfoID"]]["comments"]) ? $arr[$allrow["bookinfoID"]]["comments"] : "") . $allrow["comments"] . ",";
				$arr[$allrow["bookinfoID"]]["grabs"] = (isset($arr[$allrow["bookinfoID"]]["grabs"]) ? $arr[$allrow["bookinfoID"]]["grabs"] : "") . $allrow["grabs"] . ",";
				$arr[$allrow["bookinfoID"]]["categoryID"] = (isset($arr[$allrow["bookinfoID"]]["categoryID"]) ? $arr[$allrow["bookinfoID"]]["categoryID"] : "") . $allrow["categoryID"] . ",";
			}

			//
			// stuff back into the results set
			//
			foreach ($rows as &$row)
			{
				$row["grp_release_id"] = substr($arr[$row["bookinfoID"]]["ID"], 0, -1);
				$row["grp_rarinnerfilecount"] = substr($arr[$row["bookinfoID"]]["rarinnerfilecount"], 0, -1);
				$row["grp_haspreview"] = substr($arr[$row["bookinfoID"]]["haspreview"], 0, -1);
				$row["grp_release_password"] = substr($arr[$row["bookinfoID"]]["passwordstatus"], 0, -1);
				$row["grp_release_guid"] = substr($arr[$row["bookinfoID"]]["guid"], 0, -1);
				$row["grp_release_nfoID"] = substr($arr[$row["bookinfoID"]]["nfoID"], 0, -1);
				$row["grp_release_grpname"] = substr($arr[$row["bookinfoID"]]["grpname"], 0, -1);
				$row["grp_release_name"] = substr($arr[$row["bookinfoID"]]["searchname"], 0, -1);
				$row["grp_release_postdate"] = substr($arr[$row["bookinfoID"]]["postdate"], 0, -1);
				$row["grp_release_size"] = substr($arr[$row["bookinfoID"]]["size"], 0, -1);
				$row["grp_release_totalparts"] = substr($arr[$row["bookinfoID"]]["totalpart"], 0, -1);
				$row["grp_release_comments"] = substr($arr[$row["bookinfoID"]]["comments"], 0, -1);
				$row["grp_release_grabs"] = substr($arr[$row["bookinfoID"]]["grabs"], 0, -1);
				$row["grp_release_categoryID"] = substr($arr[$row["bookinfoID"]]["categoryID"], 0, -1);
			}
		}
		return $rows;
	}

	/**
	 * Get orderby column for book browse list.
	 */
	public function getBrowseOrder($orderby)
	{
		$order = ($orderby == '') ? 'r.postdate' : $orderby;
		$orderArr = explode("_", $order);
		switch($orderArr[0]) {
			case 'artist':
				$orderfield = 'b.author';
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
	 * Get all orderable columns for book browse list.
	 */
	public function getBookOrdering()
	{
		return array('author_asc', 'author_desc', 'posted_asc', 'posted_desc', 'size_asc', 'size_desc', 'files_asc', 'files_desc', 'stats_asc', 'stats_desc');
	}

	/**
	 * Get filter options from browse list.
	 */
	public function getBrowseByOptions()
	{
		return array('author'=>'author', 'title'=>'title');
	}

	/**
	 * Get filter options from user into SQL query.
	 */
	public function getBrowseBy()
	{
		$db = new Db;

		$browseby = ' ';
		$browsebyArr = $this->getBrowseByOptions();
		foreach ($browsebyArr as $bbk=>$bbv) {
			if (isset($_REQUEST[$bbk]) && !empty($_REQUEST[$bbk])) {
				$bbs = stripslashes($_REQUEST[$bbk]);
				if (preg_match('/id/i', $bbv)) {
					$browseby .= " and b.{$bbv} = $bbs ";
				} else {
					$browseby .= " and b.$bbv LIKE(".$db->escapeString('%'.$bbs.'%').") ";
				}
			}
		}
		return $browseby;
	}

	/**
	 * Update bookinfo row.
	 */
	public function update($id, $title, $asin, $url, $author, $publisher, $publishdate, $cover)
	{
		$db = new DB();

		$db->queryExec(sprintf("update bookinfo SET title=%s, asin=%s, url=%s, author=%s, publisher=%s, publishdate='%s', cover=%d, updateddate=NOW() WHERE ID = %d",
		$db->escapeString($title), $db->escapeString($asin), $db->escapeString($url), $db->escapeString($author), $db->escapeString($publisher), $publishdate, $cover, $id));
	}

	/**
	 * Determine if a bookinfo can be found locally, if not query amazon, strip out it
	 * properties and update the database.
	 */
	public function updateBookInfo($author, $title)
	{
		$db = new DB();
		$ri = new ReleaseImage();

		$mus = array();
		$amaz = $this->fetchAmazonProperties($author." ".$title);
		if (!$amaz)
		{
			//echo "tried to lookup ".$author." ".$title;
			return false;
		}
		sleep(1);

		//
		// get album properties
		//
		$item = array();
		$item["asin"] = (string) $amaz->Items->Item->ASIN;
		$item["url"] = (string) $amaz->Items->Item->DetailPageURL;
		$item["coverurl"] = (string) $amaz->Items->Item->LargeImage->URL;
		if ($item['coverurl'] != "")
			$item['cover'] = 1;
		else
			$item['cover'] = 0;
		$item["author"] = (string) $amaz->Items->Item->ItemAttributes->Author;
		$item["dewey"] = (string) $amaz->Items->Item->ItemAttributes->DeweyDecimalNumber;
		$item["ean"] = (string) $amaz->Items->Item->ItemAttributes->EAN;
		$item["isbn"] = (string) $amaz->Items->Item->ItemAttributes->ISBN;
		$item["publisher"] = (string) $amaz->Items->Item->ItemAttributes->Publisher;
		$item["publishdate"] = (string) $amaz->Items->Item->ItemAttributes->PublicationDate;
		$item["pages"] = (string) $amaz->Items->Item->ItemAttributes->NumberOfPages;
		$item["title"] = (string) $amaz->Items->Item->ItemAttributes->Title;
		$item["review"] = "";
		if (isset($amaz->Items->Item->EditorialReviews))
			$item["review"] = trim(strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content));

		//This is to verify the result back from amazon was at least somewhat related to what was intended.
		//If you are debugging releases comment out the following code to show all info

		$match = similar_text($author, $item["author"], $authorpercent);
		$match = similar_text($title, $item['title'], $titlepercent);

		//If the author is less than 80% album must be 100%
		if ($authorpercent < '60')
		{
			if ($titlepercent != '100')
			{
				//echo "\nAuthor Under 80 Title Under 100 \n".$author." - ".$item['author']." - ".$authorpercent."\n";
				$temptitle = $title;
				$tempauthor = $author;
				$title = $tempauthor;
				$author = $temptitle;
				$match = similar_text($author, $item['author'], $authorpercent);
				$match = similar_text($title, $item['title'], $titlepercent);
				if ($authorpercent < '60')
				{
					if ($titlepercent != '100')
					{
						//echo "\nAuthor Under 80 Title Under 100 second check\n".$author." - ".$item['author']." - ".$authorpercent."\n";
						//echo $title." - ".$item['title']." - ".$titlepercent."\n";
						return false;
					}
				}
			}
		}

		//If the title is ever under 30%, it's probably not a match.
		if ($titlepercent < '30')
		{
			//echo "Title Under 30 ".$title." - ".$item['title']." - ".$titlepercent;
			return false;
		}

		$bookId = $this->addUpdateBookInfo($item['title'], $item['asin'], $item['url'],
								$item['author'], $item['publisher'], $item['publishdate'], $item['review'],
								$item['cover'], $item['dewey'], $item['ean'], $item['isbn'], $item['pages'] );

		if ($bookId)
		{
			$item['cover'] = $ri->saveImage($bookId, $item['coverurl'], $this->imgSavePath, 250, 250);
		}

		return $bookId;
	}

	/**
	 * Query amazon for a title.
	 */
	public function fetchAmazonProperties($title)
	{
		$obj = new AmazonProductAPI($this->pubkey, $this->privkey, $this->asstag);
		try
		{
			$result = $obj->searchProducts($title, AmazonProductAPI::BOOKS, "TITLE");
		}
		catch(Exception $e)
		{
			$result = false;
		}

		return $result;
	}

	/**
	 * Process all untagged book releases for additional metadata.
	 */
	public function processBookReleases()
	{
		$ret = 0;
		$db = new DB();
		$numlookedup = 0;

		$res = $db->queryDirect(sprintf("SELECT searchname, ID from releases where bookinfoID IS NULL and categoryID = %d ORDER BY postdate DESC LIMIT 100", Category::CAT_BOOK_EBOOK));
		if ($db->getNumRows($res) > 0)
		{
			if ($this->echooutput)
				echo "BookPrc : Processing " . $db->getNumRows($res) . " book releases\n";

			while ($arr = $db->getAssocArray($res))
			{
				if ($numlookedup > Book::NUMTOPROCESSPERTIME)
					return;

				$bookId = -2;
				$book = $this->parseAuthor($arr['searchname']);
				if ($book !== false)
				{
					if ($this->echooutput)
						echo 'BookPrc : '.$book["author"].' - '.$book["title"]."\n";

					//check for existing book entry
					$bookCheck = $this->getBookInfoByName($book["author"], $book["title"]);

					if ($bookCheck === false)
					{
						//
						// get from amazon
						//
						$numlookedup++;
						$ret = $this->updateBookInfo($book["author"], $book["title"]);
						if ($ret !== false)
						{
							$bookId = $ret;
						}
					}
					else
					{
						$bookId = $bookCheck["ID"];
					}
				}
				$db->queryExec(sprintf("update releases SET bookinfoID = %d WHERE ID = %d", $bookId, $arr["ID"]));
			}
		}
	}

	/**
	 * Strip out author and title name from a release name.
	 */
	public function parseAuthor($releasename)
	{
		$result = array();
		$result['releasename'] = $releasename;
		$newName = $releasename;

		//only process the reasonably named items
		//if (!preg_match('/epub|mobi|html|lit|prc|djvu|pdf/i', $releasename))
		//	return false;

		//remove things in brackets and double hyphens
        $newName = preg_replace("%(\([\w\s-.,]*\)|\[[\w\s-.,]*\]|[0-9])%","",$newName);
        $newName = preg_replace("%(\-\s.*\-)%","-",$newName);

		$name = explode("-", $newName);
		$name = array_map("trim", $name);


		if (is_array($name) && sizeof($name) > 1)
		{
			$result['author'] = trim($name[0]);
			$result['title'] = trim($name[1]);
			$result['title'] = preg_replace('/retail/i','',$result['title']);
            $result['title'] = preg_replace('/\.epub/i','',$result['title']);
            $result['title'] = preg_replace('/\.mobi/i','',$result['title']);
            $result['title'] = preg_replace('/\.prc/i','',$result['title']);
            $result['title'] = preg_replace('/\.lit/i','',$result['title']);
            $result['title'] = preg_replace('/\.obi/i','',$result['title']);
            $result['title'] = preg_replace('/\.azw3/i','',$result['title']);
            $result['title'] = preg_replace('/\.azw/i','',$result['title']);
            $result['title'] = preg_replace('/\.pdf/i','',$result['title']);
            $result['title'] = preg_replace('/\.html/i','',$result['title']);
			$result['title'] = preg_replace('/\./i',' ',$result['title']);
			$result['title'] = preg_replace('/\d{4}/i','',$result['title']);
			$result['title'] = preg_replace('/repost/i','',$result['title']);
			$result['title'] = preg_replace('/ebook/i','',$result['title']);
			$result['title'] = preg_replace('/\(.*?\) WW/i','',$result['title']);
			$result['title'] = preg_replace('/ WW/i','',$result['title']);
			$result['title'] = preg_replace('/\(.*?\)$/i','',$result['title']);
			$result['title'] = preg_replace('/\:.*?$/i','',$result['title']);

			//echo "author parsed - ".$result['author']."\n";
			//echo "title parsed - ".$result['title']."\n";

			// switch Cratchett, Bob to Bob Cratchett
			preg_match_all('/[,]/i', $result['author'], $matches);
			if (sizeof($matches[0]) == 1)
			{
				$pos = strpos($result['author'], ",");
				if ($pos !== false)
				{
					$firstname = substr($result['author'], $pos+1);
					$surname = substr($result['author'], 0, $pos);
					$result['author'] = trim($firstname . " " . $surname);
				}
			}
		}

		return (!empty($result['author'])  ? $result : false);
	}

	/**
	 * Insert or update a bookinfo row.
	 */
	public function addUpdateBookInfo($title, $asin, $url, $author, $publisher, $publishdate, $review, $cover, $dewey, $ean, $isbn, $pages)
	{
		$db = new DB();

		if ($pages == 0)
			$pages = "null";
		else
			$pages = $pages + 0;

        if ($publishdate == "")
            $publishdate = "null";
        elseif (strlen($publishdate) == 4)
            $publishdate = $db->escapeString($publishdate."-01-01");
        elseif (strlen($publishdate) == 7)
            $publishdate = $db->escapeString($publishdate."-01");
        else
            $publishdate = $db->escapeString($publishdate);

		$sql = sprintf("INSERT INTO bookinfo  (title, asin, url, author, publisher, publishdate, review, cover, createddate, updateddate, dewey, ean, isbn, pages)
		VALUES (%s,  %s, %s, %s, %s, %s,  %s, %d, now(), now(), %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE  title = %s,  asin = %s,  url = %s,   author = %s,  publisher = %s,  publishdate = %s,  review = %s, cover = %d,  createddate = now(),  updateddate = now(), dewey = %s, ean = %s, isbn = %s, pages = %s",
		$db->escapeString($title), $db->escapeString($asin), $db->escapeString($url),
		$db->escapeString($author), $db->escapeString($publisher),
		$publishdate, $db->escapeString($review), $cover,  $db->escapeString($dewey), $db->escapeString($ean), $db->escapeString($isbn), $pages,
		$db->escapeString($title), $db->escapeString($asin), $db->escapeString($url),
		$db->escapeString($author), $db->escapeString($publisher),
		$db->escapeString($publishdate), $db->escapeString($review), $cover,  $db->escapeString($dewey), $db->escapeString($ean), $db->escapeString($isbn), $pages );

		$bookId = $db->queryInsert($sql);
		return $bookId;
	}
}
