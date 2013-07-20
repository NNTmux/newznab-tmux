<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");
require_once ("hashcompare.php");

//This script is adapted from nZEDb predb.php script

        $db = new DB();
		$newnames = 0;

		$buffer = getUrl("http://rss.omgwtfnzbs.org/rss-info.php");
        echo "Requesting pre info from omgwtfnzbs.org rss feed ...\n";
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
							$oldname = $db->queryOneRow(sprintf("SELECT title, source, ID FROM prehash WHERE title = %s", $db->escapeString($matches2["title"])));
							if ($oldname["title"] == $matches2["title"])
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
                                $db->query(sprintf("INSERT IGNORE INTO predb (title, size, category, predate, adddate, source, md5) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($title), $size, $db->escapeString($matches2["category"]), $db->escapeString("omgwtfnzbs"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;


        ?>