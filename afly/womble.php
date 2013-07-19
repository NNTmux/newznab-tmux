<?php

require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR ."/lib/framework/db.php");
require_once(WWW_DIR ."/lib/util.php");
require_once ("hashcompare.php");

//This script is adapted from nZEDb predb.php script

//function Womble ()
//{
		$db = new DB();
		$newnames = 0;

		$buffer = getUrl("http://www.newshost.co.za");

        echo "Requesting pre info from womble ...\n";

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
							$oldname = $db->queryOneRow(sprintf("SELECT releasename, source, ID FROM prehash WHERE releasename = %s", $db->escapeString($matches2["title"])));
							if ($oldname["releasename"] == $matches2["title"])
							{
								if ($oldname["source"] == "womble")
                                {
									continue;
                                }

							else
								{
									if (!isset($matches2["size1"]) && empty($matches["size1"]))
										$size = "NULL";
									else
										$size = $db->escapeString($matches2["size1"].$matches2["size2"]);

								   	if ($matches2["nfo"] == "")
										$nfo = "NULL";
									else
										$nfo = $db->escapeString("http://nzb.isasecret.com/".$matches2["nfo"]);

									$db->query(sprintf("UPDATE prehash SET nfo = %s, size = %s, category = %s, predate = FROM_UNIXTIME(".strtotime($matches2["date"])."), adddate = now(), source = %s where ID = %d", $nfo, $size, $db->escapeString($matches2["category"]), $db->escapeString("womble"), $oldname["ID"]));
									$newnames++;
								}
							}
							else
							{
								if (!isset($matches2["size1"]) && empty($matches["size1"]))
									$size = "NULL";
								else
									$size = $db->escapeString($matches2["size1"].$matches2["size2"]);

								if ($matches2["nfo"] == "")
									$nfo = "NULL";
								else
									$nfo = $db->escapeString("http://nzb.isasecret.com/".$matches2["nfo"]);

								$db->query(sprintf("INSERT IGNORE INTO prehash (releasename, nfo, size, category, predate, adddate, source, hash) VALUES (%s, %s, %s, %s, FROM_UNIXTIME(".strtotime($matches2["date"])."), now(), %s, %s)", $db->escapeString($matches2["title"]), $nfo, $size, $db->escapeString($matches2["category"]), $db->escapeString("womble"), $db->escapeString(md5($matches2["title"]))));
								$newnames++;
							}
						}
					}
				}
			}
		}
		return $newnames;


//   }


?>