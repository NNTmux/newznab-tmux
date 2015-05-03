<?php

require_once('magpierss/rss_fetch.inc');
require_once('config.php');

//
// retrieve a list of feeds to be scraped
//
$result = mysql_query("SELECT *, NOW() as now FROM feed WHERE status=1");	
while ($row = mysql_fetch_assoc($result)) 
{
	
	if (strtotime($row['now']) - strtotime($row['lastupdate']) < $row['updatemins']*60) {
		continue;
	}
	
	echo "checking ".$row["code"]."\n";
	$rss = fetch_rss($row["url"]);
	
	$upd = mysql_query("UPDATE feed SET lastupdate = NOW() WHERE ID = ".$row['ID']);
	
	//
	// scrape every item into a database table
	//
	foreach ($rss->items as $item) 
	{
		$link = "";
		if (isset($item['link']))
			$link = mysql_real_escape_string($item['link']);
		
		if (isset($item['description']))
			$description = mysql_real_escape_string($item['description']);	
		elseif (isset($item['summary']))
			$description = mysql_real_escape_string($item['description']);	
		else
			$description = "";
			
		$feedID = $row["ID"];
		
		if (isset($item['pubdate']))
			$pubdate = date("Y-m-d H:i:s", strtotime($item['pubdate']));
		elseif (isset($item["dc"]) && isset($item["dc"]["date"]))
			$pubdate = date("Y-m-d H:i:s", strtotime($item["dc"]["date"]));
		else
			$pubdate = date("Y-m-d H:i:s");
		
		//
		// store 'specific stuff' like parsed reqids by regexing
		//
		$reqid = 0;
		$matches = "";
	
		$title = "";
		if (preg_match($row["titleregex"], $item[$row["titlecol"]], $matches))
			$title = mysql_real_escape_string($matches["title"]);	
					
		//straight md5
		if ($row["reqidregex"] == "-1" && $title != "")
		{
			$reqid = md5($title);
		}
		//regex reqid out of columns
		else
		{
            //multi dimensional position
            $multi = strpos($row["reqidcol"], ':');
            if ($multi !== FALSE)
            {
                $part1 = substr($row["reqidcol"], 0, $multi);
                $part2 = substr($row["reqidcol"], $multi + 1);

                if (preg_match($row["reqidregex"], $item[$part1][$part2], $matches))
                    $reqid = mysql_real_escape_string($matches["reqid"]);
            }
            else
            {
			    if (preg_match($row["reqidregex"], $item[$row["reqidcol"]], $matches))
				    $reqid = mysql_real_escape_string($matches["reqid"]);
            }
		}

		if (isset($item['guid']))
			$guid = mysql_real_escape_string($item['guid']);	
		else
		{
			if ($title != "" && $reqid != 0)
				$guid = md5($reqid.$title);
			else
				$guid = md5(uniqid());	
		}

        $addateunique = round(microtime(true) * 1000);
		$res = mysql_query("INSERT INTO item (feedID, reqid, title, link, description, pubdate, guid, adddate, adddateunique) VALUES ($feedID, '$reqid', '$title', '$link', '$description', '$pubdate', '$guid', NOW(), '$addateunique') ON DUPLICATE KEY update reqid = '$reqid', title = '$title'");
	}
}