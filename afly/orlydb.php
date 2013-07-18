<?php

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");
require_once ("hashcompare.php");

//This script is adapted from nZEDb predb.php script

$db = new DB();
		$newnames = 0;

		$buffer = getUrl("http://www.orlydb.com/");

        echo "Requesting pre info from orlydb ...\n";

		if ($buffer !== false && strlen($buffer))
		{
			if (preg_match('/<div id="releases">(.+)<div id="pager">/s', $buffer, $match))
			{
				if (preg_match_all('/<div>.+<\/div>/s', $match["1"], $matches))
				{
					foreach ($matches as $m1)
					{
						foreach ($m1 as $m)
						{
							if (preg_match('/timestamp">(?P<date>.+?)<\/span>.+?section">.+?">(?P<category>.+?)<\/a>.+?release">(?P<title>.+?)<\/span>(.+info">(?P<size>.+?) )?/s', $m, $matches2))
							{
								$oldname = $db->queryOneRow(sprintf("SELECT releasename FROM prehash WHERE title = %s", $db->escapeString($matches2["title"])));
								if ($oldname["releasename"] == $matches2["title"])
									continue;
								else
								{
									if (!isset($matches2["size"]) && empty($matches["size"]))
										$size = "NULL";
									else
										$size = $db->escapeString($matches2["size"]);

									$db->query(sprintf("INSERT IGNORE INTO prehash (releasename, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $db->escapeString($matches2["category"]), $db->escapeString("orlydb"), $db->escapeString(md5($matches2["title"]))));
									$newnames++;
								}
							}
						}
					}
				}
			}
		}
		return $newnames;



?>