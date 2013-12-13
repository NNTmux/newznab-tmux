<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/framework/db.php");
require_once(WWW_DIR."lib/category.php");
require_once(WWW_DIR."lib/groups.php");
require_once(WWW_DIR."lib/nfo.php");
require_once(WWW_DIR."lib/site.php");
require_once("functions.php");
require_once("consoletools.php");
require_once("ColorCLI.php");
require_once ("nzbcontents.php");

/*
 * Class for inserting names/categories/md5 etc from predb sources into the DB, also for matching names on files / subjects.
 */
 // This script is adapted from nZEDb
Class Predb
{
	function __construct($echooutput=false)
	{
		$s = new Sites();
		$this->site = $s->get();
		$this->echooutput = $echooutput;
        $this->db = new DB();
        $this->c = new ColorCLI;
	}

	// Retrieve pre info from predb sources and store them in the DB.
	// Returns the quantity of new titles retrieved.
	public function combinePre($nntp)
	{
		$db = new DB();
        $f = new Functions();
		$newnames = 0;
		$newestrel = $db->queryOneRow("SELECT adddate, ID FROM prehash ORDER BY adddate DESC LIMIT 1");
		if (strtotime($newestrel["adddate"]) < time()-600 || is_null($newestrel['adddate']))
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
            $this->retrieveAllfilledMoovee();
			$this->retrieveAllfilledTeevee();
			$this->retrieveAllfilledErotica();
			$this->retrieveAllfilledForeign();
			$newnames = $newwomble+$newomgwtf+$newzenet+$newprelist+$neworly+$newsrr+$newpdme;
			if(count($newnames) > 0)
				$db->exec(sprintf("UPDATE prehash SET adddate = now() where ID = %d", $newestrel["ID"]));
		}
		$matched = $this->matchPredb();
		if ($matched > 0 && $this->echooutput)
			echo "\nMatched ".$matched." prehash titles to release search names.\n";
        else
            echo "\nNo matches found.\n";
		$nfos = $this->matchNfo($nntp);
		if ($nfos > 0 && $this->echooutput)
			echo "\nAdded ".$nfos." missing NFOs from prehash sources.\n";
        else
            echo "\nNo missing nfo matches found.Nothing added.\n";

		return $newnames;
	}

	public function retrieveWomble()
	{
		$db = new DB();
        $f = new Functions();
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

									    $db->exec(sprintf("UPDATE prehash SET nfo = %s, size = %s, category = %s, predate = %s, adddate = now(), source = %s where ID = %d", $nfo, $size, $db->escapeString($matches2["category"]), $db->from_unixtime(strtotime($matches2["date"])), $db->escapeString("womble"), $oldname["ID"]));
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

								    $db->exec(sprintf("INSERT IGNORE INTO prehash (title, nfo, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $nfo, $size, $db->escapeString($matches2["category"]), $db->escapeString("womble"), $db->escapeString(md5($matches2["title"]))));
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
        $f = new Functions();
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
									$db->exec(sprintf("UPDATE prehash SET size = %s, category = %s, predate = FROM_UNIXTIME(".strtotime($matches2["date"])."), adddate = now(), source = %s where ID = %d", $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $oldname["ID"]));
                                    $newnames++;
								}
							}
							else
							{
								$size = $db->escapeString(round($matches2["size1"]).$matches2["size2"]);
								$title = preg_replace("/  - omgwtfnzbs.org/", "", $matches2["title"]);
								$db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($title), $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $db->escapeString($md5)));
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
                $f = new Functions();
                $newnames = $updated = 0;

                $buffer = getUrl("http://pre.zenet.org/live.php");
                if ($buffer !== false && strlen($buffer))
                {
                       if (preg_match_all('/<div class="mini-layout fluid">((\s+\S+)?\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(\S+\s+)?(\S+\s+)?(\S+\s+)?(\S+\s+)?(\S+\s+)?(\S+\s+)?(\S+\s+)?<\/div>\s+<\/div>)/s', $buffer, $matches))
                        {
                                foreach ($matches as $match)
                                {
                                        foreach ($match as $m)
                                        {
                                                if (preg_match('/<span class="bold">(?P<predate>\d{4}-\d{2}-\d{2} \d{2}:\d{2})<\/span>.+<a href="\?post=\d+"><b><font color="#\d+">(?P<title>.+)<\/font><\/b><\/a>.+<p><a href="\?cats=.+"><font color="#FF9900">(?P<category>.+)<\/font><\/a> \| (?P<size1>[\d\.,]+)?(?P<size2>[MGK]B)? \/.+<\/div>/s', $m, $matches2))
                                                {
                                                    $predate = $db->escapeString($matches2['predate']);
							                        $md5 = $db->escapeString(md5($matches2['title']));
						        	                $title = $db->escapeString($matches2['title']);
							                        $oldname = $db->queryOneRow(sprintf('SELECT hash FROM prehash WHERE hash = %s', $db->escapeString($md5)));
                                                        if ($oldname !== false && $oldname["md5"] == $md5)
                                                                continue;
                                                        else
                                                        {
                                                               	if (!isset($matches2['size1']) && empty($matches2['size1']))
									                                $size = 'NULL';
								                                else
									                                $size = $db->escapeString(round($matches2['size1']).$matches2['size2']);

								                                if (isset($matches2['category']) && !empty($matches2['category']))
									                                $category = $db->escapeString($matches2['category']);
								                                else
									                                $category = 'NULL';

                                                                $run = $db->queryInsert(sprintf('INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, %s, now(), %s, %s)', $title, $size, $category, $predate, $db->escapeString('zenet'), $md5));
                                                                if ($run)
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
        $f = new Functions();
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

								$db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("prelist"), $db->escapeString($md5)));
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

								$db->exec(sprintf("INSERT IGNORE INTO prehash (title, category, predate, adddate, source, hash) VALUES (%s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $category, $db->escapeString("prelist"), $db->escapeString($md5)));
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
        $f = new Functions();
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

									$db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("orlydb"), $db->escapeString($md5)));
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
		$newnames = 0;
		$url = "http://www.srrdb.com/feed/srrs";

		$options = array(
		  'http'=>array(
			'method'=>"GET",
			'header'=>"Accept-language: en\r\n" .
					  "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
					  "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
		  )
		);

		$context = stream_context_create($options);
		$releases = file_get_contents($url, false, $context);
		$releases = @simplexml_load_string($releases);
		if ($releases !== false)
		{
			foreach ($releases->channel->item as $release)
			{
				$md5 = md5($release->title);
				$oldname = $db->queryOneRow(sprintf('SELECT hash FROM prehash WHERE hash = %s', $db->escapeString($md5)));
				if ($oldname !== false && $oldname['md5'] == $md5)
					continue;
				else
				{
					$db->exec(sprintf('INSERT IGNORE INTO prehash (title, predate, adddate, source, hash) VALUES (%s, %s, now(), %s, %s)', $db->escapeString($release->title), $db->from_unixtime(strtotime($release->pubDate)), $db->escapeString('srrdb'), $db->escapeString($md5)));$newnames++;
				}
			}
		}
		return $newnames;
	}

	public function retrievePredbme()
	{
		$db = new DB();
        $f = new Functions();
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
						$db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash) VALUES (%s, now(), now(), %s, %s)", $db->escapeString($release->title), $db->escapeString("predbme"), $db->escapeString($md5)));
						$newnames++;
					}
				}
			}
		}
		return $newnames;
	}

    public function retrieveAllfilledMoovee()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.moovee');
		$buffer = @file_get_contents('http://abmoovee.allfilled.com/reqs.php?fetch=posted&page=1');
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr class="(even|odd)".+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<td class="cell_reqid">(?P<requestID>\d+)<\/td>.+<td class="cell_request">(?P<title>.+)<\/td>.+<td class="cell_statuschange">(?P<predate>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})<\/td>/s', $m, $matches2))
						{
							if (isset($matches2["requestID"]) && isset($matches2["title"]))
							{
								$requestID = $matches2["requestID"];
								$title = $db->escapeString($matches2["title"]);
								$md5 = $db->escapeString(md5($matches2["title"]));
								$predate = $db->escapeString($matches2["predate"]);
								$source = $db->escapeString('allfilled');
								$run = $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $predate, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
	}

	public function retrieveAllfilledTeevee()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.teevee');
		$buffer = @file_get_contents('http://abteevee.allfilled.com/reqs.php?fetch=posted&page=1');
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr class="(even|odd)".+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<td class="cell_reqid">(?P<requestID>\d+)<\/td>.+<td class="cell_request">(?P<title>.+)<\/td>.+<td class="cell_statuschange">(?P<predate>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})<\/td>/s', $m, $matches2))
						{
							if (isset($matches2["requestID"]) && isset($matches2["title"]))
							{
								$requestID = $matches2["requestID"];
								$title = $db->escapeString($matches2["title"]);
								$md5 = $db->escapeString(md5($matches2["title"]));
								$predate = $db->escapeString($matches2["predate"]);
								$source = $db->escapeString('allfilled');
								$run = $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $predate, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
	}

	public function retrieveAllfilledErotica()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.erotica');
		$buffer = @file_get_contents('http://aberotica.allfilled.com/reqs.php?fetch=posted&page=1');
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr class="(even|odd)".+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<td class="cell_reqid">(?P<requestID>\d+)<\/td>.+<td class="cell_request">(?P<title>.+)<\/td>.+<td class="cell_statuschange">(?P<predate>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})<\/td>/s', $m, $matches2))
						{
							if (isset($matches2["requestID"]) && isset($matches2["title"]))
							{
								$requestID = $matches2["requestID"];
								$title = $db->escapeString($matches2["title"]);
								$md5 = $db->escapeString(md5($matches2["title"]));
								$predate = $db->escapeString($matches2["predate"]);
								$source = $db->escapeString('allfilled');
								$run = $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $predate, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
	}

	public function retrieveAllfilledForeign()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.mom');
		$buffer = @file_get_contents('http://abforeign.allfilled.com/reqs.php?fetch=posted&page=1');
		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match_all('/<tr class="(even|odd)".+?<\/tr>/s', $buffer, $matches))
			{
				foreach ($matches as $match)
				{
					foreach ($match as $m)
					{
						if (preg_match('/<td class="cell_reqid">(?P<requestID>\d+)<\/td>.+<td class="cell_request">(?P<title>.+)<\/td>.+<td class="cell_statuschange">(?P<predate>\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?)<\/td>/s', $m, $matches2))
						{
							if (isset($matches2["requestID"]) && isset($matches2["title"]))
							{
								$requestID = $matches2["requestID"];
								$title = $db->escapeString($matches2["title"]);
								$md5 = $db->escapeString(md5($matches2["title"]));
								$predate = $db->escapeString($matches2["predate"]);
								$source = $db->escapeString('allfilled');
								$run = $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, hash, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $predate, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
	}

	// Update a single release as it's created.
	public function matchPre($cleanerName, $releaseID)
	{
		$db = new DB();
        $f = new Functions();
		if($x = $db->queryOneRow(sprintf("SELECT ID FROM prehash WHERE title = %s", $db->escapeString($cleanerName))) !== false)
		{
			$db->exec(sprintf("UPDATE releases SET relnamestatus = 11 WHERE ID = %d", $x["ID"], $releaseID));
		}
	}

	// When a searchname is the same as the title, tie it to the predb. Try to update the categoryID at the same time.
	public function matchPredb()
	{
		$db = new DB();
        $f = new Functions();
        $consoletools = new ConsoleTools();
		$updated = 0;
		if($this->echooutput)
			echo $this->c->header('Querying DB for matches in prehash titles with release searchnames.');

		$res = $db->prepare("SELECT p.ID, p.category, r.ID AS releaseID FROM prehash p inner join releases r ON p.title = r.searchname WHERE p.releaseID IS NULL");
        $res->execute();
        //$row = mysqli_fetch_array($res);
        //$total = $row [0];
        $total = $res->rowCount();
        if($total > 0)
        {
            $updated = 0;
			foreach ($res as $row)
			{
				$db->exec(sprintf("UPDATE prehash SET releaseID = %d WHERE ID = %d", $row["releaseID"], $row["ID"]));
                $catName=str_replace(array("TV-", "TV: "), '', $row["category"]);
				if($catID = $db->queryOneRow(sprintf("SELECT ID FROM category WHERE title = %s", $db->escapeString($catName))))
					$db->exec(sprintf("UPDATE releases SET categoryID = %d WHERE ID = %d", $db->escapeString($catID["ID"]), $db->escapeString($row["releaseID"])));
				$db->exec(sprintf("UPDATE releases SET relnamestatus = 11 WHERE ID = %d", $row["releaseID"]));
				if($this->echooutput)
					$consoletools->overWrite("Matching up prehash titles with release search names: ".$consoletools->percentString($updated++,$total));
			}
        }

		return $updated;
    }
	// Look if the release is missing an nfo.
	public function matchNfo($nntp)
	{
		$db = new DB();
        $f = new Functions();
		$nfos = 0;
		if($this->echooutput)
			echo "\nMatching up prehash NFOs with releases missing an NFO.\n";

			$res = $db->prepare("SELECT r.ID, p.nfo FROM releases r inner join prehash p ON r.ID = p.releaseID WHERE p.nfo IS NOT NULL AND r.nfostatus != 1 LIMIT 100");
            $res->execute();
		    $total = $res->rowCount();
		    if($total > 0)
            {
			$nfo = new Nfo($this->echooutput);
            $nzbcontents = new Nzbcontents($this->echooutput);
            $functions = new Functions($this->echooutput);
		    foreach ($res as $row)
			{
				$buffer = getUrl($row["nfo"]);
				if ($buffer !== false)
				{
					if ($functions->addAlternateNfo($db, $buffer, $row, $nntp))
					{
					 if($this->echooutput)
							echo '+';
						$nfos++;
					}
					else
					{
						if($this->echooutput)
							echo '-';
				    }
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
        $regex = "AND (r.hashed = true OR rf.name REGEXP'[a-fA-F0-9]{32}')";
		$res = $db->prepare(sprintf('SELECT DISTINCT r.id, r.name, r.searchname, r.categoryid, r.groupID, rf.name AS filename, rf.releaseid, rf.size FROM releases r LEFT JOIN releasefiles rf ON r.id = rf.releaseid WHERE r.relnamestatus IN (0, 1, 20, 21, 22) AND dehashstatus BETWEEN -5 AND 0 AND passwordstatus >= -1 %s %s %s ORDER BY rf.releaseid, rf.size DESC', $regex, $tq, $ct));
        $res->execute();
        if ($res->rowCount() > 0)
		{
		  foreach ($res as $row)
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
		return $db->exec(sprintf("SELECT p.*, r.guid FROM prehash p left join releases r on p.releaseID = r.ID ORDER BY p.adddate DESC limit %d,%d", $offset, $offset2));
	}

	public function getCount()
	{
		$db = new DB();
		$count = $db->queryOneRow("SELECT count(*) as cnt from prehash");
		return $count["cnt"];
	}
}
