<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");


//This script is adapted from nZEDb predb.php script

$db = new DB();
		$newnames = 0;

		$buffer = getUrl("http://pre.zenet.org/live.php");

        echo "Requesting pre info from pre.zenet.org  ...\n";
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
							$oldname = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE title = %s", $db->escapeString($matches2["title"])));
							if ($oldname["title"] == $matches2["title"].$matches2["title2"])
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

								$db->query(sprintf("INSERT IGNORE INTO prehash (title, size, category, predate, adddate,  source, hash) VALUES (%s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $size, $category, $db->escapeString("zenet"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;




?>