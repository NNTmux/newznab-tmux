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
require_once("simple_html_dom.php");

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
	public function updatePre()
	{
		$db = new DB();
        $f = new Functions();
		$newnames = 0;
		$newestrel = $db->queryOneRow("SELECT adddate, ID FROM prehash ORDER BY adddate DESC LIMIT 1");
		if (strtotime($newestrel["adddate"]) < time()-1200 || is_null($newestrel['adddate']))
		{
			if ($this->echooutput)
			{
				echo $this->c->header("Retrieving titles from preDB sources.");
			}
			$newwomble = $this->retrieveWomble();
			if ($this->echooutput)
			{
				echo $this->c->primary($newwomble . " \tRetrieved from Womble.");
			}
			$newomgwtf = $this->retrieveOmgwtfnzbs();
			if ($this->echooutput)
			{
				echo $this->c->primary($newomgwtf . " \tRetrieved from Omgwtfnzbs.");
			}
			$newzenet = $this->retrieveZenet();
			if ($this->echooutput)
			{
				echo $this->c->primary($newzenet . " \tRetrieved from Zenet.");
			}
			$newprelist = $this->retrievePrelist();
			if ($this->echooutput)
			{
				echo $this->c->primary($newprelist . " \tRetrieved from Prelist.");
			}
			$neworly = $this->retrieveOrlydb();
			if ($this->echooutput)
			{
				echo $this->c->primary($neworly . " \tRetrieved from Orlydb.");
			}
			$newsrr = $this->retrieveSrr();
			if ($this->echooutput)
			{
				echo $this->c->primary($newsrr . " \tRetrieved from Srrdb.");
			}
			$newpdme = $this->retrievePredbme();
			if ($this->echooutput)
			{
				echo $this->c->primary($newpdme . " \tRetrieved from Predbme.");
			}
			$this->retrieveAllfilledMoovee();
			$this->retrieveAllfilledTeevee();
			$this->retrieveAllfilledErotica();
			$this->retrieveAllfilledForeign();
			$abgx = $this->retrieveAbgx();
			if ($this->echooutput)
			{
				echo $this->c->primary($abgx . " \tRetrieved from abgx.");
			}
            $newUsenetCrawler = $this->retrieveUsenetCrawler();
			if ($this->echooutput) {
				echo $this->c->primary($newUsenetCrawler . " \tRetrieved from Usenet-Crawler.");
			}
            $this->retrieveAllfilledMoovee();
			$this->retrieveAllfilledTeevee();
			$this->retrieveAllfilledErotica();
			$this->retrieveAllfilledForeign();
			$newnames = $newwomble + $newomgwtf + $newzenet + $newprelist + $neworly + $newsrr + $newpdme + $abgx + $newUsenetCrawler;
			if (count($newnames) > 0) {
				$db->exec(sprintf('UPDATE prehash SET adddate = NOW() WHERE ID = %d', $newestrel['ID']));
			}
			return $newnames;
	    }
    }

    // Attempts to match predb to releases.
	public function checkPre($nntp)
	{
		$matched = $this->matchPredb();
		if ($this->echooutput)
		{
			$count = ($matched > 0) ? $matched : 0;
			echo $this->c->header('Matched ' . number_format($count) . ' prehash titles to release search names.');
		}
		$nfos = $this->matchNfo($nntp);
		if ($this->echooutput)
		{
			$count = ($nfos > 0) ? $nfos : 0;
			echo $this->c->header('Added ' . $count . ' missing NFOs from prehash sources.');
		}
	}

	public function retrieveWomble()
	{
		$db = new DB();
        $f = new Functions();
		$newnames = $updated = 0;
        $matches2 = $matches = $match = $m = '';

		$buffer = $this->fileContents('http://www.newshost.co.za');
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
										$nfo = $db->escapeString("http://newshost.co.za/".$matches2["nfo"]);

                                            $db->exec(sprintf("UPDATE prehash SET nfo = %s, size = %s, category = %s, predate = %s, adddate = now(), source = %s where ID = %d", $nfo, $size, $db->escapeString($matches2["category"]), $db->from_unixtime(strtotime($matches2["date"])), $db->escapeString("womble"), $oldname["ID"]));
                                $updated++;
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
									$nfo = $db->escapeString("http://newshost.co.za/".$matches2["nfo"]);

                                    if (strlen($matches2['title']) > 15) {
                                        if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, nfo, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $nfo, $size, $db->escapeString($matches2["category"]), $db->escapeString("womble"), $db->escapeString(md5($matches2["title"])))));{
                                $newnames++;
                                }
							}
						}
					}
				}
			}
        }
            echo $this->c->primary($updated . " \tUpdated from Womble.");
	}
		else
		{
			echo $this->c->error("Update from Womble failed.");
		}
		return $newnames;
	}

	public function retrieveOmgwtfnzbs()
	{
		$db = new DB();
        $f = new Functions();
		$newnames = $updated = 0;
        $matches2 = $matches = $match = $m = '';

		$buffer = $this->fileContents('http://rss.omgwtfnzbs.org/rss-info.php');
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
						    $title = preg_replace('/\s+- omgwtfnzbs\.org/', '', $matches2['title']);
							$md5 = md5($title);
							$oldname = $db->queryOneRow(sprintf('SELECT md5, source, ID FROM prehash WHERE md5 = %s', $db->escapeString($md5)));
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
                                    $updated++;
								}
							}
							else
							{
								$size = $db->escapeString(round($matches2["size1"]).$matches2["size2"]);
                                if (strlen($title) > 15) {
                                    if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($title), $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $db->escapeString($md5)))); {
                                $newnames++;
                                }
                                }
							}
						}
					}
				}
			}
            echo $this->c->primary($updated . " \tUpdated from Omgwtfnzbs.");
		}
		else
		{
			echo $this->c->error("Update from Omgwtfnzbs failed.");
		}
		return $newnames;
	}

	public function retrieveZenet()
        {
                $db = new DB();
                $f = new Functions();
                $newnames = $updated = 0;
                $matches2 = $matches = $match = $m = '';

		        $buffer = $this->fileContents('http://pre.zenet.org/live.php');
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
							                        $oldname = $db->queryOneRow(sprintf('SELECT md5 FROM prehash WHERE md5 = %s', $db->escapeString($md5)));
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

                                                                if (strlen($title) > 15) {
                                                                    if ($db->queryInsert(sprintf('INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, %s, now(), %s, %s)', $title, $size, $category, $predate, $db->escapeString('zenet'), $md5)));{
                                                                $newnames++;
                                                                }
                                                        }
                                                        }
                                                }
                                        }
                                }
                        }
                }
                else
		        {
			        echo $this->c->error("Update from Zenet failed.");
		        }
                return $newnames;
        }

	public function retrievePrelist()
	{
		$db = new DB();
        $f = new Functions();
		$newnames = $updated = 0;
        $matches2 = $matches = $match = $m = '';

		$buffer = $this->fileContents('http://www.prelist.ws/');
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

								if (strlen($matches2['title']) > 15) {
								    if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("prelist"), $db->escapeString($md5))));{
								$newnames++;
                                }
                                }
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

								$db->exec(sprintf("INSERT IGNORE INTO prehash (title, category, predate, adddate, source, md5) VALUES (%s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $category, $db->escapeString("prelist"), $db->escapeString($md5)));
								$newnames++;
							}
						}
					}
				}
			}
		}
        else
		{
			echo $this->c->error("Update from Prelist failed.");
		}
		return $newnames;
	}

	public function retrieveOrlydb()
	{
		$db = new DB();
        $f = new Functions();
		$newnames = $updated = 0;
        $matches2 = $matches = $match = $m = '';

		$buffer = $this->fileContents('http://www.orlydb.com/');
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
                                $oldname = $db->queryOneRow(sprintf("SELECT md5 FROM prehash WHERE md5 = %s", $db->escapeString($md5)));
								if ($oldname !== false && $oldname["md5"] == $md5)
									continue;
								else
								{
									if (!isset($matches2["size"]) && empty($matches2["size"]))
										$size = "NULL";
									else
										$size = $db->escapeString($matches2["size"]);

                                    if (strlen($matches['title']) > 15) {
                                        if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("orlydb"), $db->escapeString($md5))));{
									$newnames++;
                                    }
								}
							}
						}
					}
				}
			}
		}
    }
        else
		{
			echo $this->c->error("Update from Orly failed.");
		}
		return $newnames;
	}

   public function retrieveSrr()
	{
		$db = new DB();
        $f = new Functions();
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
		$web = $this->fileContents($url, false, $context);
		if ($web !== false)
		{
			$releases = simplexml_load_string($this->fileContents($url, false, $context));
			if ($releases !== false)
			{

			foreach ($releases->channel->item as $release)
			{
				$md5 = md5($release->title);
				$oldname = $db->queryOneRow(sprintf('SELECT md5 FROM prehash WHERE md5 = %s', $db->escapeString($md5)));
				if ($oldname !== false && $oldname['md5'] == $md5)
					continue;
				else
				{
                if (strlen($release->title) > 15) {
                    if($db->exec(sprintf('INSERT IGNORE INTO prehash (title, predate, adddate, source, md5) VALUES (%s, %s, now(), %s, %s)', $db->escapeString($release->title), $f->from_unixtime(strtotime($release->pubDate)), $db->escapeString('srrdb'), $db->escapeString($md5))));{$newnames++;
}
				}
			}
		}
        }
            else
			{
				echo $this->c->error("Update from Srr failed.");
			}
		}
		else
		{
			echo $this->c->error("Update from Srr failed.");
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
                    if (strlen($release->title) > 15) {
                        if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, md5) VALUES (%s, now(), now(), %s, %s)", $db->escapeString($release->title), $db->escapeString("predbme"), $db->escapeString($md5))));{
						$newnames++;
                        }
					}
				}
			}
        }else
			{
				echo $this->c->error("Update from Predbme failed.");
			}
		}
		return $newnames;
	}

    public function retrieveAllfilledMoovee()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = $updated = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.moovee');
		$buffer = $this->fileContents('http://abmoovee.allfilled.com/reqs.php?fetch=posted&page=1');
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
                                if (strlen($title) > 15) {
                                    $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, md5, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
       }
        else
			echo $this->c->error ("Error: Update from Moovee failed.");
	}

	public function retrieveAllfilledTeevee()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = $updated = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.teevee');
		$buffer = $this->fileContents('http://abteevee.allfilled.com/reqs.php?fetch=posted&page=1');
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
                                if (strlen($title) > 15) {
                                    $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, md5, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
        }else
			echo $this->c->error ("Error: Update from Teevee failed.");
	}

	public function retrieveAllfilledErotica()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = $updated = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.erotica');
		$buffer = $this->fileContents('http://aberotica.allfilled.com/reqs.php?fetch=posted&page=1');
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
                                if (strlen($title) > 15) {
                                    $db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, md5, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $requestID, $groupID));
							}
						}
					}
				}
			}
		}
        }else
			echo $this->c->error ("Error: \tUpdate from Erotica failed.");
	}

	public function retrieveAllfilledForeign()
	{
		$db = new DB();
        $functions = new Functions();
		$newnames = $updated = 0;
		$groups = new Groups();
		$groupID = $functions->getIDByName('alt.binaries.mom');
		$buffer = $this->fileContents('http://abforeign.allfilled.com/reqs.php?fetch=posted&page=1');
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
                                if (strlen($title) > 15) {
                                    if($db->exec(sprintf("INSERT IGNORE INTO prehash (title, predate, adddate, source, md5, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %s, %d) ON DUPLICATE KEY UPDATE requestID = %d, groupID = %d", $title, $predate, $source, $md5, $requestID, $groupID, $requestID, $groupID)));
							}
						}
					}
				}
			}
		}
        }else
			echo $this->c->error ("Error: Update from Foreign failed.");
	}

    public function retrieveAbgx()
	{
		$db = new DB();
		$newnames = 0;
		$db = new DB();
        $functions = new Functions();
		$groups = new Groups();

		$options = array(
		  'http'=>array(
			'method'=>"GET",
			'header'=>"Accept-language: en\r\n" .
					  "Cookie: foo=bar\r\n" .  // check function.stream-context-create on php.net
					  "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
		  )
		);
		$context = stream_context_create($options);
		$arr = array('x360', 'abcp', 'abgw', 'abgwu', 'absp', 'abgn', 'spsv', 'n3ds', 'abgx', 'abg', 'x360');
		foreach ($arr as &$value)
		{
			$releases = simplexml_load_string($this->fileContents('http://www.abgx.net/rss/' . $value . '/posted.rss', false, $context));
			if ($releases !== false)
			{
				preg_match('/^Filled requests in #(\S+)/', $releases->channel->description, $groupname);
				$groupid = ($functions->getIDByName($groupname[1])) ? $functions->getIDByName($groupname[1]) : 0;
				foreach ($releases->channel->item as $release)
				{
					preg_match('/^Req (\d+) - (\S+) .+/', $release->title, $request);
					$requestid = $request[1];
					preg_match('/\(\d+\) (\S+) .+(\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2})/', $release->description, $title);
					$md5 = md5($title[1]);
					$predate = $title[2];

					$oldname = $db->queryOneRow(sprintf('SELECT md5, requestID, groupID FROM prehash WHERE md5 = %s', $db->escapeString($md5)));
					if ($oldname !== false && $oldname['md5'] == $md5)
					{
						$oldrequestid = $oldname['requestID'];
						$oldgroupid = $oldname['groupID'];
						$db->exec(sprintf('UPDATE prehash SET requestID = IF(%d = 0, %d, 0), groupid = IF(%d = 0, %d, 0) WHERE md5 = %s', $oldrequestid, $requestid, $oldgroupid, $groupid, $db->escapeString($md5)));
					}
					else
					{
                        if (strlen($title[1]) > 15) {
                            if ($db->exec(sprintf('INSERT INTO prehash (title, predate, adddate, source, md5, requestID, groupID) VALUES (%s, %s, now(), %s, %s, %d, %d)', $db->escapeString($title[1]), $functions->from_unixtime(strtotime($predate)), $db->escapeString('abgx'), $db->escapeString($md5), $requestid, $groupid)))
						{
							$newnames++;
						}
                    }
					}
				}
			}
			else
				echo $this->c->error ("Error: Update from ABGX failed.");
		}
		return $newnames;
	}

    public function retrieveUsenetCrawler()
	{
		$db = new DB();
		$newnames = 0;
        $functions = new Functions();
		$html = str_get_html($this->getWebPage("http://www.usenet-crawler.com/predb?q=&c=&offset=0#results"));
		$releases = @$html->find('table[id="browsetable"]');
		if (!isset($releases[0])) {
			return $newnames;
		}
		$rows = $releases[0]->find('tr');
		$count = 0;
		foreach ($rows as $post) {
			if ($count == 0) {
				//Skip the table header row
				$count++;
				continue;
			}
			$data = $post->find('td');
			$predate = strtotime($data[0]->innertext);

			$e = $data[1]->find('a');
			if (isset($e[0])) {
				$title = trim($e[0]->innertext);
				$title = str_ireplace(array('<u>', '</u>'), '', $title);
			} elseif (preg_match('/(.+)<\/br><sub>/', $data[1])) {
				// title is nuked, so skip
				continue;
			} else {
				$title = trim($data[1]->innertext);
			}
			$e = $data[2]->find('a');
			$category = $e[0]->innertext;
			preg_match('/([\d\.]+MB)/', $data[3]->innertext, $match);
			$size = isset($match[1]) ? $match[1] : 'NULL';
			$md5 = md5($title);
			if (strlen($title) > 15 && $category != 'NUKED') {
				if ($db->exec(sprintf('INSERT INTO prehash (title, predate, adddate, source, md5, category, size) VALUES (%s, %s, now(), %s, %s, %s, %s)', $db->escapeString($title), $functions->from_unixtime($predate), $db->escapeString('usenet-crawler'), $db->escapeString($md5), $db->escapeString($category), $db->escapeString($size)))) {
					$newnames++;
				}
			}
		}
		return $newnames;
	}

	// Update a single release as it's created.
	public function matchPre($cleanerName, $releaseID)
	{
		$db = new DB();
        $f = new Functions();
			$x = $db->queryOneRow(sprintf('SELECT ID FROM prehash WHERE title = %s', $db->escapeString($cleanerName)));
		if (isset($x['ID'])) {
			$db->exec(sprintf('UPDATE releases SET preID = %d WHERE ID = %d', $x['ID'], $releaseID));
			return true;
		}
		return false;
	}

	// When a searchname is the same as the title, tie it to the predb. Try to update the categoryID at the same time.
	public function matchPredb()
	{
		$db = new DB();
        $f = new Functions();
        $consoletools = new ConsoleTools();
		$updated = 0;
		if($this->echooutput)
			{
			echo $this->c->header('Querying DB for release searchnames not matched with prehash titles.');
		}

		$res = $db->queryDirect('SELECT p.ID AS preID, r.ID AS releaseID FROM prehash p INNER JOIN releases r ON p.title = r.searchname WHERE r.preID IS NULL');
        //$row = mysqli_fetch_array($res);
        //$total = $row [0];
        $total = $res->rowCount();
        if($total > 0)
        {
           echo "\n";
			foreach ($res as $row)
			{
				$db->exec(sprintf('UPDATE releases SET preID = %d WHERE ID = %d', $row['preID'], $row['releaseID']));
				if ($this->echooutput)
				{
					$consoletools->overWritePrimary('Matching up prehash titles with release search names: ' . $consoletools->percentString(++$updated, $total));
				}
			}
			echo "\n";
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
			echo $this->c->header ("Matching up prehash NFOs with releases missing an NFO.");

			$res = $db->query("SELECT r.ID, p.nfo, r.completion, r.guid, r.groupID FROM releases r INNER JOIN prehash p ON r.preID = p.ID WHERE p.nfo IS NOT NULL AND r.nfostatus != 1 LIMIT 100");
		    $total = count($res);
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
		public function parseTitles($time, $echo, $cats, $namestatus, $show)
	{
		$db = new DB();
		$namefixer = new Namefixer();
        $consoletools = new ConsoleTools();
		$updated = $checked = 0;
		$matches = '';

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
			echo $this->c->header ("Fixing search names".$te." using the prehash md5.");
		}
        $regex = "AND ((r.bitwise & 512) = 512 OR rf.name REGEXP'[a-fA-F0-9]{32}')";
		$res = $db->queryDirect(sprintf('SELECT r.ID AS releaseID, r.name, r.searchname, r.categoryID, r.groupID, '
				. 'dehashstatus, rf.name AS filename FROM releases r '
				. 'LEFT OUTER JOIN releasefiles rf ON r.ID = rf.releaseID '
				. 'WHERE (bitwise & 260) = 256 AND dehashstatus BETWEEN -6 AND 0 %s %s %s', $regex, $ct, $tq));
        $total = $res->rowCount();
		echo $this->c->primary(number_format($total) . " releases to process.");
		if ($total > 0) {
			foreach ($res as $row) {
				if (preg_match('/[a-f0-9]{32}/i', $row['name'], $matches)) {
					$updated = $updated + $namefixer->matchPredbMD5($matches[0], $row, $echo, $namestatus, $this->echooutput, $show);
				} else if (preg_match('/[a-f0-9]{32}/i', $row['filename'], $matches)) {
					$updated = $updated + $namefixer->matchPredbMD5($matches[0], $row, $echo, $namestatus, $this->echooutput, $show);
				}
				if ($show === 2) {
					$consoletools->overWritePrimary("Renamed Releases: [" . number_format($updated) . "] " . $consoletools->percentString( ++$checked, $total));
				}
			}
		}
		if ($echo == 1) {
			echo $this->c->header("\n" . $updated . " releases have had their names changed out of: " . number_format($checked) . " files.");
		} else {
			echo $this->c->header("\n" . $updated . " releases could have their names changed. " . number_format($checked) . " files were checked.");
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

    public function getOne($preID)
	{
		$db = new DB();
		return $db->queryOneRow(sprintf('SELECT * FROM predb WHERE id = %d', $preID));
	}

	public function getWebPage($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

	function fileContents($path, $use = false, $context = '')
	{
		if ($context === '') {
			$str = @file_get_contents($path);
		} else {
			$str = @file_get_contents($path, $use, $context);
		}
		if ($str === FALSE) {
			return false;
		} else {
			return $str;
		}
	}
}
