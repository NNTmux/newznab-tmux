<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");
require_once ("prehashHashCompare.php");

//This script is adapted from nZEDb predb.php script

$db = new DB();
		$newnames = 0;

		$buffer = getUrl("http://www.prelist.ws/");

        echo "Requesting pre info from prelist.ws ...\n";

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
							$oldname = $db->queryOneRow(sprintf("SELECT releasename FROM prehash WHERE releasename = %s", $db->escapeString($matches2["title"])));
							if ($oldname["releasename"] == $matches2["title"])
								continue;
							else
							{
								if (!isset($matches2["size"]) && empty($matches["size"]))
									$size = "NULL";
								else
									$size = $db->escapeString(round($matches2["size"]));

								$db->query(sprintf("INSERT IGNORE INTO prehash (releasename, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("prelist"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
						else if (preg_match('/">\[ (?P<date>.+?) U.+?">(?P<category>.+?)<\/a>.+?">(?P<category1>.+?)<\/a.+">(?P<title>.+?)<\/a>/si', $m, $matches2))
						{
							$oldname = $db->queryOneRow(sprintf("SELECT releasename FROM prehash WHERE releasename = %s", $db->escapeString($matches2["title"])));
							if ($oldname["releasename"] == $matches2["title"])
								continue;
							else
							{
								$category = $db->escapeString($matches2["category"].", ".$matches2["category1"]);

								$db->query(sprintf("INSERT IGNORE INTO prehash (releasename, category, predate, adddate, source, hash) VALUES (%s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $category, $db->escapeString("prelist"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;



?>