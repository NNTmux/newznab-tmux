<?php
require_once("config.php");
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/tvrage.php");
require_once(WWW_DIR."/lib/amazon.php");
require_once(WWW_DIR."/lib/anidb.php");
require_once(WWW_DIR."/lib/nzbinfo.php");
require_once(WWW_DIR."/lib/nzb.php");
require_once(WWW_DIR."/lib/book.php");
require_once(WWW_DIR."/lib/music.php");

$qualities = array('(:?..)?tv', '480[ip]?', '640[ip]?', '720[ip]?', '1080[ip]?', 'ac3', 'audio_ts', 'avi', 'bd[\- ]?rip', 'bd25', 'bd50', 'bdmv', 'blu ?ray', 'br[\- ]?disk', 'br[\- ]?rip', 'cam', 'cam[\- ]?rip', '\bdc\b', 'directors.?cut', 'divx\d?', '\bdts', 'dvd', 'dvd[\- ]?r', 'dvd[\- ]?rip', 'dvd[\- ]?scr', 'extended', 'hd', 'hd[\- ]?tv', 'h264', 'hd[\- ]?cam', 'hd[\- ]?ts', 'iso', 'm2ts', 'mkv', 'mpeg(:?\-\d)?', 'mpg', 'ntsc', 'pal', 'proper', 'ppv', 'ppv[\- ]?rip', 'r\d{1}', 'repack\b', 'repacked', 'scr', 'screener', 'tc', 'telecine', 'telesync', 'ts', 'tv[\- ]?rip', 'unrated', 'video_ts', 'video ts', 'x264', 'xvid');

define("DEBUGGING", false);

function findbookid ($author, $title)
{
	global $db;

	$bookID = 0;

	$book = new Book();

	$author = stripname($author);
	$title = stripname($title);

	$tmp = explode(",", $author);

	if (count($tmp) > 1)
		$author = trim($tmp[1]." ".$tmp[0]);

	$rel = $book->getBookInfoByName($author, $title);

	echo "book1 ";
	var_dump($rel);

	if ($rel === false)
	{
		$query = "Select * from `bookinfo` WHERE `title` LIKE '%".$title."%'  AND ID in (SELECT ID  FROM `bookinfo` WHERE `author` sounds LIKE '".$author."')";
		$rel = $db->query($query);

		echo "book2 ";
		var_dump($rel);

		if (isset($rel[0]["ID"]))
		{
			$bookID = $rel[0]["ID"];
		} else {

			$bookID = $book->updateBookInfo($author, $title);
		}
	} else {
		$bookID = $rel['ID'];
	}
	unset($book);
	echo "book ".$bookID."\n";
	return $bookID;
}


function findmusicname($row, $cat = 3010)
{
	global $db;
	$ok = false;

	$query = "SELECT * FROM releases WHERE ( releases.passwordstatus < 0 AND releases.haspreview < 0 ) AND ID = ".$row['ID'];
	$rel = $db->queryOneRow($query);

	if ($rel !== false)
		return false;

	$query = "SELECT releasevideo.releaseID FROM releasevideo WHERE releasevideo.releaseID = ".$row['ID'];
	$rel = $db->queryOneRow($query);

	if ($rel !== false)
		return false;

	$query = "SELECT releasefiles.releaseID, releasefiles.`name` FROM releasefiles INNER JOIN releases ON releasefiles.releaseID = releases.ID WHERE releases.ID = ".$row['ID']." order by releasefiles.`name`";
	$rel = $db->query($query);

	$name = '';
	$ok = false;

	$m3u = '';
	$alt = '';
	$mp3 = false;
	$mp3name = '';
	$files = 0;
	$extras = 0;
	$folder = '';
	$bookId = 0;
	$musicId = 0;

	if (count($rel) > 0)
	{
		echo "findmusicname ".$row['ID']." ".count($rel)."\n";
		foreach($rel as $r)
		{
			$sub = $r['name']."\n";

			$sub = preg_replace('/[\x01-\x1f]/', '', $sub);

			$sub = preg_replace('/(?<!\d|\b[a-z]\b)\.(?!(.{3,4}$)|\b[a-z]\b)/', ' ', $sub);

			$a = preg_replace('/[\/\_]/', ' ', $row['name']);

			$sub = preg_replace("/".preg_quote($a)."/i",'', $sub);

			if ($folder != '')
			{
				$sub = preg_replace("/".preg_quote($folder)."/i",'', $sub);
				$sub = preg_replace("/^\\\\/i",'', $sub);
			}

//			$sub = preg_replace("/^([^\\\\]+?)[^\\\\]*[\\\\](.*\1)/Ui",'\\2', $sub);

			echo "doing music file = $sub\n";

			if (preg_match('/(\nfo\b|sfv)/iU', $sub))
			{
				$extras++;
				$alt = preg_replace('/(\.[a-z][a-z0-9]{2})+?$/iU', '', $sub);
			} elseif (!preg_match('/(\.[a-z][a-z0-9]{2,3}?$)/iU', $sub)) {
				$extras++;
				$folder = preg_replace('/\\\\$/', '', $sub);
			}

			if (preg_match('/\.mp3|\.flac/', $sub, $matches))
			{
				$mp3name = preg_replace('/(?iU)^[^\"]+\"(0\d+?-?(00)??)??/iU', '', $sub);

				$mp3 = true;
				$files++;
			}

			if (preg_match('/\.m3u|\"00+[ \-\_\.]+?|\.nfo\b|\.sfv/iU', $sub, $matches))
			{
				if (preg_match('/\.url|playlist/iU', $sub))
				{
					continue;
				}
				$sub = preg_replace('/(\.vol\d{1,3}?\+\d{1,3}?|\.par2|\.[a-z][a-z0-9]{2})+?/iU', '', $sub);

				$m3u = preg_replace('/00+?[\-\.](.+[a-z][a-z0-9]{2,3})$/iU', '', $sub);
			}
		}
	$name = '';


	echo "folder $folder\n";

	echo "$m3u $mp3name\n";

	echo (($m3u != '' || ($files + $extras) / count($rel) > 0.7) && $mp3)." ".$mp3." ".$m3u."\n";

	if (($m3u != '' || (($files + $extras) / count($rel) > 0.7)) && $mp3)
	{
		$name = $m3u;

		if ($files == 1)
			$name = $mp3name;

		if (empty($name))
		{
			$name = matchnames($row['name'], $alt);
		}

		echo $row['guid']." ".$name.' '.$mp3.' '.($files + $extras) / count($rel)."\n";
	}


	//		if (preg_match('/\.mp3|\.m3u|\.sfv|\.nfo|\.flac/|\.asf/', $r['name']))
	//			echo "release file ".$r['name']."\n";

		$name = preg_replace("/^\d+\-/i", '', $name);
		$name = trim(preg_replace("/\\\\|^  +|  +$/i", ' ', $name));

echo "file name $name\n";

	} else {
		$name = domusicfiles ($row);
	}

echo "found name $name\n";

	if ($name != '')
	{
		$music = new Music();
		$result = $music->parseArtist($name);
		$artist = $result['artist'];
		$album = $result['album'];

		if (empty($album) && empty($artist))
			$album = $name;

echo "1artist = $artist album = $album\n";

		$album = stripname($album);
		$artist = stripname($artist);

		$artist = preg_replace('/\d+/', '', $artist);

		$tmp = explode(",", $artist);

		if (count($tmp) > 1)
			$artist = trim($tmp[1]." ".$tmp[0]);


echo "2artist = $artist album = $album\n";

		$rel = $music->getMusicInfoByName($artist, $album);
//	var_dump($rel);
		$musicId = $rel['ID'];

		if ($musicId <= 1)
		{
			echo "amazon music1 ";
			$musicId = $music->updateMusicInfo($artist, $album, 0);

			if ($musicId === false)
			{
// 				$book = new Book(true);
// 				$bookId = $book->updateBookInfo($artist, $album);
// 				unset($book);
				$bookId = findbookid ($artist, $album);
			}
//var_dump($musicId);
		}

if (preg_match('/\.flac/', $mp3name))
		$cat = 3040;

echo "music name: $name $musicId $bookId\n";
		if ($musicId > 1)
		{
			$ok = dodbupdate($row['ID'], $cat, $name, $musicId, 'music');
		}  else if ($bookId > 0) {
			$ok = dodbupdate($row['ID'], 3030, $name, $bookId, 'book');
		}   else {
			if (preg_match('/book/i', $row['gname']))
				$ok = dodbupdate($row['ID'], 3030, $name);
			else
				$ok = dodbupdate($row['ID'], $cat, $name);
		}

		unset($music);
	}
	return $ok;
}

function stripname($name)
{
	if (is_array($name))
		return $name;

	global $qualities;

	$quality = preg_replace('/(.+?)/iU', "/\b\\1\b.*?$/iU", $qualities);

//	var_dump($quality);

	$name1 = $name;

	do {
		$original = $name1;
//	echo "fm1: $name1\n";
		$name1 = preg_replace($quality, " ", $name1);
		$name1 = preg_replace('/(as )?(requested|request|req):?/i', " ", $name1);
		$name1 = preg_replace('/Thanks To Original Poster/i', " ", $name1);
		$name1 = preg_replace('/\b[a-z]{1,2}[0-9]{1,2} /i', " ", $name1);
		$name1 = preg_replace('/^\d+|\d+$/', '', $name1);
		$name1 = preg_replace('/\battn:? [^ ]+\b/i', " ", $name1);
		$name1 = preg_replace('/^National.?Geographic|^nat.?geo|^bbc/i', " ", $name1);
		$name1 = preg_replace("/\.?(s\d+?e\d+?)\.?/iU", " \\1 ", $name1);
		$name1 = preg_replace("/^(\[\d+\].\[.+\].(\[#?a.b.[^\]]+\])*?.)/iU", "", $name1);
		$name1 = preg_replace("/\[(?![^\[]+\])|\((?![^\(]+\))|\{(?![^\{]+\})/iU", " ", $name1);
		$name1 = preg_replace("/^\(?\?+?\)/iU", " ", $name1);
		$name1 = preg_replace("/[\x01-\x1f\_\!\?\[\{\}\]\/\:\|]/iU", " ", $name1);
		$name1 = preg_replace("/(?<![a-z0-9])(\.)(?!\.[0-9])/iU", " ", $name1);
		$name1 = preg_replace("/(?<=[a-z])(\.)(?!\.[0-9]|\b[a-z]\b)/iU", " ", $name1);
		$name1 = preg_replace("/[\[\{\(]??\d{1,4}?\/\d{1,4}?[\]\}\)]??/iU", "", $name1);
		$name1 = preg_replace("/^[\?\)\,\.\- \"\']+|[\,\(\?\.\- \"\']+$/iU", "", $name1);
		$name1 = preg_replace("/  +?/iU", " ", $name1);
		$name1 = trim($name1);
//	echo "fm2: $name1\n";
	} while ($original != $name1);

echo "stripname $name1\n";
echo cleanname($name1)."\n";

	return $name1;
}

function lookupTV ($namebase = '', $nfo, $row, $cat = 5050)
{
	global $db;

	$rageID = false;

	$ok = false;

	$tmp = array();

	$name = $namebase;

	$name = stripname($name);

	$tvrage = new TvRage();
	if ($name != '')
	{
		$name = cleanname($name);

		echo "title = ".$name."\n";

		$showinfo = $tvrage->parseNameEpSeason($name);

		if (empty($showinfo['cleanname']))
		{
			$showinfo['name'] = $name;
			$showinfo['cleanname'] = $name;
		}

echo "clean ".$showinfo['cleanname']."\n";

		$rageID = $tvrage->getByTitle($showinfo['cleanname']);

	}

//	echo "1 $name\n";

	if ($rageID == false)
	{
		$rageID = $tvrage->parseRageIdFromNfo($nfo);

		$name1 = $tvrage->getRageInfoFromService($rageID);
//	var_dump($name1);
		if (isset($name1['name']) && $name1['name'] != '')
			$name = $name1['name'];
		else
		{
			if (!isset($showinfo['cleanname']))
				$showinfo = $tvrage->parseNameEpSeason($name);
				$showInfo1 = array(
					'name' => '',
					'season' => '',
					'episode' => '',
					'seriesfull' => '',
					'airdate' => '',
					'country' => '',
					'year' => '',
					'cleanname' => ''
				);
				foreach ($showInfo1 as $k => $v)
					if (isset($showinfo[$k]))
						$showInfo1[$k] = $showinfo[$k];
				$showinfo = $showInfo1;
	echo "calling rage $name\n";
//	var_dump($showinfo);
			try {
				$tvrShow = $tvrage->getRageMatch($showinfo);
//	var_dump($tvrShow);
				if (isset($tvrShow["showid"]))
					$rageID = $tvrShow["showid"];
//	echo "$rageID \n";
			} catch (Exception $e) {
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
		}
		if (isset($tvrShow) && $rageID > 0)
			$tvrage->updateRageInfo($rageID, $showinfo, $tvrShow, null);
	}

//	echo "2 $name\n";

	if ($rageID == false)
	{
		$query = "SELECT * FROM releases WHERE ( releases.passwordstatus < 0 AND releases.haspreview < 0 ) AND ID = ".$row['ID'];
		$rel = $db->queryOneRow($query);

		if ($rel !== false)
			return false;

		$query = "SELECT releasevideo.releaseID FROM releasevideo WHERE releasevideo.releaseID = ".$row['ID'];
		$rel = $db->queryOneRow($query);

		if ($rel === false)
			return false;

		$query = "SELECT releasefiles.releaseID, releasefiles.`name` FROM releasefiles INNER JOIN releases ON releasefiles.releaseID = releases.ID WHERE releases.ID = ".$row['ID'];
		$rel = $db->query($query);

		foreach($rel as $r)
		{
			$name1 = preg_replace('/(?<!\d)[\.\_](?!\b[a-z]\b)/', ' ', $r['name']);

//			echo $name1."\n";

			$parsed = $tvrage->parseNameEpSeason($name1);

//			var_dump($parsed);

			$rageID = $tvrage->getByTitle($parsed['cleanname']);

			if ($rageID == false)
			{
				if ($parsed)
					$name1 = $parsed['cleanname'];
				$sql = sprintf("SELECT rageID from tvrage where (releasetitle = %s or releasetitle = %s)", $db->escapeString($name1), $db->escapeString(str_replace(' and ', ' & ', $name1)));

				$sql = str_replace(" = '", " like '%", $sql);
				$sql = str_replace("' or", "%' or", $sql);
				$sql = str_replace("')", "%')", $sql);

//				echo "$sql\n";

				$res = $db->queryOneRow($sql);
				if ($res)
					$rageID = $res["rageID"];
			}
		}
		if ($rageID != 0 && $name1 != '')
			$name = $name1;
	}

	$tmp = $tvrage->getByRageID($rageID);

//	echo "ltvrageid = $rageID\n";
//	var_dump($tmp);

	if (!isset($tmp[0]))
	{

		$name = preg_replace('/[\_\.]|bbc|nat.?geo|national.?geographic/i', " ", $name1);
//		$name = preg_replace('/(19|20)\d{2}.*$/i', " ", $name);
		$name = preg_replace('/\d{1,3} ?of ?\d{1,3}.*$/i', " ", $name);
		$name = preg_replace('/^[\]\}\)]|[\[\{\(]/i', "", $name);
		$name = trim($name);

echo "name == $name\n";
		$showinfo = $tvrage->parseNameEpSeason($name);
		if (!isset($showinfo['cleanname']))
			$showinfo = array(
				'name' => $name,
				'season' => '',
				'episode' => '',
				'seriesfull' => '',
				'airdate' => '',
				'country' => '',
				'year' => '',
				'cleanname' => $name
			);



		try {
			$tvrShow = $tvrage->getRageMatch($showinfo);
			if (isset($tvrShow["showid"]))
				$rageID = $tvrShow["showid"];
		} catch (Exception $e) {
			echo 'Caught exception: ',  $e->getMessage(), "\n";
		}
//	var_dump($tvrShow);
		if ($tvrShow === false)
		{
			$name1 = preg_replace('/(\D+?)( s?\d{1,3}?(?!\d).*?)/iU', '\1', $name);
			$showinfo = $tvrage->parseNameEpSeason($name1);
			if (!isset($showinfo['cleanname']))
				$showinfo = array(
					'name' => $name1,
					'season' => '',
					'episode' => '',
					'seriesfull' => '',
					'airdate' => '',
					'country' => '',
					'year' => '',
					'cleanname' => $name1
				);
echo "$name1\n";
			$tvrShow = $tvrage->getRageMatch($showinfo);
//	var_dump($tvrShow);
			if ($tvrShow !== false)
			{
				if (isset($tvrShow["showid"]))
					$rageID = $tvrShow["showid"];
			}
		}

		if (($tvrShow !== false && is_array($tvrShow) && $tvrShow['title'] == $name) || $rageID)
		{
			$showinfo['name'] = $tvrShow["title"];
			$showinfo['cleanname'] = $tvrShow["title"];
			if ($rageID != 0)
				$tvrage->updateRageInfo($rageID, $showinfo, $tvrShow, NULL);
		}
		$tmp = $tvrage->getByRageID($rageID);
	}

	if (isset($tmp[0]))
	{
//	echo "rageID = $rageID\n";
//	echo "title = ".$tmp[0]['releasetitle']."\n";
//		var_dump($tmp);

		$show = $tvrage->parseNameEpSeason($tmp[0]["releasetitle"]." ".$row['searchname']);
// 		var_dump($tvrage->getEpisodeInfo($rageID, $show['season'], $show['episode']));

		$name = matchnames($row['name'], $tmp[0]['releasetitle']." ".$show['seriesfull']);

		$name =  findquality ($nfo, $name);

		if (dodbupdate($row['ID'], $cat, $name, $rageID, 'tv'))
		{
			if (is_array($show) && $show['name'] != '')
			{
	//			$tvrage->updateEpInfo($show, $row['ID']);
			}
			$ok = $rageID;
		}
	}

	unset($tvrage);
	return $ok;
}

function cleanname($name)
{
	global $qualities;

	$quality = preg_replace('/(.+?)/iU', "/\.*?(\b\\1\b)\.*?/iU", $qualities);

	do {
		$original = $name;
//	echo "CN: $name\n";
		$name = preg_replace($quality, " \\1 ", $name);
		$name = preg_replace("/\.?(s\d+?e\d+?)\.?/iU", " \\1 ", $name);
		$name = preg_replace("/yenc/iU", " ", $name);
		$name = preg_replace("/^(\[\d+\].\[.+\].(\[#?a.b.[^\]]+\])*?.)/iU", "", $name);
		$name = preg_replace("/\[(?![^\[]+\])|\((?![^\(]+\))|\{(?![^\{]+\})/iU", " ", $name);
		$name = preg_replace("/^\(?\?+?\)/iU", " ", $name);
		$name = preg_replace("/[\x01-\x1f\_\!\?\[\{\}\]\/\:\|]/iU", " ", $name);
		$name = preg_replace("/(?<![a-z0-9])(\.)(?!\.[0-9])/iU", " ", $name);
		$name = preg_replace("/(?<=[a-z])(\.)(?!\.[0-9]|\b[a-z]\b)/iU", " ", $name);
		$name = preg_replace("/[\[\{\(]??\d{1,4}?\/\d{1,4}?[\]\}\)]??/iU", "", $name);
		$name = preg_replace("/^[\?\)\,\.\- ]+|[\,\(\?\.\- ]+$/iU", "", $name);
		$name = preg_replace("/  +?/iU", " ", $name);
		$name = trim($name);

	//	echo "$original * O* *N * $name\n";

	} while ($original != $name);
	$name = mb_convert_case($name, MB_CASE_TITLE, "UTF-8");

//	echo "CN2: $name\n";

	$tmp = explode(" ", $name);

	if (!stripos($name, ' -*- '))
		for ($i = 0; $i < count($tmp); $i++) {
			for ($j = $i +3; $j < count($tmp); $j++) {
				if ($tmp[$i] == $tmp[$j])
					$tmp[$j] = '';
			}
		}

	$name = implode(' ', $tmp);
	$name = preg_replace("/  +?/iU", " ", $name);
	$name = trim($name);

//	echo "CN3: $name\n";

	return $name;
}
function matchnames($old, $new)

{
	if (is_array($old))
		$old = '';

	$new = cleanname($new);
	$old = cleanname($old);

	$new = preg_quote($new);
	$old = preg_quote($old);

//	echo "new = $new\n";
//	echo "old = $old\n";

	$arr = preg_split('/[\s\(\)\_\\\\]/', $new, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);

	if (str_word_count($old) < 2)
	{
		echo "una palabra ".$old."\n";
	}

	$str = $old;
	$i = 0;

	$str = preg_replace('/[\s\(\)\_\\\\]/', ' ', $str);


//	echo "$str\n";
//	echo implode($arr, " ")."\n";

	foreach ($arr as $r)
	{
		if (preg_match('/^[\-\*]$/', $r))
			continue;
//	echo "$str $r\n";
		$str = preg_replace("/\b".preg_quote($r)."\b/i", " ", $str);
	}

	$str = trim($new." ".$str);

	do {
		$str1 = $str;
		$str = str_ireplace("\\", "", $str);
		$str = preg_replace("/([\{\[\(] ?)?( ?[\]\}\)])?/iU", "", $str);
		$str = preg_replace("/ [0-9a-f]{5,}$/iU", "", $str);
		$str = preg_replace("/  +/", " ", $str);
		$str = trim($str);
	} while ($str != $str1);

//	echo "CNF $str\n";
	return $str;
}

function updateTV ($name, $id, $row, $cat = 5000, $nfo = '')
{
	global $db, $tvrage;

	$ok = false;

	$set = preg_split('/(?:\btitle|\bname).+[\.\s]+?[^a-z0-9]*?([a-z0-9\-\.\_ ]+?)/iU',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

//	var_dump($set);

	$name1 = $name;

	if (isset($set[1]))
	{
		$name1 = $set[1];
	}

	$name1 = preg_replace('/(?<=[a-z])(\.)(?!\.[0-9]|\b[a-z]\b)/iU', ' ', $name1);
	$name1 = preg_replace('/  +/iU', ' ', $name1);
	$name1 = trim($name1);

	$rageID = lookupTV ($name1, $nfo, $row, $cat);

	if ($rageID != 0)
	{
		$tvrage = new TVRage(true);
		$name1 = $tvrage->getByRageID($rageID);
		unset($tvrage);

		$name1 = $name1[0]["releasetitle"];

		$name = matchnames($row['name'], $name1);

	echo "doing tv1 ".$name." ".$row['guid']."\n";

		$ok = dodbupdate($id, $cat, $name, $rageID, 'tv');

	} else {

//echo "$name $name1\n";

		$name = matchnames($row['name'], $name1);

	echo "doing tv2 ".$name." ".$row['guid']."\n";

//		$matches = preg_split('/(\btitle\:|\bname=)[ \.\_]*?(.*)\r/iU',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);

//		echo $matches[1]."\n";

		if (empty($name))
		{
			$ok = dodbupdate($id, $cat, '');
		} else {
			$ok = dodbupdate($id, $cat, $name);
		}
	}
	return $ok;
}

function doAmazon ($name, $id, $nfo = "", $q, $region = 'com', $case = false, $nfo ='', $row = '')
{
	global $db;
	$s = new Sites();
	$site = $s->get();
	$amazon = new AmazonProductAPI($site->amazonpubkey, $site->amazonprivkey, $site->amazonassociatetag);
	$ok = false;


	try {
		switch ($case)
		{
			case 'upc':
				$amaz = $amazon->getItemByUpc(trim($q), $region);
				break;
			case 'asin':
				$amaz = $amazon->getItemByAsin(trim($q), $region);
				break;
			case 'isbn':
				$amaz = $amazon->searchProducts(trim($q), '', "ISBN");
				break;
		}
	} catch (Exception $e) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
		unset($s);
		unset($amaz);
		unset($amazon);
		return $ok;
	}

	if (!isset($amaz->Items->Item))
		return $ok;

	$type = $amaz->Items->Item->ItemAttributes->ProductGroup;

	switch ($type)
	{
		case 'Book':
		case 'eBooks':
			$new = (string) $amaz->Items->Item->ItemAttributes->Author;
			$new = $new . " - ". (string) $amaz->Items->Item->ItemAttributes->Title;
			$name = matchnames($name, $new);

			$query = "SELECT ID  FROM `bookinfo` WHERE `asin` = '".(string) $amaz->Items->Item->ASIN."'";
			$rel = $db->query($query);
			if (count($rel) == 0)
			{
				$book = new Book();
				$item = array();
				$item["asin"] = (string) $amaz->Items->Item->ASIN;
				$item["url"] = (string) $amaz->Items->Item->DetailPageURL;
				$item["coverurl"] = (string) $amaz->Items->Item->LargeImage->URL;
				if ($item['coverurl'] != "")
					$item['cover'] = 1;
				else
					$item['cover'] = 0;
				$item["author"] = (string) $amaz->Items->Item->ItemAttributes->Author;
				$item["dewey"] = (string) $amaz->Items->Item->ItemAttributes->DeweyDecimalNumber;
				$item["ean"] = (string) $amaz->Items->Item->ItemAttributes->EAN;
				$item["isbn"] = (string) $amaz->Items->Item->ItemAttributes->ISBN;
				$item["publisher"] = (string) $amaz->Items->Item->ItemAttributes->Publisher;
				$item["publishdate"] = (string) $amaz->Items->Item->ItemAttributes->PublicationDate;
				$item["pages"] = (string) $amaz->Items->Item->ItemAttributes->NumberOfPages;
				$item["title"] = (string) $amaz->Items->Item->ItemAttributes->Title;
				$item["review"] = "";
				if (isset($amaz->Items->Item->EditorialReviews))
					$item["review"] = trim(strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content));

				$bookId = $book->addUpdateBookInfo($item['title'], $item['asin'], $item['url'],
								$item['author'], $item['publisher'], $item['publishdate'], $item['review'],
								$item['cover'], $item['dewey'], $item['ean'], $item['isbn'], $item['pages'] );
				unset($book);
			} else {
				$bookId = $rel[0]['ID'];
			}


			$query = "SELECT * FROM releases INNER JOIN releaseaudio ON releases.ID = releaseaudio.releaseID WHERE releases.ID = $id";
			$rel = $db->query($query);
			if (count($rel) == 0)
			{
				$ok = dodbupdate($id, 7020, $name, $bookId, 'book');
			} else {
				$ok = dodbupdate($id, 3030, $name, $bookId, 'book');
			}
			unset($rel);
			break;

		case 'Digital Music Track':
		case 'Digital Music Album':
		case 'Music':
			$new = (string) $amaz->Items->Item->ItemAttributes->Artist;
			if ($new != '')
				$new = $new .  " - ";
			$new = $new .(string) $amaz->Items->Item->ItemAttributes->Title;
			$name = matchnames($name, $new);

			$query = "SELECT *  FROM `bookinfo` WHERE `asin` = '".(string) $amaz->Items->Item->ASIN."'";

//	echo "$query\n";
			$rel = $db->query($query);
			if (count($rel) == 0)
			{
				$mus = array();
				$mus['coverurl'] = (string) $amaz->Items->Item->LargeImage->URL;
				if ($mus['coverurl'] != "")
					$mus['cover'] = 1;
				else
					$mus['cover'] = 0;

				$mus['title'] = (string) $amaz->Items->Item->ItemAttributes->Title;

				$mus['asin'] = (string) $amaz->Items->Item->ASIN;

				$mus['url'] = (string) $amaz->Items->Item->DetailPageURL;

				$mus['salesrank'] = (string) $amaz->Items->Item->SalesRank;
				if (empty($mus['salesrank']))
					$mus['salesrank'] = 'null';

				$mus['artist'] = (string) $amaz->Items->Item->ItemAttributes->Artist;

				$mus['publisher'] = (string) $amaz->Items->Item->ItemAttributes->Publisher;

				$mus['releasedate'] = $db->escapeString((string) $amaz->Items->Item->ItemAttributes->ReleaseDate);
				if ($mus['releasedate'] == "''")
					$mus['releasedate'] = 'null';

				$mus['review'] = '';
				if (isset($amaz->Items->Item->EditorialReviews))
					$mus['review'] = trim(strip_tags((string) $amaz->Items->Item->EditorialReviews->EditorialReview->Content));

				$mus['year'] = ($mus['releasedate'] != 'null' ? substr($mus['releasedate'], 1, 4) : date("Y"));

				$mus['tracks'] = '';
				if (isset($amaz->Items->Item->Tracks))
				{
					$tmpTracks = (array) $amaz->Items->Item->Tracks->Disc;
					$tracks = $tmpTracks['Track'];
					$mus['tracks'] = (is_array($tracks) && !empty($tracks)) ? implode('|', $tracks) : '';
				}

				$music = new Music();
				$musicId = $music->addUpdateMusicInfo($mus['title'], $mus['asin'], $mus['url'],
					$mus['salesrank'], $mus['artist'], $mus['publisher'], $mus['releasedate'], $mus['review'],
					$mus['year'],'', $mus['tracks'], $mus['cover'] );

				unset($music);
			} else {
				$musicId = $rel[0]['ID'];
			}

			$ok = dodbupdate($id, 3010, $name, $musicId, 'music');
			break;

			case 'Movies':
			case 'DVD':
			$new = (string) $amaz->Items->Item->ItemAttributes->Title;
			$new = $new . " (" . substr((string) $amaz->Items->Item->ItemAttributes->ReleaseDate, 0, 4) . ")";
			$name = matchnames($name, $new);

			$ok = matchimdb ($nfo, $row);

			if (!$ok)
			{
				$name =  findquality ($nfo, $name);
				$ok = dodbupdate($id, 2000, $name);
			}
			break;

		default:
			echo "* * * * * * uncatched amazon category $type ".$name;
			break;
	}
//echo  $query."\n";

	unset($s);
	unset($amaz);
	unset($amazon);
	return $ok;
}

function doarray($matches)
{
	$r = array();

	foreach ($matches as $m)
	{
		if (strlen($m) < 50)
		{
			$str = preg_replace("/\s/iU", "", $m);

			$m = strtolower($str);

			if ($m == 'audiobook') {
				$r[-1] = $m;
			} else if ($m == 'anidb.net') {
				$r[-2] = $m;
			} else if ($m == 'upc') {
				$r[-3] = $m;
			} else if ($m == 'amazon.') {
				$r[-4] = $m;
			}  else if ($m == 'asin') {
				$r[-5] = $m;
			} else if ($m == 'imdb') {
				$r[-6] = $m;
			}  else if ($m == 'os') {
				$r[-7] = $m;
			} else if ($m == 'mac' || $m == 'macintosh' || $m == 'dmg' || $m == 'macos' || $m == 'macosx' || $m == 'osx') {
				$r[-8] = $m;
			}  else if ($m == 'itunes.apple.com/') {
				$r[-9] = $m;
			} else if (preg_match('/sports|deportes|nhl|nfl|\bnba/i', $m)) {
				$r[1000] = $m;
			} else if (preg_match('/avi|xvid|divx|mkv/i', $m)) {
				$r[1001] = $m;
			} else {
				$r[] = $m;
			}
		}
	}

	ksort($r);
	return $r;
}

function doOS ($nfo, $name, $cat, $id)
{
	global $db;

	$ok = false;

	$pattern = '/(.*v(?:er)?[\.\s]*?\d+\.\d(?:\.\d+)??[^\v]*?)|(?<!fine )(?:\btitle|\bname|release)\b(?! type| info(?:rmation)?| date)(?:[\-\:\.\}\[\s\xb0-\x{3000}]+?)\B([a-z0-9\.\- \(\)]+?)/Uui';
	$set = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);

	if (!isset($set[1]) || strlen($set[1]) < 3)
	{
		$pattern = '/(?:(?:presents?|p +r +e +s +e +n +t +s)(?:[^a-z0-9]+?))([a-z0-9 \.\-\_]+?)/Ui';
		$set = preg_split($pattern,  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
	}

	if (isset($set[1]))
	{
		$name = matchnames($name, $set[1]);

		$ok = dodbupdate($id, $cat, $name);

	} else {
		$ok = dodbupdate($id, $cat);
	}

	return $ok;

}

function domusicfiles ($row)
{
	$nzbinfo = new nzbInfo();
	$nzb = new Nzb();


//	echo $row['guid']."\n";

	$file = $nzb->getNZBPath($row['guid']);
	$m3u = '';
	$alt = '';
	$mp3 = false;
	$mp3name = '';
	$files = 0;
	$extras = 0;

	echo "doing music ".$row['guid']."\n";

	if ($nzbinfo->loadFromFile($file))
	{
		$name = $row['name'];

		$name = preg_replace("/\//",' ', $name);

//		echo "$name\n";

		$name = preg_quote($name);


		echo "$name\n";


		foreach($nzbinfo->nzb as $nzbsubject)
		{

			$sub = $nzbsubject['subject']."\n";

			$sub = preg_replace("/$name/i",'', $sub);

//			echo "doing music file = $sub\n";

			if (preg_match('/\.(vol\d{1,3}?\+\d{1,3}?|par2|nfo\b|sfv|par\b|p\d{1,3}?|sv\b)/iU', $sub))
			{
				$extras++;
				$alt = preg_replace('/(\.vol\d{1,3}?\+\d{1,3}?|\.par2|\.[a-z][a-z0-9]{2})+?".+?/iU', '', $sub);
			}

			if (preg_match('/\.mp3|\.flac/', $sub, $matches))
			{
				$mp3name = preg_replace('/(\.mp3".+?)/iU', '.mp3', $sub);
				$mp3name = preg_replace('/(\.flac".+?)/iU', '.flac', $sub);
				$mp3name = preg_replace('/(?iU)^[^\"]+\"(0\d+?-?(00)??)??/iU', '', $mp3name);

				$mp3 = true;
				$files++;
			}

			if (preg_match('/\.m3u|\"00+[ \-\_\.]+?|\.nfo\b|\.sfv/iU', $sub, $matches))
			{
				if (preg_match('/\.url|playlist/iU', $sub))
				{
					continue;
				}
				$sub = preg_replace('/(\.vol\d{1,3}?\+\d{1,3}?|\.par2|\.[a-z][a-z0-9]{2})+?".+?/iU', '', $sub);

				$m3u = preg_replace('/(?iU)^[^\"]+\"(0\d+?-?(00)??)??/iU', '', $sub);
			}

		}
	}
	$name = '';

	if ( count($nzbinfo->nzb) > 0)
	{

		echo (($m3u != '' || ($files + $extras) / count($nzbinfo->nzb) > 0.7) && $mp3)." ".$mp3." ".$m3u."\n";

		if (($m3u != '' || (($files + $extras) / count($nzbinfo->nzb) > 0.7)) && $mp3)
		{
			$name = $m3u;

			if ($files == 1)
				$name = $mp3name;

			if (empty($name))
			{
				$name = matchnames($row['name'], $alt);
			}

			echo $row['guid']." ".$name.' '.$mp3.' '.($files + $extras) / count($nzbinfo->nzb)."\n";

		}

		echo (($m3u != '' || ($files + $extras) / count($nzbinfo->nzb) > 0.7) && $mp3)." ".$mp3." ".$m3u."\n";

	}

echo "music files $name\n";
	$name = cleanname($name);
echo "cleaned name $name\n";
	unset($file);

	unset($nzbinfo);
	unset($nzb);

	return $name;
}

function findquality ($nfo, $name)
{
	global $qualities;
	$qual = array();
//	echo "$name\n";
	foreach ($qualities as $quality)
	{
		if (preg_match("/(?<!\[ \] )(\b".$quality."\b)(?! \[ \])/iU", $nfo, $match))
		{
			$qual[] =$match[1];
		}
	}

	foreach ($qual as $key=>$quality)
	{
		if (preg_match("/$quality/iU", $name))
		{
			unset($qual[$key]);
		}
	}

	$n = '';
	if (count($qual) > 0)
	{
		foreach ($qual as $quality)
		{
			$n = $n ." ".$quality;
		}
	}

	return trim($name .' '.$n);
}

function matchanime ($nfo, $id, $name)
{
	$ok = false;
	$anime = '';

	if (preg_match('/anidb.net.*aid=(\d+?)/iU', $nfo, $set))
	{
		$anidb = new AniDB();
		if ($anidb->getAnimeInfo($set[1]) === false)
		{
			$anidb->addTitle($anidb->AniDBAPI($set[1]));
		}
		$anime = $anidb->getAnimeInfo($set[1]);

		if ($anime !== false)
		{

		$anime = matchnames($name, $anime['title']);

		$ok = dodbupdate($id, 5070, $anime, $set[1], 'anime');
		}
		unset($anidb);

	}
	return $ok;
}

function matchimdb ($nfo, $row)
{
	$ok = false;

	preg_match('/imdb\.[a-z0-9\.\_\-\/]+?(?:tt|\?)(\d+?)\/?/iU', $nfo, $set);

//	echo "imdb name= ".$row['name']."\n";

	if (isset($set[1]))
	{
		$imdb = $set[1] + 0;

		$movie = new Movie();

		$title = $movie->getMovieInfo($imdb);

		if ($title === false)
		{
			$movie->fetchImdbProperties($imdb);
			$movie->updateMovieInfo($imdb);
			$title = $movie->getMovieInfo($imdb);
		}

		$name = $title['title']." (".$title['year'].")";

//		$name = matchnames($row['name'], $name);

	//		echo "quality ".findquality ($nfo, $name)."\n";
		$name = findquality ($nfo, $name);

		$name = matchnames($row['name'], $name);

	echo 	"imdb $name\n";

//	echo 	(strval($imdb) + 0) . " * * * " . (strval($title['imdbID']) + 0) ."\n";

		if ((strval($imdb) + 0) == (strval($title['imdbID']) + 0))
		{
			if (preg_match('/sport/iU', $title['genre'])) {
				$ok = dodbupdate($row['ID'], 5060, $name, $imdb, 'imdb');
			} else if (preg_match('/docu/iU', $title['genre'])) {
				$ok = dodbupdate($row['ID'], 5080, $name, $imdb, 'imdb');
			} else if (preg_match('/talk\-show/iU', $title['genre'])) {
				$ok = dodbupdate($row['ID'], 5000, $name, $imdb, 'imdb');
			} else if (preg_match('/tv/iU', $title['genre']) || preg_match('/episode/iU', $title['genre']) || preg_match('/reality/iU', $title['genre']))	{
				$ok = updateTV ($title['title']." (".$title['year'].")", $row['ID'], $row, 5000, $nfo);
			} else {
				$ok = dodbupdate($row['ID'], 2000, $name, $imdb, 'imdb');
			}
		}
		unset($movie);
	}
//	echo (bool) $ok."\n";
	return $ok;
}

function matchaudiobook ($nfo, $id, $oldname, $row)
{
	global $db;
	$ok = false;

	if (preg_match('/Sample Rate|MP3|speech|Encoded|ID3|(un)?abridged|Copyright|Ripped|khz|stero|mono/i', $nfo))
	{
		if (preg_match('/mpeg|pixels|fps/i', $nfo))
		{
			echo "not audio\n";
			return $ok;
		}
		$query = "SELECT * FROM releases INNER JOIN releasevideo ON releases.ID = releasevideo.releaseID where ( releases.passwordstatus >= 0 OR releases.haspreview >= 0 ) AND releases.ID = ".$id;
		$rel = $db->query($query);
		if (count($rel) > 0)
		{
			echo "not audio\n";
			return $ok;
		}
		unset ($rel);

	echo "audiobooks $id\n";
		$name = $oldname;
		$author = '';
		$title = '';
		$bookID = 0;
		$set = preg_split('/\btitle\:?(\.\.+?|\s\s+?|__+?)([^\r]*)\r/Ui',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);


		if (isset($set[2]))
		{

			$set[2] = trim(preg_replace('/[\|\:\-]|  +/', ' ', $set[2]));

//			echo $set[2]."\n";
			$name =  $set[2];
			$title = $name;

			$set = preg_split('/author\:?(\.\.+?|\s\s+?|__+?)([^\r]*)\r/Ui',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);

			if (isset($set[2]))
			{
				$set[2] = trim(preg_replace('/[\|\:\-]|  +/', ' ', $set[2]));

//				echo $set[2]."\n";
				$name = $set[2]." - ".$name;
				$author = $set[2];
			}

			$bookID = findbookid ($author, $title);
//
// 			$book = new Book();
// 			$rel = $book->getBookInfoByName($author, $title);
//
// 			if ($rel === false)
// 			{
// 				$query = "Select * from `bookinfo` WHERE `title` LIKE '%".$title."%'  AND ID in (SELECT ID  FROM `bookinfo` WHERE `author` sounds LIKE '".$author."')";
// 				$rel = $db->query($query);
// //	echo "$query\n";
//
// 				if (isset($rel[0]["ID"]))
// 				{
// //	var_dump($rel);
// 					$bookID = $rel[0]["ID"];
// 				} else {
// //	echo "not found\n";
// 					$bookID = $book->updateBookInfo($author, $title);
// //	echo "$bookID\n";
// 				}
// 			} else {
// 				$bookID = $rel['ID'];
// 			}

			$name = matchnames($oldname, $name);
			if ($bookID > 0)
			{
				$ok = dodbupdate($id, 3030, $name, $bookID, 'book');
			} else {
				$ok = dodbupdate($id, 3030,  $name);
			}
		} else {
			$ok = findmusicname($row, 3030);
//			$ok = dodbupdate($id, 3030);
		}
	}
//	unset($book);
	return $ok;
}

function matchmusic($nfo, $row)
{
	$artist = $album = '';

	$artist = preg_split('/(?:a\s?r\s?t\s?i\s?s\s?t\b)+? *?(?!(?:[^ \.\:\}\]\*] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\( \xb0-\x{3000}]+?)[ \.\>\:]([a-z0-9]?(?!:).+?(?<!\s\s))/Uuim',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
//	$title = preg_split('/(?:title\b|album\b|release\b)(?![ \.]*(?:notes|\d+\:\d+|size|info|date|time|information|no|number))(?:[\|\;\:\.\[\}\]\(\s\xb0-\x{3000}]+?)(\b.+?$)/Uuim',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
	$title = preg_split('/(?:t\s?i\s?t\s?l\s?e\b|a\s?l\s?b\s?u\s?m\b)+? *?(?!(?:[^ \.\:\}\]\*] ?){2,}?\b)(?:[\*\?\-\=\|\;\:\.\[\}\]\( \xb0-\x{3000}]+?)[ \.\>\:]([a-z0-9]?(?!:).+?(?<!\s\s))/Uuim',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);


//	var_dump($artist);
//	var_dump($title);

	$ok = false;

//	echo "title = ".$title[1]."\n";
//	echo "artist = ".$artist[1]."\n";

	if (!isset($title[1]))
	{
		$ok = findmusicname($row, 3010);
		return $ok;
	}

	$new = '';

	$artist1 ='';
	$music = new Music();

	$musicId = 0;
	$bookId = 0;

	if (isset($title[1]) && strlen($title[1]) >1 && $title[1] <50 && isset($artist[1]) && strlen($artist[1]) >1 && $artist[1] <50)
	{

//	echo "\n * * * * *   title= ".$title[1]."\t ".$row['name']."\n";
		if (isset($artist[1]))
		{
			$new = trim($artist[1]);
			$artist1 = trim($artist[1]);
		}


		if (isset($title[1]))
		{
			if (strlen($new) > 1)
			{
				$new = $new . " -*- ";
			}
			$new = $new . trim($title[1]);
			$album = trim($title[1]);
		}

		$name = matchnames($row['name'], $new);

		$artist = matchnames($artist1, '');
		$album = matchnames($album, '');

//	echo "$artist * * $album\n";
	} else {

		$name = domusicfiles ($row);
		if ($name != "")
		{
			$result = $music->parseArtist($name);
			$artist = $result['artist'];
			$album = $result['album'];
		}
	}

echo "3artist = $artist album = $album\n";

	$album = stripname($album);
	$artist = stripname($artist);



	$tmp = explode(",", $artist);

	if (count($tmp) > 1)
		$artist = trim($tmp[1]." ".$tmp[0]);

echo "4artist = $artist album = $album\n";

	if ($artist != '' && $album != '')
		$musicId = 0;
	else {
		$rel = $music->getMusicInfoByName($artist, $album);
//	var_dump($rel);
		$musicId = $rel['ID'];
	}

	if ($musicId <= 1)
	{
		echo "amazon music2 ";
		$musicId = $music->updateMusicInfo($artist, $album, 0);

		if ($musicId === false)
			{
//	var_dump($artist);
//	var_dump($album);
				$book = new Book(true);
				$bookId = $book->updateBookInfo($artist, $album);
				unset($book);
			}
//	var_dump($musicId);

	}

	if ($musicId > 0)
	{
		$ok = dodbupdate($row['ID'], 3010, $name, $musicId, 'music');
	}  else if ($bookId > 0) {
		$ok = dodbupdate($row['ID'], 3030, $name, $bookId, 'book');
	}

	if (!$ok)
	{
		$ok = findmusicname($row, 3010);

//		$name = matchnames($row['name'], domusicfiles($row));
//		$ok = dodbupdate($row['ID'], 3010, '');
	}
	if (!$ok)
	{
		if (preg_match('/book/i', $row['gname']))
			$ok = dodbupdate($row['ID'], 3030, $name);
		else
			$ok = dodbupdate($row['ID'], 3010, $name);
	}
	unset($music);
	return $ok;
}

function matchmag($nfo, $row)
{
	$ok = false;
	$set = preg_split('/(?:\btitle|\bname|release)\b(?:[\-\:\.\}\[\s\xb0-\x{3000}]+?)\B([a-z0-9\.\- \(\)]+?)/Uui',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
	if (isset($set[1]))
	{
		$name = trim($set[1]);
		if (preg_match('/(?:issue)\b(?:[\-\:\.\}\[\s\xb0-\x{3000}]+?)\B([a-z0-9\.\- \(\)\/]+?)/iuU', $nfo, $set))
		{
			if (isset($set[1]))
			{
				$name = $name." ".trim($set[1]);
			}
		$name = matchnames($row['name'], $name);
		$ok = dodbupdate($row['ID'], 7010, $name );
		}
	}
	return $ok;
}

function dodbupdate($id, $cat, $name = '', $typeid = 0, $type='none', $debug = DEBUGGING)
{
	global $db;

	$query = "UPDATE `releases` SET  `categoryID` = $cat";

	if ($name != '')
		$query = $query.", `searchname` = ".$db->escapeString($name);
	switch ($type) {
		case 'imdb':
			if ($typeid != 0)
			$query = $query.", `imdbID` = $typeid";
			break;
		case 'book':
			if ($typeid != 0)
			$query = $query.", `bookinfoID` = $typeid";
			break;
		case 'music':
			if ($typeid != 0)
			$query = $query.", `musicinfoID` = $typeid";
			break;
		case 'anime':
			if ($typeid != 0)
			$query = $query.", `anidbID` = $typeid";
			break;
		case 'tv':
			if ($typeid != 0)
			$query = $query.", `rageID` = $typeid";
			break;
		default:
			break;
	}

	$query = $query." where `ID` = $id";
echo $debug." ".$query."\n";
	if (!$debug)
	{
echo "updating";
		if ($db->query($query) !== false)
			return true;
	} else {
		return true;
	}
	return false;
}

function findmovie($name)
{
	$imdb = 0;

	$j = 0;

	$name = stripname($name);

	$url = "http://www.omdbapi.com/?r=xml&s=";
	$name1 = $name;
	if (preg_match('/([^\[\r\]]+)[\[\{\(\_\. ]+?((?:19|20)\d{2})/U', $name, $title))
		{
			$url = $url.urlencode($title[1])."&y=".$title[2];
			$name1 = $title[1]." ".$title[2];
		}
	else
		$url = $url.urlencode($name);

echo "omdbapi: $url\n";
echo cleanname($name1)."\n";

	$data = getUrl($url);

//echo "$data\n";
			$xmlObj = @simplexml_load_string(html_entity_decode($data));
			$arrXml = objectsIntoArray($xmlObj);

//	var_dump($arrXml);

	if (isset($arrXml["Movie"]))
	{

		$tvrage = new TVRage(true);

		$name1 = '';

		foreach ($arrXml["Movie"] as $ax)
		{
//		var_dump($ax);

			if (isset($ax["@attributes"]))
				$a = $ax["@attributes"];
			else
				$a = $ax;
			if (isset($title[2]) && $a["Year"] == $title[2])
			{
				$i = 0;
				$a["Title"] = preg_replace('/&/', ' and ', $a["Title"]);
				$name = preg_replace('/&/', ' and ', $name);

//echo "matched ".$a["Title"]."\n";

				$arr = preg_split('/[\:\:\.\, \-\_\(\)\[\]\{\}]/', $a["Title"], 0, PREG_SPLIT_NO_EMPTY);
				foreach ($arr as $r)
					{
echo preg_quote($r)."\n";
						$r = str_replace("/", " ", $r);
						$r = str_replace("\\", " ", $r);
						if (preg_match('/'.preg_quote($r).'/i', $name))
							$i++;
					}
				if (count($arr) == $i && $i > $j)
				{
					$name1 = $name;
					$imdb = $a["imdbID"];
					$j = $i;
				}
//				echo "comp result ".count($arr)." ".$i." ".$name."\n";
//				echo $a["Title"]."\n";
			}
		}
		echo "$name1 $imdb\n";

		unset($tvrage);
	}
	echo "findmovie = $imdb\n";

	return array(strval($imdb), $j);
}

function matchvideo($row, $nfo)
{
	global $db;

	$ok = false;

	$imdb = '';

	$j = 0;

	$query = "SELECT releasevideo.releaseID FROM releasevideo WHERE releasevideo.releaseID = ".$row['ID'];
	$rel = $db->queryOneRow($query);

	if ($rel === false)
	{
		$query = "SELECT releasefiles.`name`, releasefiles.releaseID, releasefiles.size FROM releasefiles WHERE releasefiles.releaseID = ".$row['ID'];
		$res = $db->queryDirect($query);

		$cont = false;
		while ($rel =  $db->getAssocArray($res))
		{
			if(preg_match('/\.(iso|mkv|mp4|iso|divx)$|\.([^\.]+)$/', $rel['name'], $ext))
			{
				echo "ext = ".$ext[1]." ".$row['guid']."\n";
				var_dump($ext);
				$cont = true;
			}
		}
		if (!$cont)
			return false;
	}
	$thename = $name = $row['name'];
echo "name1: $name\n";
	$rageID = lookupTV ($name, $nfo, $row, 5000);

	if ($rageID == 0)
	{
		$imdb = findmovie($name);
		$imdb = $imdb[0];
	}

	if ($rageID == 0 && empty($imdb) )
	{
		$set = preg_split('/(?:\btitle|\bname).+[\.\s]+?[^a-z0-9]*?([a-z0-9\-\.\_ ]+?)/iU',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
//var_dump($set);
		if (isset($set[1]))
		{
echo "name2: ".$set[1]."\n";
			$rageID = lookupTV ($set[1], $nfo, $row, 5000);
			if ($rageID)
			{
				$thename = $set[1];
			} else
			{
				$imdb = findmovie($set[1]);
				$imdb = $imdb[0];
			}

		}

		if ($rageID == 0 && empty($imdb) )
		{
			$query = "SELECT releasefiles.releaseID, releasefiles.`name` FROM releasefiles INNER JOIN releases ON releasefiles.releaseID = releases.ID WHERE releases.ID = ".$row['ID'];
			$rel = $db->query($query);

			$ok = false;
			$j = 0;


			if (count($rel) > 0)
			{
				foreach($rel as $r)
				{
					$name1 = preg_replace('/video[\_\. ]ts/iU', ' ', $r['name']);

					$name1 = preg_replace('/\\\\/', ' ', $name1);

					$name1 = preg_replace('/\.[a-z][a-z0-9]{2}$/iU', '', $name1);

					$name1 = preg_replace('/  +/', ' ', $name1);

					$name1 = preg_replace('/^ | $/', '', $name1);

					echo "file ".$name1."\n";
echo "name3: $name1\n";
					$rageID = lookupTV ($name1, $nfo, $row, 5000);

					if ($rageID && $j == 0)
					{
						$thename = $name1;
						break;
					} else {
						$tmp = findmovie($name1);
//						var_dump($tmp);
						if ($tmp[1] > $j)
						{
							echo "$rageID\n";
							$imdb = $tmp[0];
							$j = $tmp[1];
							$thename = $name1;
						}
					}

				}
			}
		}
	}
echo "$j $rageID $imdb\n";

	if ($imdb != '')
	{
		$imdb = preg_replace('/^tt/', '', $imdb);
		$movie = new Movie();
		$tmp = $movie->getMovieInfo($imdb);

		if ($tmp === false)
		{
			$movie->fetchImdbProperties($imdb);
			$movie->updateMovieInfo($imdb);
			$tmp = $movie->getMovieInfo($imdb);
		}


		$thename = matchnames($row['name'], $thename);
		$thename = matchnames($thename, $tmp['title']);
		$thename =  findquality ($nfo, $thename);

		unset($movie);

echo "$thename\t";
//	var_dump($tmp);

		if (isset($tmp['ID']))
		{
			if (preg_match('/sport/iU', $tmp['genre'])) {
				$ok = dodbupdate($row['ID'], 5060, $thename, $imdb, 'imdb');
			} else if (preg_match('/docu/iU', $tmp['genre'])) {
				$ok = dodbupdate($row['ID'], 5080, $thename, $imdb, 'imdb');
			} else if (preg_match('/talk\-show/iU', $tmp['genre'])) {
				$ok = dodbupdate($row['ID'], 5000, $thename, $imdb, 'imdb');
			} else if (preg_match('/tv/iU', $tmp['genre']) || preg_match('/episode/iU', $tmp['genre']) || preg_match('/reality/iU', $tmp['genre']))	{
				$ok = updateTV ($tmp['title']." (".$tmp['year'].")", $row['ID'], $row, 5000, $nfo);
			} else {
				$ok = dodbupdate($row['ID'], 2000, $thename, $imdb, 'imdb');
			}
		}


	}

	if ($rageID)
	{
		$tvrage = new TVRage(true);
		$tmp = $tvrage->getByRageID($rageID);
		$thename = matchnames($row['name'], $thename);
		$thename = matchnames($thename, $tmp[0]['releasetitle']);
		$thename =  findquality ($nfo, $thename);

		$name = $tmp[0]['releasetitle'];

	//	$tmp = $tvrage->getByRageID($rageID);

		unset($tvrage);
//	var_dump($tmp);
echo "doing updates";
		if (isset($tmp[0]))
		{
			if (preg_match('/sports/i', $tmp[0]['genre']))
				$ok = dodbupdate($row['ID'], 5060, $thename, $rageID, 'tv');
			elseif (preg_match('/documen/i', $tmp[0]['genre']))
				$ok = dodbupdate($row['ID'], 5080, $thename, $rageID, 'tv');
			else
				$ok = dodbupdate($row['ID'], 5000, $thename, $rageID, 'tv');
		}
	}

	if ($rageID == 0 && empty($imdb) )
	{
		$thename = matchnames($row['name'], $thename);
		$thename =  findquality ($nfo, $thename);

		if (preg_match('/sport/i', $row['gname']))
			$ok = dodbupdate($row['ID'], 5060, $thename);
		elseif (preg_match('/docu/i', $row['gname']))
			$ok = dodbupdate($row['ID'], 5080, $thename);
		elseif (preg_match('/tv/i', $row['gname']))
			$ok = dodbupdate($row['ID'], 5000, $thename);
		elseif (preg_match('/mov/i', $row['gname']))
			$ok = dodbupdate($row['ID'], 2000, $thename);
		else
			$ok = dodbupdate($row['ID'], 5050, $thename);
	}

	return $ok;
}


echo "starting\n";
$db = new DB();

$res = $db->query("SET NAMES 'utf8'");
$res = $db->query("SET CHARACTER SET 'utf8'");

//mysql_query("SET NAMES 'utf8'");
//mysql_query("SET CHARACTER SET 'utf8'");
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
mb_http_output("UTF-8");
mb_language("uni");

$query = "SELECT ID FROM category WHERE parentID = 8000";  // OR parentID = 2000 OR parentID = 5000

$res = $db->query($query);

$thecategory = $res[0]['ID'];

unset($res[0]);

foreach($res as $r)
	$thecategory = $thecategory.", ".$r['ID'];

if (isset($argv[1]))
{
	$query = "SELECT uncompress(releasenfo.nfo) AS nfo, releases.ID, releases.guid, releases.`name`, releases.searchname, groups.`name` AS gname FROM releasenfo INNER JOIN releases ON releasenfo.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.ID = ".$argv[1];
} else {
	$query = "SELECT uncompress(releasenfo.nfo) AS nfo, releases.ID, releases.guid, releases.`name`, releases.searchname, groups.`name` AS gname FROM releasenfo INNER JOIN releases ON releasenfo.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.categoryID IN ( $thecategory )";
}
//$query = "SELECT uncompress(releasenfo.nfo) AS nfo, releases.ID, releases.guid, releases.`name`, releases.searchname, groups.`name` AS gname FROM releasenfo INNER JOIN releases ON releasenfo.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID";
//$query = "SELECT uncompress(releasenfo.nfo) AS nfo, releases.ID, releases.guid, releases.`name`, releases.searchname, groups.`name` AS gname FROM releasenfo INNER JOIN releases ON releasenfo.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.guid = '6dc77dc7fb48745ee76ec345c4fe789b'";
//
//echo $query."\n";

$res = $db->queryDirect($query);

echo "got matches\n";

while ($row =  $db->getAssocArray($res))
{
	$nfo = utf8_decode($row['nfo']);
	if (strlen($nfo) > 0)
	{
//		trigger_error("doing nfo ".$row['ID']." ".$row['name']);

		$pattern = '/(imdb)\.[a-z0-9\.\_\-\/]+?(tt|\?)(\d+?)\/?|(tvrage)|(\bASIN)|(isbn)|(UPC\b)|(comic book)|(comix)|(tv series)|(\bos\b)|(documentaries)|(documentary)|(doku)|(macintosh)|(dmg)|(mac[ _\.\-]??os[ _\.\-]??x??)|(\bos\b\s??x??)|(\bosx\b)';
		$pattern = $pattern . '|(\bios\b)|(iphone)|(ipad)|(ipod)|(pdtv)|(hdtv)|(video streams)|(movie)|(audiobook)|(audible)|(recorded books)|(spoken book)|(speech)|(read by)\:?|(narrator)\:?|(narrated by)';
		$pattern = $pattern . '|(dvd)|(ntsc)|(m4v)|(mov\b)|(avi\b)|(xvid)|(divx)|(mkv)|(amazon\.)[a-z]{2,3}.*\/dp\/|(anidb.net).*aid=|(\blame\b)|(\btrack)|(t r a c k)|(music)|(44.1kHz)|type:(game)|(game) Type|(platform)|\b(win(?:dows|all|xp)\b)|(\bwin\b)';
		$pattern = $pattern . '|(m3u)|(flac\b)|(application)|(plugin)|(\bcrack)|(install)|(setup)|(magazin)|(x264)|(itunes\.apple\.com\/)|(sports)|(deportes)|(nhl)|(nfl)|(\bnba)|(ncaa)|(album)|(epub)|(mobi)/iU';

		$matches = preg_split($pattern, $nfo, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);

		$matches = doarray($matches);

		if (count($matches) > 0)
		{
		//	var_dump($matches);
		}


		foreach($matches as $m)
		{
			if (isset($m)) {
				$case = preg_replace('/ /', '', $m);
			} else {
				$case = '';
			}
	if ($row['name'] == '4d5bdbf0da623fb9cf849b8b745c60e9')
	print_r($matches);

			if (($m == 'os' || $m == 'platform') && preg_match('/(?:\bos\b(?: type)??|platform)[ \.\:\}]+(\w+?).??(\w*?)/iU', $nfo, $set))
			{

				if (isset($set[1]))
				{
	//	var_dump($set);
					$case = strtolower($set[1]);
				}
				if (strlen($set[2]) && (stripos($set[2], 'mac') !== false || stripos($set[2], 'osx') !== false))
				{
					$case = strtolower($set[2]);
				}
			}


			echo "\n".$case." ".$row['guid']."\n";
	//		echo $row['name']."\n";
	//		echo $row['searchname']."\n";
			echo "$case ".$row['guid']."\n";

			switch (strtolower($case))
			{
				case 'itunes.apple.com/':
					echo $row['guid']."\n";
					preg_match('/itunes\.apple\.com\/(.*)/iU', $nfo, $set);
					echo 'itunes.apple.com'.$set[1]."\n";
					break;

				case 'imdb':
					if (matchimdb($nfo, $row))
						break(2);
					break;


				case "asin":
				case "isbn":
				case "amazon.":
				case "upc":
					if ($case == 'asin' || $case == 'isbn')
					{
						preg_match('/(?:isbn|asin)[ \:]*? *?([a-zA-Z0-9\-\.]{8,20}?)/iU', $nfo, $set);
		var_dump($set);
						if (isset($set[1]))
						{
							$set[1] = preg_replace('/[\-\.]/', '', $set[1]);
	echo "asin ".$set[1]."\n";
							if (strlen($set[1])>13)
								break;
							if (isset($set[1]))
							{
								$set[2] = $set[1];
								$set[1] = "com";
							}
						}
					} else if ($case == 'amazon.') {
						preg_match('/amazon\.([a-z]*?\.?[a-z]{2,3}?)\/.*\/dp\/([a-zA-Z0-9]{8,10}?)/iU', $nfo, $set);
					} else if ($case == 'upc') {
						preg_match('/UPC\:?? *?([a-zA-Z0-9]*?)/iU', $nfo, $set);
							if (isset($set[1]))
							{
								$set[2] = $set[1];
								$set[1] = "All";
							}
					} else {
						echo "* * * * * error in amazon";
						break;
					}

					if (count($set) > 1)
					{
						if (doAmazon ($row['name'], $row['ID'], $nfo, $set[2], $set[1], $case, $nfo, $row))
						{
							break(2);
						}
					}
					break;
				case 'comicbook':
				case 'comix':
					if (dodbupdate($row['ID'], 7030))
						break(2);
					break;
				case 'audiobook':
				case 'audible':
				case 'recordedbooks':
				case 'spokenbook':
				case 'readby':
				case 'narratedby':
				case 'narrator':
				case 'speech':
					if (matchaudiobook ($nfo, $row['ID'], $row['name'], $row))
						break(2);
					break;

				case 't r a c k':
				case 'track':
				case 'lame':
				case 'album':
				case 'music':
				case '44.1kHz':
				case 'm3u':
				case 'flac':
					if (preg_match('/(a\s?r\s?t\s?i\s?s\s?t|l\s?a\s?b\s?e\s?l|mp3|e\s?n\s?c\s?o\s?d\s?e\s?r|rip|stereo|mono)/i', $nfo))
					{
						if (!preg_match('/(\bavi\b|x\.?264|divx|mvk|xvid|install|Setup\.exe|unzip|unrar)/i', $nfo))
						{
							if (matchmusic($nfo, $row))
							{
		//						echo "breaking 2\n";
								break(2);
							}
						} else {
	//					echo "$case ".$row['guid']."\n";
						if (findmusicname($row, 3010))
							break (2);
						}
					}
					break;

				case 'magazin':
	//				echo "magazin\n";
					if (matchmag($nfo, $row))
						break(2);
					break;

				case 'dmg':
				case 'mac':
				case 'macintosh':
				case 'macos':
				case 'macosx':
				case 'osx':
					if (doOS($nfo, $row['name'], 4030, $row['ID']))
						break(2);
					break;

				case 'ios':
				case 'iphone':
				case 'ipad':
				case 'ipod':

					if (doOS($nfo, $row['name'], 4060, $row['ID']))
						break(2);
					break;

				case 'application':
				case 'install':
				case 'setup':
					if (!preg_match('/(Setup(\.exe)?|unzip|unrar)/i', $nfo))
						continue;
					if (preg_match('/(\bavi\b|x\.?264|divx|mvk|xvid|install)/i', $nfo))
						continue;

				case 'windows':
				case 'win':
				case 'winall':
				case 'winxp':
				case 'plugin':
				case 'crack':
					if (doOS($nfo, $row['name'], 4020, $row['ID']))
						break(2);
					break;

				case 'android':
					if (doOS($nfo, $row['name'], 4070, $row['ID']))
						break(2);
					break;

				case 'game':
					$set = preg_split('/\>(.*)\</Ui',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

					if (isset($set[1]))
					{
						$name = matchnames($row['name'], $set[1]);
						if (dodbupdate($row['ID'], 4050, $name))
							break(2);
					} else {
						if (doOS($nfo, $row['name'], 4050, $row['ID']))
							break;
					}
					break;

				case 'anidb.net':
					if (matchanime ($nfo, $row['ID'], $row['name']))
						break(2);
					break;

				case 'sports':
				case 'deportes':
				case 'nhl':
				case 'nfl':
				case 'nba':
				case 'ncaa':
					if (doOS($nfo, $row['name'], 5060, $row['ID']))
					{
						break(2);
					}
					break;

				case 'tvrage':
				case 'documentaries':
				case 'documentary':
				case 'tvseries':
				case 'hdtv':
				case 'pdtv':

					$cat = 5050;

					if (preg_match('/hdtv/', $case))
						$cat = 5040;
					elseif (preg_match('/documentar/', $case))
						$cat = 5080;

					$name = '';
	//				$title = preg_match('/(?:\btitle\b)+?(?! *[^ \.\:\}\]]{2,}?\b)(?:[\|\;\:\.\[\}\]\( \xb0-\x{3000}]+?)[ \.]([a-z0-9](?!:)(?:.(?!\s\s+))+?[^\s]+?)/Uuim',  $nfo, 0, PREG_SPLIT_DELIM_CAPTURE);
					if (preg_match('/(?:\btitle\b)+?(?! *[^ \.\:\}\]]{2,}?\b)(?:[\|\;\:\.\[\}\]\( \xb0-\x{3000}]+?)[ \.]([a-z0-9](?!:)(?:.(?!\s\s+))+?[^\s]+?)/Uuim',  $nfo, $title))
						$name = $title[1];
					elseif (preg_match('/^(.*(19|20)\d{2}[ \_\.]\d{1,2}[ \_\.]\d{1,2})[ \_\.]?/', $nfo, $title))
						$name = $title[1];
					elseif (preg_match('/^(.*\d{1,2}[ \_\.]\d{1,2}[ \_\.](19|20)\d{2})[ \_\.]?/', $nfo, $title))
						$name = $title[1];
					elseif (preg_match('/^(.*(19|20)\d{2}[\]\}\)]?)[ \_\.]/', $nfo, $title))
						$name = $title[1];

		echo "doing tv ".$name." ".$row['guid']."\n";
					if ($name != '')
					{
						if (lookupTV($name, $nfo, $row, $cat) !== false)
							break(2);
					}

	//				break;
				case 'movie':
				case 'videostreams':
				case 'x264':
				case 'avi':
				case 'mkv':
				case 'xvid':
				case 'dvd':
				case 'ntsc':
				case 'm4v':
				case 'mov':
	echo "doing movie\n";
					if (matchvideo($row, $nfo))
						break(2);


					break;

				default:
		//echo "$case ".$row['guid']."\n";
					break;

			}
		}
		echo $row['guid']."\n";
	}
}

//exit(0);
echo "done nfos\n";


if (isset($argv[1]))
{
	$query = "SELECT releases.*, g.`name` AS gname FROM releases INNER JOIN groups g ON releases.groupID = g.ID WHERE releases.ID = ".$argv[1]."";
} else {
	$query = "SELECT releases.*, g.`name` AS gname FROM releases INNER JOIN groups g ON releases.groupID = g.ID WHERE releases.ID NOT IN ( SELECT releasevideo.releaseID FROM releasevideo ) AND releases.categoryID IN ($thecategory)" ;
//$query = "SELECT * FROM releases WHERE releases.ID NOT IN ( SELECT releasevideo.releaseID FROM releasevideo )" ;
}

$res = $db->queryDirect($query);

echo "doing part 2\n";

while ($row =  $db->getAssocArray($res))
{
//	trigger_error("doing part 2".$row['ID']);
	$query = "SELECT releasevideo.releaseID FROM releasevideo WHERE releasevideo.releaseID = ".$row['ID'];
	$rel = $db->queryOneRow($query);

	if ($rel !== false)
		continue;

	echo "\n".$row['guid']."\n";

	$query = 'SELECT releasenfo.releaseID, uncompress(releasenfo.nfo) AS nfo FROM releasenfo WHERE releasenfo.releaseID = '.$row['ID'];
	$rel = $db->queryOneRow($query);

	$nfo = '';
	if ($rel !== false)
		if ($rel['releaseID'] == $row['ID'])
			$nfo = $rel['nfo'];

		 matchmusic($nfo, $row);
		//findmusicname($row, 3010);
}

if (isset($argv[1]))
{
	$query = "SELECT releasefiles.`name` AS rname, releasefiles.size, releases.*, groups.`name` AS gname FROM releasefiles INNER JOIN releases ON releasefiles.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.ID = ".$argv[1]." ORDER BY releasefiles.releaseID, releasefiles.`name`";
} else {
	$query = "SELECT releasefiles.`name` AS rname, releasefiles.size, releases.*, groups.`name` AS gname FROM releasefiles INNER JOIN releases ON releasefiles.releaseID = releases.ID INNER JOIN groups ON releases.groupID = groups.ID WHERE releases.categoryID IN ( $thecategory ) ORDER BY releasefiles.releaseID, releasefiles.`name`";
}

$res = $db->queryDirect($query);

echo "doing part 3\n";

while ($row =  $db->getAssocArray($res))
{
//	trigger_error("doing part 3".$row['ID']);
	if(preg_match('/\.([^\.]{3,4})$/', $row['rname'], $ext))
	{
		$ext[1] = strtolower($ext[1]);

		echo "ext = ".$ext[1]." ".$row['guid']."\n";
//		var_dump($ext);
		switch ($ext[1])
		{
			case 'epub':
			case 'mobi':
				$title = preg_split("/ \- /", $row['rname']);
				$author = preg_replace('/(\.[^\.]+)/', '', $title[1]);

				$tmp = preg_replace('/'.$row['name'].'/', '', $title[0]);
				$tmp = preg_split('/\\\\/', $tmp, 0, PREG_SPLIT_NO_EMPTY);



				echo "author: ".$author." title: ".$tmp[count($tmp)-1]."\n";

				$bookId = findbookid ($author, $tmp[count($tmp)-1]);

				$title1 = $tmp[count($tmp)-1];

				echo $bookId."\n";

				if ($bookId == 0)
				{
					$bookId = findbookid ($tmp[count($tmp)-1], $author);
					echo "inverse ".$bookId."\n";

					$title1 = $author;
					$author = $tmp[count($tmp)-1];

				}

				$title1 = matchnames($row['name'], $author.' - '.$title1);

				if ($bookId != 0)
					$ok = dodbupdate($row['ID'], 7020, $title1, $bookId, 'book');
				else
					$ok = dodbupdate($row['ID'], 7020, $title1);


			case 'avi':
			case 'mkv':
			case 'mp4':
			case 'iso':
			case 'wmv':
			case 'mpg':
			case 'mov':
			case 'm4v':
				$tmp = preg_replace("/([^\\\\]+?)\\\\(\\1)/", "\\2", $row['rname']);
				$tmp = str_replace('/', ' ', $tmp);
				$tmp = preg_replace("/".preg_quote($row['name'])."/", "", $tmp);
				$tmp = preg_replace("/\\\\/", " ", $tmp);
				$tmp = stripname($tmp);

				$tvrage = new TVRage(true);
				$parsed = $tvrage->parseNameEpSeason($tmp);

				unset($tvrage);
				echo "name = ".$tmp."\n";

				$nfo = $tmp."\n";

				var_dump($parsed);

				matchvideo($row, $nfo);

				break;


			default:
				break;
		}

	}

}

echo "all done\n";

unset($res);
unset($row);
unset($matches);

if (!isset($argv[1]))
{
	$movie = new Movie();
	$movie->processMovieReleases();
	$movie->updateUpcoming();

	$music = new Music(true);
	$music->processMusicReleases();

	$book = new Book(true);
	$book->processBookReleases();

	$anidb = new AniDB(true);
	$anidb->animetitlesUpdate();
	$anidb->processAnimeReleases();

	$tvrage = new TVRage(true);
	$tvrage->processTvReleases(true);

	$thetvdb = new TheTVDB(true);
	$thetvdb->processReleases();

	echo "cleaning 5000\n";

	$query = "update releases set  releases.categoryID= 5060 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE movieinfo.genre REGEXP 'sport' AND NOT movieinfo.genre REGEXP 'comedy' AND NOT movieinfo.genre REGEXP 'romance' AND NOT movieinfo.genre REGEXP 'drama' )";

	$db->query($query);

	$query = "update releases set  releases.categoryID= 5000 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE genre REGEXP 'tv|talk\-show|reality|episode' ) AND categoryID NOT IN ( SELECT ID FROM category WHERE parentID = 5000 )";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2050  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)  AND size > 10500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2040  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000) AND size > 2500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2030  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)  AND size > 500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2020  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `imdbID` > 0) OR `categoryID` = 2000)";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2040  WHERE `categoryID` = 2050 AND size <10000000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=2030  WHERE `categoryID` = 2040 AND size < 2500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=5040  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000) AND size > 1500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=5030  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000)  AND size > 10000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=5050  WHERE (`categoryID` in (SELECT ID FROM category WHERE parentID = 8000 and `rageID` > 0) OR `categoryID` = 5000)";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=5030  WHERE  `categoryID` = 5040 AND size < 1500000000";

	$db->query($query);

	$query = "UPDATE `releases` SET `categoryID`=5050  WHERE  `categoryID` = 5030 AND size < 1000000";

	$db->query($query);

	$query = "UPDATE releases SET categoryID = 5070 WHERE rageID IN ( SELECT tvrage.rageID FROM tvrage WHERE tvrage.genre REGEXP 'animat' )";

	$db->query($query);

	$query = "update releases set  releases.categoryID= 5080 WHERE imdbID IN ( SELECT movieinfo.imdbID FROM movieinfo WHERE genre REGEXP 'docum' )";

	$db->query($query);

}
//ALTER TABLE  `movieinfo` CHANGE  `genre`  `genre` VARCHAR( 256 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL

?>