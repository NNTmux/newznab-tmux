<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/framework/db.php");
require_once(WWW_DIR."lib/category.php");
require_once(WWW_DIR."lib/groups.php");
require_once(WWW_DIR."lib/nfo.php");
require_once(WWW_DIR."lib/site.php");
require_once("functions.php");
require_once("consoletools.php");

/*
 * Class for inserting names/categories/md5 etc from predb sources into the DB, also for matching names on files / subjects.
 */
 // This script is adapted from nZEDb
Class Predb
{
	function Predb($echooutput=false)
	{
		$s = new Sites();
		$this->site = $s->get();
		$this->echooutput = $echooutput;
	}

	// Retrieve pre info from predb sources and store them in the DB.
	// Returns the quantity of new titles retrieved.
	public function combinePre()
	{
		$db = new DB();
		$newnames = 0;
		$newestrel = $db->queryOneRow("SELECT adddate, ID FROM prehash ORDER BY adddate DESC LIMIT 1");
		if (strtotime($newestrel["adddate"]) < time()-900 || is_null($newestrel['adddate']))
		{
			if ($this->echooutput)
				echo "Retrieving titles from preDB sources.\n";
			$newwomble = $this->retrieveWomble();
			if ($this->echooutput)
				echo $newwomble." Retrieved from Womble.\n";
			$newomgwtf = $this->retrieveOmgwtfnzbs();
			if ($this->echooutput)
				echo $newomgwtf." Retrieved from Omgwtfnzbs.\n";
			$newzenet = $this->retrieveZenet();
			if ($this->echooutput)
				echo $newzenet." Retrieved from Zenet.\n";
			$newprelist = $this->retrievePrelist();
			if ($this->echooutput)
				echo $newprelist." Retrieved from Prelist.\n";
			$neworly = $this->retrieveOrlydb();
			if ($this->echooutput)
				echo $neworly." Retrieved from Orlydb.\n";
			$newsrr = $this->retrieveSrr();
			if ($this->echooutput)
				echo $newsrr." Retrieved from Srrdb.\n";
			$newpdme = $this->retrievePredbme();
			if ($this->echooutput)
				echo $newpdme." Retrieved from Predb.me.\n";
			$newnames = $newwomble+$newomgwtf+$newzenet+$newprelist+$neworly+$newsrr+$newpdme;
			if(count($newnames) > 0)
				$db->query(sprintf("UPDATE prehash SET adddate = now() where ID = %d", $newestrel["ID"]));
		}
		$matched = $this->matchPredb();
		if ($matched > 0 && $this->echooutput)
			echo "\nMatched ".$matched." prehash titles to release search names.\n";
		$nfos = $this->matchNfo();
		if ($nfos > 0 && $this->echooutput)
			echo "\nAdded ".$nfos." missing NFOs from prehash sources.\n";
		return $newnames;
	}

	public function retrieveWomble()
	{
		$db = new DB();
		$newnames = $updated = 0;

		$buffer = getUrl("http://www.newshost.co.za");
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr bgcolor=#[df]{6}>.+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<tr bgcolor=#[df]{6}>.+?<td>(?P<date>.+?)<\/td>(.+?right>(?P<size1>.+?)&nbsp;(?P<size2>.+?)<\/td.+?)?<td>(?P<category>.+?)<\/td.+?<a href=.+?(<a href="(?P<nfo>.+?)">nfo<\/a>.+)?<td>(?P<title>.+?)<\/td.+tr>/s', $m, $matches2))
						{
						    $md5 = md5($matches2["title"]);
							$oldname = $db->queryOneRow(sprintf("SELECT title, source, ID, nfo FROM prehash WHERE title = %s", $db->escapeString($md5)));
							if ($oldname !== false && $oldname["md5"] == $md5)
							{
								if ($oldname["nfo"] != NULL)
									continue;
								else
								{
									if (!isset($matches2["size1"]) && empty($matches2["size1"]))
										$size = "NULL";
									else
										$size = $db->escapeString($matches2["size1"].$matches2["size2"]);

									if ($matches2["nfo"] == "")
										$nfo = "NULL";
									else
										$nfo = $db->escapeString("http://nzb.isasecret.com/".$matches2["nfo"]);

									$db->query(sprintf("UPDATE prehash SET nfo = %s, size = %s, category = %s, predate = %s, adddate = now(), source = %s where ID = %d", $nfo, $size, $db->escapeString($matches2["category"]), $db->from_unixtime(strtotime($matches2["date"])), $db->escapeString("womble"), $oldname["ID"]));
								}
							}
							else
							{
								if (!isset($matches2["size1"]) && empty($matches2["size1"]))
									$size = "NULL";
								else
									$size = $db->escapeString($matches2["size1"].$matches2["size2"]);

								if ($matches2["nfo"] == "")
									$nfo = "NULL";
								else
									$nfo = $db->escapeString("http://nzb.isasecret.com/".$matches2["nfo"]);

								$db->query(sprintf("INSERT IGNORE INTO prehash (title, nfo, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $nfo, $size, $db->escapeString($matches2["category"]), $db->escapeString("womble"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;
	}

	public function retrieveOmgwtfnzbs()
	{
		$db = new DB();
		$newnames = $updated = 0;

		$buffer = getUrl("http://rss.omgwtfnzbs.org/rss-info.php");
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<item>.+?<\/item>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<title>(?P<title>.+?)<\/title.+?pubDate>(?P<date>.+?)<\/pubDate.+?gory:<\/b> (?P<category>.+?)<br \/.+?<\/b> (?P<size1>.+?) (?P<size2>[a-zA-Z]+)<b/s', $m, $matches2))
						{
						    $md5 = md5($matches2["title"]);
							$oldname = $db->queryOneRow(sprintf("SELECT title, source, ID FROM prehash WHERE title = %s", $db->escapeString($md5)));
							if ($oldname !== false && $oldname["md5"] == $md5)
							{
								if ($oldname["source"] == "womble")
								{
									continue;
								}
								else
								{
									$size = $db->escapeString(round($matches2["size1"]).$matches2["size2"]);
									$db->query(sprintf("UPDATE prehash SET size = %s, category = %s, predate = FROM_UNIXTIME(".strtotime($matches2["date"])."), adddate = now(), source = %s where ID = %d", $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $oldname["ID"]));
									$newnames++;
								}
							}
							else
							{
								$size = $db->escapeString(round($matches2["size1"]).$matches2["size2"]);
								$title = preg_replace("/  - omgwtfnzbs.org/", "", $matches2["title"]);
								$db->query(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($title), $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $db->escapeString($md5)));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;
	}

	public function retrieveZenet()
	{
		$db = new DB();
		$newnames = $updated = 0;

		$buffer = getUrl("http://pre.zenet.org/live.php");
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr bgcolor=".+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<tr bgcolor=".+?<td.+?">(?P<date>.+?)<\/td.+?<td.+?(<font.+?">(?P<category>.+?)<\/a.+?|">(?P<category1>NUKE)+?)?<\/td.+?<td.+?">(?P<title>.+?-)<a.+?<b>(?P<title2>.+?)<\/b>.+?<\/td.+?<td.+<td.+?(">(?P<size1>[\d.]+)<b>(?P<size2>.+?)<\/b>.+)?<\/tr>/s', $m, $matches2))
						{
                            $md5 = md5($matches2["title"].$matches2["title2"]);
                            $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
							if ($oldname !== false && $oldname["md5"] == $md5)
								continue;
							else
							{
								if (!isset($matches2["size1"]) && empty($matches2["size1"]))
									$size = "NULL";
								else
									$size = $db->escapeString(round($matches2["size1"]).$matches2["size2"]);

								if (isset($matches2["category"]) && !empty($matches2["category"]))
									$category = $db->escapeString($matches2["category"]);
								else if (isset($matches2["category1"]) && !empty($matches2["category1"]))
									$category = $db->escapeString($matches2["category1"]);
								else
									$category = "NULL";

								$db->query(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"].$matches2["title2"]), $size, $category, $db->escapeString("zenet"), $db->escapeString($md5)));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;
	}

	public function retrievePrelist()
	{
		$db = new DB();
		$newnames = $updated = 0;

		$buffer = getUrl("http://www.prelist.ws/");
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<small><span.+?<\/span><\/small>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (!preg_match('/NUKED/', $m) && preg_match('/">\[ (?P<date>.+?) U.+?">(?P<category>.+?)<\/a>.+?">(?P<title>.+?)<\/a>.+?(b>\[ (?P<size>.+?) \]<\/b)?/si', $m, $matches2))
						{
                            $md5 = md5($matches2["title"]);
                            $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
							if ($oldname !== false && $oldname["md5"] == $md5)
								continue;
							else
							{
								if (!isset($matches2["size"]) && empty($matches2["size"]))
									$size = "NULL";
								else
									$size = $db->escapeString(round($matches2["size"]));

								$db->query(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("prelist"), $db->escapeString($md5)));
								$newnames++;
							}
						}
						else if (preg_match('/">\[ (?P<date>.+?) U.+?">(?P<category>.+?)<\/a>.+?">(?P<category1>.+?)<\/a.+">(?P<title>.+?)<\/a>/si', $m, $matches2))
						{
                            $md5 = md5($matches2["title"]);
                            $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
							if ($oldname !== false && $oldname["md5"] == $md5)
								continue;
							else
							{
								$category = $db->escapeString($matches2["category"].", ".$matches2["category1"]);

								$db->query(sprintf("INSERT IGNORE INTO prehash (title, category, predate, adddate, source, hash) VALUES (%s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $category, $db->escapeString("prelist"), $db->escapeString($md5)));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;
	}

	public function retrieveOrlydb()
	{
		$db = new DB();
		$newnames = $updated = 0;

		$buffer = getUrl("http://www.orlydb.com/");
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match('/<div id="releases">(.+)<div id="pager">/s', $buffer, $match))
			{
				if (preg_match_all('/<div>.+?<\/div>/s', $match["1"], $matches))
				{
					foreach ($matches as $m1)
					{
						foreach ($m1 as $m)
						{
							if (preg_match('/timestamp">(?P<date>.+?)<\/span>.+?section">.+?">(?P<category>.+?)<\/a>.+?release">(?P<title>.+?)<\/span>(.+info">(?P<size>.+?) )?/s', $m, $matches2))
							{
                                $md5 = md5($matches2["title"]);
                                $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
								if ($oldname !== false && $oldname["md5"] == $md5)
									continue;
								else
								{
									if (!isset($matches2["size"]) && empty($matches2["size"]))
										$size = "NULL";
									else
										$size = $db->escapeString($matches2["size"]);

									$db->query(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("orlydb"), $db->escapeString($md5)));
									$newnames++;
								}
							}
						}
					}
				}
			}
		}
		return $newnames;
	}

	public function retrieveSrr()
	{
		$db = new DB();
		$newnames = $updated = 0;
		$releases = @simplexml_load_file('http://www.srrdb.com/feed/srrs');
		if ($releases !== false)
		{
			foreach ($releases->channel->item as $release)
			{
                $md5 = md5($release->title);
                $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
				if ($oldname !== false && $oldname["md5"] == $md5)
					continue;
				else
				{
					$db->query(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash) VALUES (%s, FROM_UNIXTIME(".strtotime($release->pubDate)."), now(), %s, %s)", $db->escapeString($release->title), $db->escapeString("srrdb"), $db->escapeString($md5)));
					$newnames++;
				}
			}
		}
		return $newnames;
	}

	public function retrievePredbme()
	{
		$db = new DB();
		$newnames = $updated = 0;
		$arr = array("http://predb.me/?cats=movies-sd&rss=1", "http://predb.me/?cats=movies-hd&rss=1", "http://predb.me/?cats=movies-discs&rss=1", "http://predb.me/?cats=tv-sd&rss=1", "http://predb.me/?cats=tv-hd&rss=1", "http://predb.me/?cats=tv-discs&rss=1", "http://predb.me/?cats=music-audio&rss=1", "http://predb.me/?cats=music-video&rss=1", "http://predb.me/?cats=music-discs&rss=1", "http://predb.me/?cats=games-pc&rss=1", "http://predb.me/?cats=games-xbox&rss=1", "http://predb.me/?cats=games-playstation&rss=1", "http://predb.me/?cats=games-nintendo&rss=1", "http://predb.me/?cats=apps-windows&rss=1", "http://predb.me/?cats=apps-linux&rss=1", "http://predb.me/?cats=apps-mac&rss=1", "http://predb.me/?cats=apps-mobile&rss=1", "http://predb.me/?cats=books-ebooks&rss=1", "http://predb.me/?cats=books-audio-books&rss=1", "http://predb.me/?cats=xxx-videos&rss=1", "http://predb.me/?cats=xxx-images&rss=1", "http://predb.me/?cats=dox&rss=1", "http://predb.me/?cats=unknown&rss=1");
		foreach ($arr as &$value)
		{
			$releases = @simplexml_load_file($value);
			if ($releases !== false)
			{
				foreach ($releases->channel->item as $release)
				{
                    $md5 = md5($release->title);
                    $oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($md5)));
					if ($oldname !== false && $oldname["md5"] == $md5)
						continue;
					else
					{
						$db->query(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash) VALUES (%s, now(), now(), %s, %s)", $db->escapeString($release->title), $db->escapeString("predbme"), $db->escapeString($md5)));
						$newnames++;
					}
				}
			}
		}
		return $newnames;
	}

	// Update a single release as it's created.
	public function matchPre($cleanerName, $releaseID)
	{
		$db = new DB();
		if($x = $db->queryOneRow(sprintf("SELECT ID FROM prehash WHERE title = %s", $db->escapeString($cleanerName))) !== false)
		{
			$db->query(sprintf("UPDATE releases SET relnamestatus = 11 WHERE ID = %d", $x["ID"], $releaseID));
		}
	}

	// When a searchname is the same as the title, tie it to the predb. Try to update the categoryID at the same time.
	public function matchPredb()
	{
		$db = new DB();
        $consoletools = new ConsoleTools();
		$updated = 0;
		if($this->echooutput)
			echo "\nQuerying DB for matches in prehash titles with release searchnames.\n";

		$res = $db->queryDirect("SELECT p.ID, p.category, r.ID AS releaseID FROM prehash p inner join releases r ON p.title = r.searchname WHERE p.releaseID IS NULL");
        $row = mysqli_fetch_array($res);
        $total = $row [0];
        if($total > 0)
        {
            $updated = 1;
			foreach ($res as $row)
			{
				$db->query(sprintf("UPDATE prehash SET releaseID = %d WHERE ID = %d", $row["releaseID"], $row["ID"]));
                $catName=str_replace(array("TV-", "TV: "), '', $row["category"]);
				if($catID = $db->queryOneRow(sprintf("SELECT ID FROM category WHERE title = %s", $db->escapeString($catName))))
					$db->query(sprintf("UPDATE releases SET categoryID = %d WHERE ID = %d", $db->escapeString($catID["ID"]), $db->escapeString($row["releaseID"])));
				$db->query(sprintf("UPDATE releases SET relnamestatus = 11 WHERE ID = %d", $row["releaseID"]));
				if($this->echooutput)
					$consoletools->overWrite("Matching up prehash titles with release search names: ".$consoletools->percentString($updated++,$total));
			}
        }

		return $updated;
    }
	// Look if the release is missing an nfo.
	public function matchNfo()
	{
		$db = new DB();
		$nfos = 0;
		if($this->echooutput)
			echo "\nMatching up prehash NFOs with releases missing an NFO.\n";

			if($res = $db->queryDirect("SELECT r.ID, p.nfo FROM releases r inner join predb p ON r.ID = p.releaseID WHERE p.nfo IS NOT NULL AND r.nfostatus != 1 LIMIT 100"))
		{
			$nfo = new Nfo($this->echooutput);
            $functions = new Functions($this->echooutput);
			while ($row = mysqli_fetch_assoc($res))
			{
				$buffer = getUrl($row["nfo"]);
				if ($buffer !== false && strlen($buffer))
				{
					$functions->addReleaseNfo($row["ID"]);
					$db->query(sprintf("UPDATE releasenfo SET nfo = compress(%s) WHERE releaseID = %d", $db->escapeString($buffer), $row["ID"]));
					$db->query(sprintf("UPDATE releases SET nfostatus = 1 WHERE ID = %d", $row["ID"]));
					if($this->echooutput)
						echo ".";
					$nfos++;
				}
			}
			return $nfos;
		}
	}

	// Matches the MD5 within the prehash table to release files and subjects (names) which are hashed.
	public function parseTitles($time, $echo, $cats, $namestatus, $md5="")
	{
		$db = new DB();
		$namefixer = new Namefixer();
		$updated = 0;

		$tq = "";
		if ($time == 1)
			$tq = " and r.adddate > (now() - interval 3 hour)";
		$ct = "";
		if ($cats == 1)
			$ct = " and r.categoryID in (2020, 5050, 6070, 8010)";

		if($this->echooutput)
		{
			$te = "";
			if ($time == 1)
				$te = " in the past 3 hours";
			echo "Fixing search names".$te." using the prehash md5.\n";
		}
		if ($res = $db->queryDirect("select r.ID, r.name, r.searchname, r.categoryID, r.groupID, rf.name as filename from releases r left join releasefiles rf on r.ID = rf.releaseID  where (r.name REGEXP'[a-fA-F0-9]{32}' or rf.name REGEXP'[a-fA-F0-9]{32}') and r.relnamestatus = 1 and r.categoryID = 8010 and passwordstatus >= -1 ORDER BY rf.releaseID, rf.size DESC ".$tq))
		{
			while($row = mysqli_fetch_assoc($res))
			{
			   if (preg_match("/[a-f0-9]{32}/i", $row["name"], $matches))
					$updated = $updated + $namefixer->matchPredbMD5($matches[0], $row, $echo, $namestatus, $this->echooutput);
				else if (preg_match("/[a-f0-9]{32}/i", $row["filename"], $matches))
					$updated = $updated + $namefixer->matchPredbMD5($matches[0], $row, $echo, $namestatus, $this->echooutput);
			}
		}
		return $updated;
	}


	public function getAll($offset, $offset2)
	{
		$db = new DB();
		return $db->query(sprintf("SELECT p.*, r.guid FROM prehash p left join releases r on p.releaseID = r.ID ORDER BY p.adddate DESC limit %d,%d", $offset, $offset2));
	}

	public function getCount()
	{
		$db = new DB();
		$count = $db->queryOneRow("SELECT count(*) as cnt from prehash");
		return $count["cnt"];
	}
}
