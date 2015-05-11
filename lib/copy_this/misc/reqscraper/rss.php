<?php
require_once('./config.php');

$type = isset($_GET["t"]) ? $_GET["t"] : "";
$limit = isset($_GET["limit"]) ? $_GET["limit"] : "100";
$uid = isset($_GET["newznabID"]) ? $_GET["newznabID"] : "";
if ($limit > 100 || !is_numeric($limit)) $limit = 100;

if ($type == "")
{
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<error>no group specified</error>\n";
	die();
}

if ($type != "g")
{
    $result = mysql_query("select ID from feed where '".mysql_real_escape_string($type)."' REGEXP code");
    $feedid = -1;
    while ($row = mysql_fetch_assoc($result))
        $feedid = $row["ID"];

    $result = mysql_query("select item.*, feed.* from item join ( select ID from item where feedID = '".mysql_real_escape_string($feedid)."' order by adddateunique desc limit ".$limit." ) x on x.ID = item.ID inner join feed on feed.ID = item.feedID inner join access on access.guid = '".mysql_real_escape_string($uid)."' order by item.id desc");
}
else
    $result = mysql_query("select item.*, feed.* from item join ( select item.ID from item inner join access on access.guid = '".mysql_real_escape_string($uid)."' and item.feedid != coalesce(access.misc, -1) and role=2 where feedid in (select id from feed where name = 'gid') ORDER BY item.adddateunique DESC LIMIT ".$limit."  ) x on x.ID = item.ID inner join feed on feed.ID = item.feedID order by item.ID desc");

if (!$result)
{
	header("Content-type: text/xml");
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
	echo "<error>general error</error>";
	die();
}

//
// build metadata about the item(s)
//
$xml = "";
while ($row = mysql_fetch_assoc($result))
{
	//create the xml
	$xml .= "\t<item>\n";
	$xml .= "\t\t<title>".htmlentities($row['title'])."</title>\n";
	$xml .= "\t\t<pubDate>".date('r', strtotime($row['adddate']))."</pubDate>\n";
    $xml .= "\t\t<hash>".htmlentities($row['reqid'])."</hash>\n";
    $xml .= "\t\t<category>".htmlentities($row['code'])."</category>\n";
	$xml .= "\t\t<link>".htmlentities($row['link'])."</link>\n";
	if ($row['description']!= "")
        $xml .= "\t\t<description><![CDATA[".$row['description']."]]></description>\n";
	$xml .= "\t\t<guid>".htmlentities($row['guid'])."</guid>\n";
	$xml .= "\t</item>\n";
}

//
// build the xml
//
$xmlstart = "
<rss version=\"2.0\">
<channel>
<title>".mysql_real_escape_string(htmlentities($type))." feed</title>
<link>http://www.newznab.com</link>
<description>".mysql_real_escape_string(htmlentities($type))." feed</description>
<language>en-us</language>\n";

$xmlend = "</channel>\n</rss>";

$ret = $xmlstart . $xml . $xmlend;

//
// output xml
//
header("Content-type: text/xml");
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo $ret;