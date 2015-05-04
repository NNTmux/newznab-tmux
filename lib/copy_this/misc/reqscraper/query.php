<?php
require_once('config.php');

$type = isset($_GET["t"]) ? $_GET["t"] : "tv";
$reqid = isset($_GET["reqid"]) ? explode(",",$_GET["reqid"]) : array();
$uid = isset($_GET["newznabID"]) ? $_GET["newznabID"] : "";

//
// query can include multiple comma sep reqids
//
$reqstr = "(";
foreach ($reqid as $req)
	$reqstr.= " reqid = '".mysql_real_escape_string($req)."' or ";
$reqstr.= " 1=2) ";

if ($type != "g")
{
    $result = mysql_query("select ID from feed where '".mysql_real_escape_string($type)."' REGEXP code and status = 1");
    $feedid = -1;
    while ($row = mysql_fetch_assoc($result))
        $feedid = $row["ID"];

    $result = mysql_query("select * from item inner join feed on feed.ID = item.feedID inner join access on access.guid = '".mysql_real_escape_string($uid)."' where ".$reqstr." and item.feedid = '".mysql_real_escape_string($feedid)."'");
}
else
	$result = mysql_query("select * from item inner join feed on feed.ID = item.feedID inner join access on access.guid = '".mysql_real_escape_string($uid)."' and role=2 where feed.name = 'gid' and ".$reqstr);


//
// build metadata about the item(s)
//
$ret = "<items>";
while ($row = mysql_fetch_assoc($result)) 
	$ret.="<item reqid=\"".$row["reqid"]."\" link=\"".cleanXML($row["link"])."\" date=\"".$row["pubdate"]."\" title=\"".cleanXML($row["title"])."\" />\n";
$ret.="</items>";

//
// output xml
//
header("Content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo $ret;
die();


function cleanXML($strin) 
{
	$strout = null;

	for ($i = 0; $i < strlen($strin); $i++) 
	{
		$ord = ord($strin[$i]);

		if (($ord > 0 && $ord < 32) || ($ord >= 127)) 
		{
			$strout .= "&amp;#{$ord};";
		}
		else 
		{
			switch ($strin[$i]) 
			{
				case '<':
					$strout .= '&lt;';
					break;
				case '>':
					$strout .= '&gt;';
					break;
				case '&':
					$strout .= '&amp;';
					break;
				case '"':
					$strout .= '&quot;';
					break;
				default:
					$strout .= $strin[$i];
			}
		}
	}

	return $strout;
}