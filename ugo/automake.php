<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
require(dirname(__FILE__)."/../../../../../www/config.php");
//require_once(FS_ROOT."/../bin/config.php");
require_once(WWW_DIR."/lib/nntp.php");

mb_internal_encoding("UTF-8");

function makerelease ($results, $parent, $name, $db, $sect, $mail)
{
	global $relname, $suffix;
	global $qual;
	$PROCSTAT_TITLEMATCHED = 5;

	echo $name."\n";

	if ($relname != '' && strlen($relname) > 3)
	{
		$name = $relname;
	}

	echo "Rname: ".$name."\n";

	$name = cleanstr($name);

	$name = preg_replace('/\(?yenc\)?/iU','',$name);

	$name = preg_replace("/^(\[\d+\].\[.+\].\[#?a.b.[^\]]+\].(\[))/i", " ", $name);

	$name = preg_replace("/\[(?![^\[]+\])|\((?![^\(]+\))|\{(?![^\{]+\})/i", " ", $name);

	$name = preg_replace("/^\(?\?+\)/i", " ", $name);

//	$name = preg_replace('/ \b(rar|zip|avi|mp3|mp4|dmg|tar|nfo|nzb|par2)\b/iU','.$1',$name);

	$name  = preg_replace('/\bmac[\. ]osx|mac[\. ]os[\. ]x\b|macos[\. ]x\b/iU', 'macosx', $name);

	$name = preg_replace('/ - - /iU',' - ',$name);

	$name = preg_replace('/^[\.\-\_\<\|]|[\.\-\_\|]$|\"/iU',' ',$name);

	$name = preg_replace('/^\s|\s$/iU','',$name);

	$name = preg_replace('/  +/iU',' ',$name);

	$name = cleanstr($name);

	$name = preg_replace('/(\d{1,4}? ?)?([o0]f ?\d{1,4}?)$/iU','',$name);

	$name = preg_replace('/ ?[o0]f ?$/iU','',$name);

	$name = preg_replace('/  +/iU',' ',$name);


	if ($qual != '')
	{
		$name = $name." ".$qual;
	}

	echo "release: ".$name." * * * ".$parent['name']."\n";

	$i = 1;
	$parts = count($results);
	$notyet = true;
//	echo $parts."\n";

//	echo $results[0]['reltotalpart']."\n";
//	echo $results[0]['ID']."\n";

	if (isset($results[0]) && ($results[0]['reltotalpart'] <= $parts) && $parts > 0 && is_string($name))
	{
		$notyet = false;
		echo "doing update: ".$parts." ".count($results)."\n";

		$parts = count($results);

		$query = "Select * from `binaries` where `relname`= ".$db->escapeString($name)." and procstat = 5;";

		$count = $db->query($query);

		if (count($count) > 0)
		{
			$nombre = $db->escapeString($name." ".$parent["ID"]);
			$notyet = clearmatches ($name, $results, $code = -6);
			return $notyet;
	echo "duplicate $nombre\n";
		} else {
			$nombre = $db->escapeString($name);
		}

		foreach ($results as $r)
		{
			$query = sprintf("UPDATE `binaries` SET `relname`=%s, `relpart`=%d, `reltotalpart`=%d, `procstat`=%d, `regexID`=%d, `fromname`='%s' where procstat != 5 AND `ID` = %d;", utf8_encode($nombre), $i++, $parts, $PROCSTAT_TITLEMATCHED, -(ord($sect) - ord("A") + 1), $mail, $r["ID"]);

		echo "doing update: ".$r['name']."\n";
	echo $query."\n";

			//var_dump($db->query($query));

			//var_dump($db->getLastError());
			//var_dump($db->getAffectedRows());

		}
	} else if ($parts == 1){
		//$notyet = clearmatches('', $results, $db);
	}

	return $notyet;
}

function cleanstr ($str = '', $full = false)
{
//	echo "CS1: ".$str."\n";

//	$str = preg_replace('/\(\?\?\?\?\)/iU', '', $str);
	$str = preg_replace('/\(??yenc\)??/iU', '', $str);
 	$str = preg_replace('/(?<=\.\b[a-z][a-z0-9]{2})(\.\b\d{1,4}?\b)(?!\.[0-9])/U', ' ', $str);
// 	$str = preg_replace('/^\s*(<*?(www\.)?[^\[\"]+?(\.\b(com|net|org|info)\b[^\[\(\"]*?))/iU','', $str);
	$str1 = preg_replace('/\s*([<>]*?(www\.)?[^\[\<>"]+?(\.\b(com|net|org|info)\b(?!\.[a-zA-Z][a-zA-Z0-9]{2})[^\[\(\"<>]*?))/U',' ', $str);

	if (strlen($str1)>0)
		$str = $str1;

	$str = preg_replace('/(\.\b[a-z][a-z0-9]{2}?\b|\.NFO|\.part[0-9]{1,4}?)+?/U',' ', $str);
	$str = preg_replace('/\.vol\d{1,4}?\+\d{1,4}?|\binfo\b|\.par2\b|\bp\d{1,4}?\b/iU', ' ', $str);

$str = preg_replace('/[\<\>]/iU', ' ', $str);

	//$str = preg_replace('/[\-\"\:]|(?![a-z0-9])\'(?![a-z0-9])/',' ',$str);
	$str = preg_replace('/[\"\:]|(?![a-z0-9])\'(?![a-z0-9])/',' ',$str);
	$str = preg_replace('/\-(?!\d\d)/',' ',$str);
	$str = preg_replace('/[\.](?!\d|\b[a-z]\b)(?<!\d|\b[a-z]\b)/iU', ' ', $str);
	$str = preg_replace('/[\s\.\-]{2,}?/iU', ' ', $str);
	$str = preg_replace('/^[\s\.\]\}\)]+?(?!\d+\])|[\s\.\[\{\(]+?$/iU', '', $str);
	$str = preg_replace('/(\d+?) ?[o0]f ?(\d+?)/iU', '\1 of \2', $str);

// 	do {
// 		$str =  preg_replace('/^[\[\{\(]|[\]\}\)]$|^ | $/iU','', $str, -1, $count);
//	} while ($count > 0);


//	echo "CS2: ".$str."\n";
	return $str;

}

function getname ($namemat = array())
{
	$name = $namemat[0];

//	echo "G1: ".$name."\n";

//	$name = preg_replace('/[\(\{\[]\d{1,4}?(?!\d\)?)/iU','',$name);
	$name = preg_replace('/^re:|Repost\b(:?)/iU','',$name);
	$name = preg_replace('/^AutoRarPar\d{3,5} /iU','',$name);
	$name = preg_replace('/[\x01-\x1f]/iU',' ',$name);

	if (strlen($name) <= 3)
	{
		$name = $namemat[count($namemat)-1];
	}
//	$name = preg_replace('/\bmac[\. ]osx|mac[\. ]os[\. ]x\b|macos[\. ]x\b/iU', 'macosx', $name);
//	$name = preg_replace('/(\.\b[a-z][a-z0-9]{2}\b|\.part[0-9]{1,4}?)(?<!\d)/iU',' ', $name);

	$name = preg_replace('/^[\]\}\)]/iU','', $name);
	$name = preg_replace('/^ +/iU','', $name);

//	echo "G2: ".$name."\n";

	$name = cleanstr($name);

//	echo "G3: ".$name."\n";

	if (strlen($name) <= 3)
	{
		$name = $namemat[2];
		$name = preg_replace('/^[\]\}\)]/iU','', $name);
		$name = preg_replace('/^ +/iU','', $name);

		$name = cleanstr($name);
	}

//	echo "G4: ".$name."\n";

return cleanstr($name);
}

function splitarray($str)
{
//	echo "\n";
//	echo "S1 :".$str."\n";


	$str = cleanstr($str);
	//$str = preg_replace('/ [o0]f \d{1,4}?/iU',' ',$str);
	//$str = preg_replace('/(\d{1,4}?)\.(\d{1,4}?)/iU','$1$2', $str);
	$str = preg_replace('/[\(\)\[\]\{\}\+\"]/',' ',$str);
	$str = preg_replace('/\-(?!\d\d)/',' ',$str);

	$str = preg_replace('/[\(\{\[]\d{1,4}?(?!\d\)?)(?! segs| parts)/iU','', $str);
//	$str = preg_replace('//'," ",$str);
	$str = preg_replace('/(\d*?([\.\,]\d*?)?) ?([kmg]b)/iU','$1$2',$str);

//	echo "S2 :".$str."\n";

	$str = cleanstr($str);

//	echo "S3 :".$str."\n";

	$arr = preg_split("/[\s_]+/", $str, 0, PREG_SPLIT_DELIM_CAPTURE);
	sort($arr);
	$i = 0;
	do {

		if (strlen($arr[$i])==0) {
			unset($arr[$i]);
			$arr = array_values($arr);
		} else {
			$i++;
		}
	} while ($i < count($arr));

	$i = 1;
	if (count($arr)>1)
	{
		do
		{
			if (strcmp($arr[$i-1] ,$arr[$i]) == 0)
			{
				unset($arr[$i]);
				$arr = array_values($arr);
			} else {
				$i++;
			}
		} while ($i < count($arr));
	}

//	var_dump($arr);
//	echo "\n";
	return $arr;
}

function checknames ($post, $matches, $fullname, $parts, $r, $db, $mail)
{
	global $relname, $suffix;
	global $nzb, $nfo;
	global $pars, $files;
	global $qual;

	echo "cn: ".$post."\n";
	echo "fn: ".$fullname."\n";

	$qualities = array('480', '720', '720p', '1080', '1080p', 'ac3', 'audio_ts', 'avi', 'bd25', 'bd50', 'bdmv', 'bdrip', 'bluray', 'BR-Disk', 'BR-Rip', 'brrip', 'cam', 'camrip', 'dc', 'directors cut', 'divx\d?', 'dts', 'DVD-R', 'DVD-Rip', 'dvd', 'dvdr', 'dvdrip', 'dvdscr',  'extended', 'h264', 'hd', 'hdcam', 'hdts', 'iso', 'm2ts', 'mkv', 'mpeg', 'mpg', 'ntsc', 'pal', 'proper', 'ppvrip', 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7', 'repack', 'rip', 'scr', 'screener', 'tc', 'telecine', 'telesync', 'ts', 'video_ts', 'unrated', 'x264', 'xvid');

	$pattern = '/[\{\[\(]0*?1\/'.$r['totalParts'].'??[\]\}\)]$/iU';

	$fullname = preg_replace($pattern, '', $fullname);

	$post = preg_replace($pattern, '', $post);

	$str = $post;

	if (preg_match('/^[\{\[\(]?(attn|att|attention)\:?/iU', $post))
	{
		$str =  preg_replace('/\batt[^ \:]*?\:?/iU','', $post);
//	 	do {
// 			$str =  preg_replace('/^[\[\{\(]|[\]\}\)]$|^ | $/iU','', $str, -1, $count);
// 		} while ($count > 0);
// 		if (preg_match('/ /iU', $str))
// 		{
// 			$str = $fullname;
// 		} else {
// 			$str = $post;
// 		}

		echo "attn: ".$str."\n";
	}

	if (!preg_match('/\d{1,4}?\/\d{1,4}?/iU', $fullname) || preg_match('/(File|Post) \d{1,4}? ?[o0]f ?\d{1,4}?/iU', $fullname))
	{
		//$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $post);
		$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $post);
	} else {
		//$str = $post;
		if (preg_match('/\d{1,4}?\/\d{1,4}?[\]\}\)]/iU', $fullname) && preg_match('/ ?[o0]f ?\d{1,4}?/iU', $fullname))
		{
			$str = preg_replace('/[\{\{\(]\d{1,4}?\/(\d{1,4}?)[\]\}\)]/iU','', $str);
	echo "cn1: ".$str."\n";
		} else {
		$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $str);
		$str = preg_replace('/\d{1,4}?\/(\d{1,4}?)/iU','$1', $str);
	echo "cn2: ".$str."\n";
		}
	}

// 	$str = preg_replace('/(?<=\.\b[a-z][a-z0-9]{2})(\.\b\d{1,4}?\b)/iU','', $str);
// 	$str = preg_replace('/(\.(par2|\b[a-z][a-z0-9]{2}?\b|vol\d{1,4}?\+\d{1,4}?))+?(\.\d{1,4}?)?/iU',' ',$str);
// 	$str = preg_replace('/(\binfo\b|\"|\-|  +)/iU',' ',$str);
// 	$str = preg_replace('/[\(\[\{]??\d{1,4}?\/\d{1,4}?[\]\)\}]??/iU','', $str);

	$postwords = $namemat = splitarray($str);


//	echo count($matches);
//	print_r($postwords);
//	echo "Name: ".cleanstr($str)."\n";
//	echo "\n";

	$actual = array();

	$nfo = 0;
	$nzb = 0;
	$segs = 0;
	$segsarr = array();
	$pars = 0;
	$files = 0;
	$mode = array();
	$extras = 0;

	foreach ($matches as $m)
	{

		$str = $m['name'];

		if ($suffix == '')
		{
			$tmp = preg_split('/\b(rar|zip|tar)\b/', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

			if (count($tmp) > 1)
			{
				$suffix = ".".$tmp[1];
			}
		}

		if (!preg_match('/\d{1,4}?\/\d{1,4}?/iU', $m['name']))
		{
			$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $m['name']);
		} else {
			if (preg_match('/\d{1,4}?\/\d{1,4}?[\]\}\)]/iU', $m['name']) && preg_match('/ ?[o0]f ?\d{1,4}?/iU', $m['name']))
			{
				$str = preg_replace('/[\{\{\(]\d{1,4}?\/(\d{1,4}?)[\]\}\)]/iU','', $m['name']);
			} else {
			$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $m['name']);
			$str = preg_replace('/\d{1,4}?\/(\d{1,4}?)/iU','$1', $m['name']);
			}
		}


// 		$str = preg_replace('/(?<=\.\b[a-z][a-z0-9]{2})(\.\b\d{1,4}?\b)/iU','', $str);
// 		$str = preg_replace('/(\.(par2|\b[a-z][a-z0-9]{2}?\b|vol\d{1,4}?\+\d{1,4}?))+?(\.\d{1,4}?)?/iU',' ',$str);
// 		$str = preg_replace('/(\binfo\b|\"|\-|  +)/iU',' ',$str);
// 		$str = preg_replace('/[\(\[\{]??\d{1,4}?\/\d{1,4}?[\]\)\}]??/iU','', $str);

//		$str = cleanstr($str);

		$keywords = splitarray($str);

//	print_r($keywords);
//	echo "m ".$str."\n";

		$i = 0;
		foreach ($postwords as $p)
		{
			foreach ($keywords as $k)
			{
//	echo $p." ".$k."\n";

				if (strcmp($p,$k) == 0)
				{
//	echo $p." ".$k."\n";
					$i++;
				}
			}
		}
		$mode[] = $i;
	}

$mode = array_count_values($mode);
ksort($mode);

//	echo "mode\n";
//	var_dump($mode);

end($mode);
$index = 0;
$key = 0;

do {
	$index = $index + current($mode);
echo "a".current($mode)."\n";
	$key++;

} while (($index < $parts) && (prev($mode) !== false));

echo "index raw".$index." ".$key."\n";

if (current($mode) !== false)
{
	if ($index > $parts)
	{
	$j = current($mode);
	echo "c".$j."\n";
		next($mode);
		$index = $index - $j;
		if ($index < $parts * 0.9)
		{
			prev($mode);
		}
	echo "d".current($mode)."\n";
	}
} else {
echo "e".current($mode)."\n";
	end($mode);
echo "f".current($mode)."\n";
}

if (current($mode) === false)
{
	end($mode);
echo "g".current($mode)."\n";
}

// if (end($mode) > prev($mode) || current($mode) > 1000)
// {
// 	end($mode);
// }

$index = key($mode);

echo "index ".$index."\n";

// if ($index < 5)
// {
// 	$index = count($postwords);
// }

foreach ($matches as $m)
{
		$str = $m['name'];

		if ($suffix == '')
		{
			$tmp = preg_split('/\b(rar|zip|tar)\b/', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

			if (count($tmp) > 1)
			{
				$suffix = ".".$tmp[1];
			}
		}

		if (!preg_match('/\d{1,4}?\/\d{1,4}?/iU', $m['name']))
		{
			$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $m['name']);
		} else {
			if (preg_match('/\d{1,4}?\/\d{1,4}?[\]\}\)]/iU', $m['name']) && preg_match('/ ?[o0]f ?\d{1,4}?/iU', $m['name']))
			{
				$str = preg_replace('/[\{\{\(]\d{1,4}?\/(\d{1,4}?)[\]\}\)]/iU','', $m['name']);
			} else {
			$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $m['name']);
			$str = preg_replace('/\d{1,4}?\/(\d{1,4}?)/iU','$1', $m['name']);
			}
		}

		$keywords = splitarray($str);

		$i = 0;
		foreach ($postwords as $p)
		{
			foreach ($keywords as $k)
			{

				if (strcmp($p,$k) == 0)
				{
//	echo $p." ".$k."\n";
					$i++;
				}
			}
		}


//	echo $i." ".$index." ".$m['name']." ".$m['ID']."\n";
		if ($i >= $index && $index > 0)
		{

//	echo $i." ".$index." ".$m['name']." ".$m['ID']."\n";

			$actual[] =$m;
			$str = preg_replace('/[\.](?!\d|\b[a-z]\b)(?<!\d|\b[a-z]\b)/iU',' ', $m['name']);

			if (preg_match('/vol\d{1,4}?\+\d{1,4}?|p\d{2,3}?/iU', $str) >= 1)
			{
				$pars = $pars + $m['totalParts'];
				$extras++;
//	echo "pars: ".$m['name']." ".$n."\n";

			} else if (preg_match('/sfv|\bnfo\b|\bnzb\b|par2/Ui', $str) >= 1)
			{
				$extras++;
//	echo "extra: ".$m['name']." ".$n."\n";
			} else
			{
				$segsarr[] = $m['totalParts'];
//	echo "segs: ".$m['name']." ".$n."\n";
			}

			$str = cleanstr($str);

//echo $str."\n";

			$str = preg_split("/[\s_\"]+/", $str, 0, PREG_SPLIT_DELIM_CAPTURE);
			foreach ($str as $n)
			{
				$namemat[] = $n;
				if (strcasecmp($n, 'nfo') == 0)
				{
					$nfo = $m['ID'];
				} else if (strcasecmp($n, 'nzb') == 0)
				{
					$nzb = $m['ID'];
				}
//	echo $n."\n";
			}
		}
//	print_r($actual);
//	echo "\n";

//	echo "suffix".$suffix."\n";
}

echo "p2: ".$pars." files: ".array_sum($segsarr)." e: ".$extras."\n";

$files = count($segsarr);

if (array_sum($segsarr) > 0)
{
	$segs = $pars /array_sum($segsarr) * ($files + $extras);
}

$pars = round($segs);

echo "p2: ".$pars." files: ".$files."\n";

$namemat = array_count_values($namemat);
asort($namemat);
//var_dump($namemat);

if (count($namemat) > 1)
{
	reset($namemat);
	$i = 1;
	$max = count($actual)-1;

	while (next($namemat) < $max && current($namemat) !== false)
	{
//echo current($namemat)." ".$i."\n";
		$i++;
	}

	$namemat = array_slice($namemat, $i,count($namemat), true);

//	var_dump($namemat);

	$j = 0;
	$name = '';
	$imat = array();
	foreach ($actual as $m)
	{
		$i = 0;

		foreach ($namemat as $key => $value)
		{
			$str = $m['name'];
			$str = preg_replace('/(\.(vol\d{1,4}?\+\d{1,4}?|sfv|part\d{1,4}?|nfo|\bnzb\b|par2))/iU','', $str);
			//$str = preg_replace('/[\[\{\(]??\d{1,4}?\/\d{1,4}?[\]\}\)]??/iU','', $str);
			//$str = preg_replace('/[\[\{\(]??\d{1,4}?(\/\d{1,4}?| [o0]f \d{1,4}?)[\]\}\)]??/iU','$1', $str);
			if(strripos($str, $key) !== false)
			{
				$i++;
			}
		}
//	echo $i." ".$j." ".$str."\n";
		$imat[] = $i;
		if ($i > $j)
		{
			$name = $str;
			$j = $i;
		}
	}

	$name = cleanstr($name);

	echo 'name = '.$name."\n";

	$name2 = '';

	$name = preg_replace('/[\.](?!\d|\b[a-z]\b)(?<!\d|\b[a-z]\b)/iU',' ', $name);

	echo 'name = '.$name."\n";

	$name = preg_split("/[\s_\"]+/", $name, 0, PREG_SPLIT_DELIM_CAPTURE);

	foreach ($name as $n)
	{
		foreach ($namemat as $key => $value)
		{

//	echo $n." ".$key."\n";
			if (strcmp($key,$n) == 0)
			{
//	echo $n."\n";
				$name2 = $name2.' '.$n;
				unset($namemat[$key]);
			}
		}
	}

	do {
		$name2 =  preg_replace('/^[\[\{\(](?!\d+\])|[\]\}\)]$|^ | $/iU','', $name2, -1, $count);
	} while ($count > 0);

	echo "name2 = ".$name2."\n";
		$relname = cleanstr($name2);
	echo "relname = ".$relname."\n";

//var_dump($namemat);

	$qual = '';

	foreach ($namemat as $key => $value)
	{
		foreach($qualities as $q)
		{
//	echo $key." ".$q."\n";
			if (stripos ($key , $q) !== false)
			{
					$qual = $qual.' '.$q;
				continue(2);
			}
		}
	}



//	var_dump($namemat);

	if ($nfo == 0)
	{
		$tmp = addnfo ($mail, $r, $db, $relname);

		echo "addnfo: ".count($actual)."\n";

		if (isset($tmp))
		{
//			$actual[] = $tmp;

		//	var_dump($actual);
		}
	}
}
return $actual;
}

function clearmatches ($name, $results, $code = -1)
{
	global $db;

	echo "clearing ".count($results)."\n";

	global $relname;

	foreach ($results as $r)
	{
		if ($name == '')
		{
			$query = sprintf("update binaries set procstat=%d, `procattempts`=0, `regexID`=NULL, `relpart`=0, `reltotalpart`=0 where (ID = %d OR binaryhash ='%s') and procstat != 5" , $code, $r["ID"], $r['binaryhash'] );
		} else {

			$query = sprintf("update binaries set procstat=%d, `procattempts`=0, `regexID`=NULL, `relpart`=0, `reltotalpart`=0, `relname`=%s where (ID = %d OR binaryhash ='%s') and procstat != 5" , $code, $db->escapeString($relname), $r["ID"], $r['binaryhash']);
		}

//	echo $query."\n";

		$db->query($query);
	}
	return false;
}

function checkmatches ($query, $r, $short = false)
{

global $relname, $nfo;
global $db;

	$query = $query." AND groupID=".$r['groupID'];

	if ($short)
	{
		$query = $query." AND `date` <= DATE_ADD('".$r['date']."', INTERVAL 6 HOUR) AND  `date` >= DATE_SUB('".$r['date']."', INTERVAL 6 HOUR) ";
	} else {
		$query = $query." AND `date` <= DATE_ADD('".$r['date']."', INTERVAL 2 DAY) AND  `date` >= DATE_SUB('".$r['date']."', INTERVAL 2 DAY) ";
	}

	if (preg_match('/^(\[\d+\])/', $r['name'], $matches))
	{
		$query = $query." AND name like '%".$matches[1]."%' ";
	}


	$query = $query." AND `procstat` != 5";

	$query = $query." GROUP BY binaryhash";

	$query = $query." ORDER BY name;";

	$matches = $db->query($query);

	echo count($matches)." ".$query."\n";

	if (count($matches) == 0)
	{
		$query = str_ireplace("match (name) against ('"," `name` like '%", $query);
		$query = str_ireplace("' IN NATURAL LANGUAGE MODE)","%'", $query);

		$matches = $db->query($query);

	$matches = $db->query($query);
	}


	echo $query."\n";
	return $matches;
}

function makenzb ($nzb, $nzb1, $nfo, $nfo1)
{
	global $nntpconnected, $nntp;
	global $relname;


//echo $nzb." ".$nfo."\n";

	if (!$nntpconnected)
	{
		//$nntp->doConnect();
		//$nntpconnected = true;
	}
	if ($nzb == 0)
	{
		$nzb = $nzb1;
	}

	if ($nzb != 0)
	{
echo "getting nzb\n";

	$relname = preg_replace('/[\[\{\(]?\d{1,4}?\/\d{1,4}?[\]\}\)]?/iU','', $relname);
	$relname = preg_replace('/\/|\:|^\.|^ | $|^[\[\]\{\}\(\)](?!\d+\])/iU','', $relname);
	$relname = preg_replace('/^[\`\'\"\<\/]/iU','', $relname);
	$relname = preg_replace('/^[\-\+\_]/iU','', $relname);
	$relname = preg_replace('/( \d{1,4}?)? ?[o0]f ?\d{1,4}?$/iU','', $relname);
	$relname = cleanstr($relname);

	//$bin =  $nntp->getBinary($nzb);
	//file_put_contents(FS_ROOT."/nzbs/".$relname.".nzb", $bin);
	}
	if ($nfo == 0)
	{
		$nfo = $nfo1;
	}

	if ($nfo != 0)
	{
echo "getting nfo\n";

	// $bin =  $nntp->getBinary($nfo);

	// file_put_contents(FS_ROOT."/nzbs/".$relname.".nfo", $bin);
	}
}

function addnfo ($mail, $r, $db, $relname)
{
	$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString($relname)." IN NATURAL LANGUAGE MODE)";

	$query = $query." AND `name` like '%.nfo%'";

	$query = $query." AND `fromname` like '%".$mail."%'";

	$query = $query." AND groupID=".$r['groupID'];

	$query = $query." AND `date` <= DATE_ADD('".$r['date']."', INTERVAL 2 DAY) AND  `date` >= DATE_SUB('".$r['date']."', INTERVAL 2 DAY) ";

	$query = $query." AND `procstat` != 5";

	$query = $query." GROUP BY binaryhash";

	$query = $query." limit 1;";

	$matches = $db->query($query);

	if (isset($matches[0]))
	{
		return $matches[0];
	} else {
		return null;
	}
}

function removemail($name, $mail)
{
	$poster = preg_split('/@/', $mail, 0, PREG_SPLIT_DELIM_CAPTURE);

//	var_dump($poster);

	$poster =  preg_replace('/[\.\_\-\']/','.?', $poster[0]);

	$poster = str_replace('/', ' ', $poster);

	$poster = str_replace('\\', ' ',  $poster);

	$poster = preg_quote($poster);

	echo "mail ".$mail."\n";

	echo "poster $poster\n";

	return preg_replace('/'.$poster.'/', '', $name);
}

function okresults (&$results, &$cuenta, $parts, $i, $patern, $patern2, $r, $mail, $dogn = true)
{
	global $pars, $relname;
	global $db;

	echo "OKr CR: ".count($results)." P2: $pars P: $parts\n";

	if (count($results) + $pars < $parts || count($results) > 2 * $parts)
	{
		$cuenta1 = preg_split($patern, $r['name'], 0, PREG_SPLIT_DELIM_CAPTURE);

echo "cuenta1 ".$i." ".$dogn;
//var_dump($cuenta1);

		if (isset($cuenta1) && $dogn)
		{
			$cuenta1[0] = getname($cuenta1);
//var_dump($cuenta1);
		}

		if (!isset($cuenta1[$i]) || strlen($cuenta1[$i]) < 3)
		{
			$cuenta1[$i] = $cuenta1[0];
		}

		$cuenta1[0] = preg_replace($patern2 ,'', $cuenta1[$i]);

echo "ok: ".$cuenta1[0]."\n";
//	var_dump($cuenta1);


		$query = "SELECT * FROM `binaries` where name like '%".preg_replace('/\'/','\\\'', removemail($cuenta1[0], $mail))."%'";

		$query = $query." AND `fromname` like '%".$mail."%'";

		$matches1 = checkmatches($query, $r);

		$relname1 = $relname;

		$pars1 = $pars;

		$results1 = checknames($cuenta1[0], $matches1, $r['name'], $parts, $r, $db, $mail);

echo "OKr1 CR1: ".count($results1)." P2.1: $pars1 P: $parts\n";

		if (abs(count($results1) + $pars - $parts) < abs(count($results) + $pars1 -$parts) && count($results1) < 2 * $parts)
		{
echo "using OKr1\n";
			$matches = $matches1;
			$cuenta[0] = $cuenta1[0];
			$results = $results1;
			$relname1 = $relname;
			$pars1 = $pars;
		}

		$pars = $pars1;
		$relname = $relname1;
		if (count($results) > $parts)
		{
			$matches1 = checkmatches($query, $r, true);

			$relname1 = $relname;

			$pars1 = $pars;

			$results1 = checknames($cuenta1[0], $matches1, $r['name'], $parts, $r, $db, $mail);

	echo "OKr2 CR2: ".count($results1)." P2.2: $pars1 P: $parts\n";

			if (abs(count($results1) + $pars - $parts) < abs(count($results) + $pars1 -$parts) && count($results1) < 2 * $parts)
			{
	echo "using OKr2\n";
				$matches = $matches1;
				$cuenta[0] = $cuenta1[0];
				$results = $results1;
				$relname1 = $relname;
				$pars1 = $pars;
			}

			$pars = $pars1;
			$relname = $relname1;

		}
	}
}

function domatching ($pattern, $db, $r, $oldname, $sect)
{
	global $notyet, $jump;

	global $ptr;

	global $nzb, $nfo;

	global $pars, $files;

	global $relname;

	$mail = preg_split('/(\b[a-z0-9\.\_\-\' ]+?\b@\b[a-z0-9\.\_\-\' ]+?\b)/Ui', $r['fromname'], 0, PREG_SPLIT_DELIM_CAPTURE);

	if (!isset($mail[1]))
	{
		$mail[1] = $r['fromname'];
	}

	$mail[1] = preg_replace('/^\'|\'$/','', $db->escapeString($mail[1]));

	$cuenta = preg_split($pattern, removemail($r['name'], $mail[1]), 0, PREG_SPLIT_DELIM_CAPTURE);

	if (isset($cuenta[1]) && $notyet)
	{

	echo "doing ".$sect."\n";
//	var_dump($cuenta);
//	echo "\n";

	echo "1: ".$r['name']."\n";
	echo "2: ".$cuenta[0]."\n";

		$cuenta[0] = getname($cuenta);

		if (strlen($cuenta[0]) <= 3)
		{
			//$cuenta[0] = $cuenta[count($cuenta)-1];
		}

	echo "3: ".preg_quote($cuenta[0])."\n";

		$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString(removemail($cuenta[0], $mail[1]))." IN NATURAL LANGUAGE MODE)";
//		$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString($r['name'])." IN NATURAL LANGUAGE MODE)";

		$query = $query." AND `fromname` like '%".$mail[1]."%'";

		$query = $query." AND name like '%".preg_replace('/\'/','\\\'', $cuenta[1])."%'";

		$matches = checkmatches($query, $r);

		$parts = intval(preg_replace('/[^\d]/','', $cuenta[1]));

//	echo $query."\n";

//	echo count($matches)."\n";
	echo $cuenta[1]."\n";

		$results = checknames($cuenta[0] , $matches, $r['name'], $parts, $r, $db, $mail[1]);

		$patern  = $pattern;
		$patern2 = '/(\.(\b[a-z][a-z0-9]{2}?\b|\bNFO|\bnfo\b|p\d{1,4}?|par2|part\d{1,4}?|vol\d{1,4}?\+\d{1,4}?)(?!\]).+?)+?/iU';
		okresults ($results, $cuenta, $parts, 2, $patern, $patern2, $r, $mail[1], false);

		$patern  = $pattern;
		$patern2 = '/(\.(\b[a-z][a-z0-9]{2}?\b|\bNFO|\bnfo\b|p\d{1,4}?|par2|part\d{1,4}?|vol\d{1,4}?\+\d{1,4}?)(?!\]).+?)+?/U';
		okresults ($results, $cuenta, $parts, 0, $patern, $patern2, $r, $mail[1], false);

		$patern  = '/([^\"\)\]]+?)(\.(\b[a-z][a-z0-9]{2}?\b|\bNFO|\bnfo\b|info\b|p\d{1,4}?|par2|part\d{1,4}?|vol\d{1,4}?\+\d{1,4}?))+?/U';
		$patern2 = '/(\.(\b[a-z][a-z0-9]{2}?\b|\bNFO|\bnfo\b|p\d{1,4}?|par2|part\d{1,4}?|vol\d{1,4}?\+\d{1,4}?))+?(?!\])/U';
		okresults ($results, $cuenta, $parts, 1, $patern, $patern2, $r, $mail[1], true);

		$nfo1 = $nfo;
		$nzb1 = $nzb;
		$pars1 =$pars;
		$cuenta1 = $cuenta;
		$relname1 = $relname;

echo "OK end CR: ".count($results)." P: ". $parts." P2: ".$pars."\n";

		$results1 = checknames($r['name'], $matches, $r['name'], $parts, $r, $db, $mail[1]);

		if (abs(count($results1) + $pars - $parts) < abs(count($results) + $pars1 -$parts) && count($results1) < 2 * $parts)
		{
			$results = $results1;
			$relname1 = $relname;
			$pars1 = $pars;
		}

		$pars = $pars1;
		$relname = $relname1;

echo "OK end CR: ".count($results)." P: ". $parts." P2: ".$pars."\n";

//	echo "6: ".$cuenta[0]."\n";
		$name = getname($cuenta);

//	echo "7: ".$name."\n";

		//$results = checknames($name, $matches, $r['name'], $parts, $r, $db, $mail[1]);


	echo $ptr." N:".$name." CM:".count($matches)." P:".$parts." CR:".count($results)." ".$r['age']." days ".$sect." ".$r['ID']."\n";
//	echo $query."\n";


	echo "stats: P2:".$pars." CR:".count($results)." P:".$parts." F:".$files." N:".$nzb."\n";


		//if ((count($results) +1 >= $parts || ($nzb && $nfo && $parts < 4)) && $oldname != $name)
		if (($nzb != 0 || $nzb1 != 0) && $files == 0)
		{
echo "D1\n";
			makenzb($nzb, $nzb1, $nfo, $nfo1);
			$jump = $jump && clearmatches($name, $results, -3);
		} else if (((((count($results) + $pars >= $parts) && ($files > 0 || count($results) == $parts)) || (count($results) >= $parts && ($files > 0 || count($results) == $parts))) && ($files <= 2 * $parts)) && $oldname != $name)
		{
//	echo "makerelease ".$sect."\n";
echo "D2\n";
			$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
		} else if ((((count($results) + $pars >= $parts) && $files > 2) || (count($results) >= $parts && $files > 2)) && ($files < 2 * $parts))
		{
echo "D3\n";
			$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
		} else if (($nzb != 0 || $nzb1 != 0) && ($r['age'] >= 3 || $files == 0))
		{
echo "D4\n";
			makenzb($nzb, $nzb1, $nfo, $nfo1);
			$jump = $jump && clearmatches($name, $results, -3);
		} else if (count($results) >= $parts && $parts == 2 && $files == 2) {
echo "doing a 2 part release\n";
			$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
		}  else if (count($results) == 1 && $parts == 1 && $r['totalParts'] > 10 && $nzb == 0 && $nzb1 == 0) {
echo "doing a 1 part release\n";
			$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
		} else if (count($results)  <= 2 * $parts || count($results) > 0) {
echo "clearmatches ".$sect."\n";
echo "stats: ".$pars." ".count($results)." ".$parts." ".$files." ".$nzb."\n";
			$jump = $jump && clearmatches('', $results);
		} else {
echo "skipping\n";
echo "stats: ".$pars." ".count($results)." ".$parts." ".$files." ".$nzb."\n";
		}
	$notyet = false;
	}
}

function domatching2 ($pattern, $db, $r, $oldname, $sect)
{
	global $notyet, $jump;

//	global $ptr;

//	global $nzb, $nfo;

//	global $pars, $files;

	global $relname;

	$mail = preg_split('/(\b[a-z0-9\.\_\-\' ]+?\b@\b[a-z0-9\.\_\-\' ]+?\b)/Ui', $r['fromname'], 0, PREG_SPLIT_DELIM_CAPTURE);

	if (!isset($mail[1]))
	{
		$mail[1] = $r['fromname'];
	}

	$mail[1] = preg_replace('/^\'|\'$/','', $db->escapeString($mail[1]));

	$cuenta = preg_split($pattern, removemail($r['name'], $mail[1]), 0, PREG_SPLIT_DELIM_CAPTURE);

var_dump($cuenta);

	if (isset($cuenta[1]) && $notyet)
	{
		echo "d2 1: ".$r['name']."\n";
		echo "d2 2: ".$cuenta[0]."\n";
		if (strlen($cuenta[0]) <= 3)
		{
			$cuenta[0] = $cuenta[1];
		}

	echo "doing ".$sect."\n";
		$oldname = $cuenta[0];
//		$cuenta[0] = getname($cuenta);
		$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString($cuenta[0])." IN NATURAL LANGUAGE MODE)";

		if ($pattern == '/^(\[\d+\])/')
		{
			$query = "SELECT * FROM `binaries` where name regexp '^".preg_quote($cuenta[0])."' ";
			$query = str_replace('\\', "\\\\", $query);
		}


		$query = $query." AND `fromname` like '%".$mail[1]."%'";

		$matches = checkmatches($query, $r);

echo "CM ".count($matches)."\n";

		if (count($matches) == 0)
		{
			$cuenta = preg_split($pattern, $r['name'], 0, PREG_SPLIT_DELIM_CAPTURE);

			if (strlen($cuenta[0]) <= 3)
			{
				$cuenta[0] = $cuenta[1];
			}

			$oldname = $cuenta[0];

			$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString($cuenta[0])." IN NATURAL LANGUAGE MODE)";

			$query = $query." AND `fromname` like '%".$mail[1]."%'";

			$matches = checkmatches($query, $r);
		}

		$arr = array();

		foreach ($matches as $value)
			$arr[] = $value['name'];
		natcasesort($arr);
		$arr = array_values($arr);

		$files = array();

		foreach ($matches as $value)
		{
			$key = array_search($value['name'], $arr);

			$files[$key] = $value;
		}

		ksort($files);

		$matches = $files;

		$i = 0;
		$k = 0;
		$q = 0;
		$par = 0;
		$arr = array();
		$files = array();

		foreach ($matches as $value)
		{

			$tmp = str_replace('/', ' ',  $oldname);
			$tmp = str_replace('\\', ' ',  $tmp);

			$tmp = preg_replace('/(\.[a-z][a-z0-9]{2,3})+$/', '', $tmp);

	echo "1100 	".preg_quote($tmp)."\n";
	echo "1100 	".$value['name']."\n";

			if (preg_match('/('.preg_quote($tmp).')/iU', str_replace('/', ' ',  $value['name']), $tmp))
			{
	echo "1101";
	var_dump($tmp);
				preg_match('/^(.+)(?|(?:\.(?:part|r)(\d{1,4}?))|\.(\d{1,4}?)[\]"]|\.(\d{1,4}?)$)/iU', $value['name'], $tmp);
	echo "1102";
	var_dump($tmp);
				if (isset($tmp[2]) && !preg_match('/\.(par2|vol\d{1,4}?\+\d{1,4}?|nzb|nfo)/iU', $value['name']))
				{
					$j = similar_text($cuenta[0], $tmp[1], $p);

					$p = floor($p);

					$a = strval($tmp[2]) + 0;

//	echo "j = $j, p = $p, a= $a\n";

					if ($j > $k || $p > $q)
					{
//	echo "reset\n";
						$i = $a - 1;
						$k = $j;
						$q = $p;
						$arr = array();
					}


					if ($a > $i && $j >= $k && $p >= $q)
					{
						$i = $a;
						$k = $j;
						$q = $p;
						$arr[] = $value;
					}
				} else {
					$par = $par + $value['totalParts'];
					$files[] = $value;
				}
			}
		}

		if (count($arr) > 0 && preg_match('/^(.+)(?|(?:\.(?:part|r)(\d{1,4}?))|\.(\d{1,4}?)[\]"]|\.(\d{1,4}?)$)/iU', $arr[0]['name'], $tmp))
		{
			$arr1 = array();

			foreach ($arr as $value)
				$arr1[] = $value['name'];
			natcasesort($arr1);
			$arr1 = array_values($arr1);

			$files1 = array();

			foreach ($arr as $value)
			{
				$key = array_search($value['name'], $arr1);

				$files1[$key] = $value;
			}

			ksort($files1);

			$arr = $files1;

			$j = $tmp[2] - 1;
			$missing = $j;

			$l = end($arr);

			$l = $l['totalParts'];

			$s = prev($arr);

			$s = $s['totalParts'];

			$tmp= prev($arr);

			$tmp = $tmp['totalParts'];

			if ($tmp > $s)
				$s = $tmp;

			if ($s == 0)
				$s = 1;

			$missing = $missing - ($par / $s);

			foreach ($arr as $a)
			{
				preg_match('/^(.+)(?|(?:\.(?:part|r)(\d{1,4}?))|\.(\d{1,4}?)[\]"]|\.(\d{1,4}?)$)/iU', $a['name'], $tmp);
				{
					if ($tmp[2] -$j != 1)
						$missing = $missing - ($tmp[2] -$j) + 1;
					if ($a['totalParts'] != $s)
						$missing = $missing + 1;
					$l = $a['totalParts'];
				}
				$j = $tmp[2] + 0;
			}

			if ($l < $s)
				$missing = $missing - 1;
			else
				echo "last not smaller\n";

			echo "$i\n";
			if ($missing)
				echo "missing $missing\n";

			$arr = array_merge($arr, $files);

			$name = getname($cuenta);

			$results = checknames($oldname , $arr, $r['name'], count($arr), $r, $db, $mail[1]);

		echo "CR= ".count($results)." AR= ".count($arr)." missing= $missing age= ".$r['age']." l= $l s= $s\n";

			if ($missing <= 0 && $l < $s && $r['age'] > .25 && count($results) == count($arr) && count($results) > 3)
			{
				$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
			} else if ($missing <= 0 && $r['age'] > 2.5 && count($results) == count($arr) && count($results) > 3) {
				$jump = $jump && makerelease($results, $r, $name, $db, $sect, $mail[1]);
			} else
				$jump = $jump && clearmatches('', $results);
		}

//		echo "relname $relname * * * name $name\n";

//		var_dump($results);

//		var_dump($files);
		$notyet = false;
	}
}


$nzb = 0;
$nfo = 0;
$pars = 0;
$files = 0;
$qual = '';

$nntpconnected = false;

$db = new Db;
$nntp = new Nntp;

//$db->query("UPDATE `binaries` SET `procstat`=0 WHERE  `procstat`=-1");

echo "starting auto matcher\n";
$time = microtime(true);

$ptr = 0;
$i = 0;
$j = 0;
$oldname = '';
$name = "*";
$oldid = 0;

if (isset($argv[1]))
	{
		echo "doing ID ".$argv[1]."\n";
	}

do {

$time2 = microtime(true);

echo "\nptr $ptr\n";

if (isset($argv[1]))
	{
//		$query = "SELECT *, hour(TIMEDIFF(now(), `date`))/24 as age from binaries b where b.`ID` in (select a.ID from binaries a where a.`procstat`=0 AND a.groupID = ".$argv[1]." AND a.`name` REGEXP '^\\\\[[0-9]+\\\\]|\\\\.[0-9]{3}\"|\\\\.[0-9]{3}$|\\\\.r[0-9]+\\\\b|\\\\.part[0-9]+|([0-9] ?([o0]f|\\\\/) ?[0-9])|([\\\\(\\\\{\\\\[]?[0-9]+ *(segs|parts))' group by a.binaryhash order by null) limit ".$ptr.", 2";
		$query = "SELECT *, hour(TIMEDIFF(now(), `date`))/24 as age from binaries where `ID` = ".$argv[1];
		$argv[1] = -1;

		$rel = $db->query($query);

	} else {

		$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat = -6");

//		$query = "SELECT *, hour(TIMEDIFF(now(), `date`))/24 as age from binaries b where b.`ID` in (select a.ID from binaries a where a.`procstat`=0 AND a.`name` REGEXP '^\\\\[[0-9]+\\\\]|\\\\.[0-9]{3}\"|\\\\.[0-9]{3}$|\\\\.r[0-9]+\\\\b|\\\\.part[0-9]+|([0-9] ?([o0]f|\\\\/) ?[0-9])|([\\\\(\\\\{\\\\[]?[0-9]+ *(segs|parts))' order by a.`groupID`, a.`name`) group by binaryhash order by null limit ".$ptr.", 2";
		$query = "SELECT *, HOUR (TIMEDIFF(now(), `date`)) / 24 AS age FROM binaries b where b.`ID` in ( SELECT a.ID FROM binaries a WHERE a.`procstat` = 0 GROUP BY a.binaryhash order by a.ID)  limit ".$ptr.", 2";
//		$query = "SELECT *,  hour(TIMEDIFF(now(), `date`))/24 as age from binaries b where b.ID in (select a.ID from binaries a where a.`procstat`=0 AND a.name REGEXP '([0-9] ?([o0]f|\\/) ?[0-9])|([\\(\\{\\[]?[0-9]+ *(segs|parts))' order by a.`groupID`, a.`name`) order by null limit ".$ptr.", 2";
//		$query = "SELECT *, HOUR (TIMEDIFF(now(), `date`)) / 24 AS age FROM binaries b where b.`ID` = 4724491";

		$rel = $db->query($query);
	}

	//$rel = $db->query("SELECT *,  hour(TIMEDIFF(now(), `date`))/24 as age FROM `binaries` where `ID` = 2060224 order by `groupID`, `name` limit ".$ptr.", 5");
	//$rel = $db->query("SELECT *,  hour(TIMEDIFF(now(), `date`))/24 as age FROM `binaries` where `name` LIKE '%Red Dwarf S10E05 %' order by `groupID`, `name` limit ".$ptr.", 5");
	//

	$patern = '/^\[[0-9]+\]|\.[0-9]{3}\"|\.[0-9]{3}$|\.r[0-9]+\b|\.part[0-9]+|([0-9] ?([o0]f|\/) ?[0-9])|([\(\{\[]?[0-9]+ *(segs|parts))/';


$tim = microtime(true) - $time2;
echo "time1 = ".$tim."\n";

echo " * *\n";
echo " * * * \n";
echo " * * * *\n";

	if (isset($rel[0]))
	{
		if ($oldid == $rel[0]['ID'])
		{
			$ptr++;
			//$rel = $db->query("SELECT *  FROM `binaries` WHERE `procstat`=0 and `name` REGEXP '(File|Post) [0-9]+ [o0]f [0-9]+' order by `groupID`, `name` limit ".$ptr.", 5");
			continue;
		}
		$oldid = $rel[0]['ID'];
	}

	$relnum = count($rel);

	//echo "doing select 1 ".$relnum."\n";

	if ($relnum > 0)
	{
//echo "ptr = ".$ptr."\n";
echo "relnum is $relnum\n";
		foreach ($rel as $r)
		{
//			trigger_error($r['procstat']);
			if ($r['procstat'] != 0)
				break;

//			trigger_error($r['name']);

			$relnum = $relnum - 1;

			$j++;

			$suffix = '';
			$notyet = true;
			$jump = true;


	//			$r['name'] = preg_replace('/\'/','',$r['name']);




		echo "name: ".$r['name']." ".$r['ID']."\n";

			$relname = '';

			$pattern = '/[\{\[\(]0*?1\/'.$r['totalParts'].'??[\]\}\)]$/iU';

			$r['name'] = preg_replace($pattern, '', $r['name']);

//	echo "name: ".$r['name']."\n";

//WHERE  `name` REGEXP CONCAT(  '[\{\[\(]0*1\/',  `totalParts` ,  '[\]\}\)]$' ) AND  `totalParts` >10

			$pattern = '/(?:File|Post) \d{1,4}? ?[o0]f ?(\d{1,4}?)/iU';

				domatching($pattern, $db, $r, $oldname, "A");


	//			$pattern = '/\d{1,3}? ?[o0]f ?(\d{1,3}?) ?.*[\[\{\(]\d{1,3}?\/\d{1,3}?[\}\]\)]/iU';

	//			domatching($pattern, $db, $r, $oldname, "B");


			$pattern = '/(?<![a-zA-Z])\d{1,4}? ?(\/ ?\d{1,4}?)/iU';

				domatching($pattern, $db, $r, $oldname, "C");


			$pattern = '/\[\d{1,4}?( ?[o0]f ?\d{1,4}?)\]$/iU';

				domatching($pattern, $db, $r, $oldname, "D");


			$pattern = '/\d{1,4}?( ?[o0]f ?\d{1,4}?)/iU';

				domatching($pattern, $db, $r, $oldname, "E");

			$pattern = '/[\[\(\{]\s*(\d{1,4}?)[^\]\}\)]*(?:segs|parts)/iU';

				domatching($pattern, $db, $r, $oldname, "F");


			$pattern = '/\.(?:part|r)(\d{1,4}?\b)(?![a-z\.])/iU';

				domatching2($pattern, $db, $r, $oldname, "T");

			$pattern = '/\.(?:part|r)(\d{1,4}?\b)\.rar/iU';

				domatching2($pattern, $db, $r, $oldname, "U");

			$pattern = '/(\.\d{2,3}\")|(\.\d{2,3}$)/iU';

				domatching2($pattern, $db, $r, $oldname, "V");

			$pattern = '/^(\[\d+\])/';

				domatching2($pattern, $db, $r, $oldname, "W");

			$pattern = '/(\d{1,2}?-\d{1,2}?\-(?:19|20)\d\d)|((?:19|20)\d\d\-\d{1,2}?-\d{1,2}?)/';
				if (preg_match($pattern, $r['name'])  && $notyet)
					echo "name to match X: ".$r['name']."\n";
				domatching2($pattern, $db, $r, $oldname, "X");

			$pattern = '/(\.rar)/';
				if (preg_match($pattern, $r['name'])  && $notyet)
	//				echo "name to match Z: ".$r['name']."\n";
				if (!preg_match('/(part\d{1,4}?|vol\d{1,4}?\+\d{1,4}?|\.zip|\.p\d{1,4}?|\.\d{1,4}?\"?$)/iU', $r['fromname']))
	//				domatching2($pattern, $db, $r, $oldname, "Z");



			$oldname = $name;

	$tim = microtime(true) - $time2;
	echo "time 2= ".$tim."\n";


			if ($notyet)
			{
				$ptr++;
				echo "*****";
					echo $r['name'];
				echo "*****\n";
					$oldname = $r['name'];
				$jump = false;
			}
			if (!$jump)
			{
	//	echo "doing continue\n";
				//$ptr++;
				continue 2;
			}
		}
	}
} while ($relnum > 0 && $ptr <100000);
echo "finnis ". $i." ".$j."\n";
if ($nntpconnected)
{
	$nntp->doQuit();
	$nntpconnected = false;
}

//unset($db);
unset($nntp);
$time = microtime(true) - $time;
echo "time = ".$time."\n";

//sql code to reset binaries (nothing processed)
//UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE 1 = 1

//sql code to see the releases
//SELECT distinct `relname`, `name`, `reltotalpart`, count(*), `groupID`, `ID`, `procstat`, `regexID` FROM `binaries` where reltotalpart > 0 and procstat = 5 group by `relname` ORDER BY count(*)  DESC
//SELECT relname, SUM(reltotalpart) AS reltotalpart, groupID, reqID, fromname, SUM(num) AS num, CASE WHEN regexID < 0 THEN 1 ELSE coalesce(g.minfilestoformrelease, s.minfilestoformrelease) END as minfilestoformrelease FROM ( SELECT relname, reltotalpart, groupID, reqID, fromname, regexID, COUNT(ID) AS num FROM binaries WHERE procstat = 5 GROUP BY relname, reltotalpart, groupID, reqID, fromname ORDER BY NULL ) x left outer join groups g on g.ID = x.groupID inner join ( select value as minfilestoformrelease from site where setting = 'minfilestoformrelease' ) s GROUP BY relname, groupID, reqID, fromname, minfilestoformrelease  ORDER BY NULL
//SELECT nombre, bID groupID, reqID, SUM(num) AS num FROM ( SELECT name as nombre, relname, `binaries`.`ID` AS bID, `regexID`, reltotalpart, groupID, reqID, fromname, COUNT(ID) AS num FROM binaries WHERE procstat = -1 GROUP BY relname, reltotalpart, groupID, reqID, fromname ORDER BY NULL ) x LEFT OUTER JOIN groups g ON g.ID = x.groupID INNER JOIN ( SELECT VALUE AS minfilestoformrelease FROM site WHERE setting = 'minfilestoformrelease' ) s GROUP BY relname, groupID, reqID, fromname ORDER BY `num` desc

//sql code to see what didn't get released
//SELECT nombre, SUM(num) AS num, bid, groupID, CASE WHEN regexID < 0 THEN 1 ELSE coalesce(g.minfilestoformrelease, s.minfilestoformrelease) END as minfilestoformrelease FROM ( SELECT name as nombre, ID as bid, relname, date, reltotalpart, groupID, reqID, fromname, regexID, COUNT(ID) AS num FROM binaries WHERE procstat = -1 GROUP BY relname, reltotalpart, groupID, reqID, fromname ORDER BY NULL ) x left outer join groups g on g.ID = x.groupID inner join ( select value as minfilestoformrelease from site where setting = 'minfilestoformrelease' ) s GROUP BY relname, groupID, reqID, fromname, minfilestoformrelease ORDER BY `num` desc

//sql code to see the state of the binaries (-3 nzb to be dled, -2 spam, -1 can't processed, 0 unprocessed, 5 ready for release
//SELECT  `procstat` , COUNT( * ), sum(totalParts) FROM  `binaries` GROUP BY  `procstat`

//sql code to see how many potential records can be processed by the assmembler
//SELECT count(*) FROM `binaries` where `procstat`=0 AND name REGEXP '([0-9] ?(of|\\/) ?[0-9])|([\\(\\{\\[]?[0-9]+ *(segs|parts))' order by `groupID`, `name`

//SELECT * FROM `binaries` WHERE `date` < DATE_SUB(now(), INTERVAL 4 DAY) AND  `name` LIKE '%.nzb%' and `procstat` != 5 ORDER BY `binaries`.`name` ASC

//sql code to clean orphaned binaries's records
//select *  FROM `binaries` WHERE `ID` NOT IN (SELECT p.`binaryID` FROM `parts` p)
//delete  FROM parts WHERE `binaryID` NOT IN (SELECT b.id FROM binaries b)

//select * FROM `parts` WHERE NOT EXISTS(SELECT ID FROM `binaries`);
//ALTER TABLE  `newznab`.`binaries` ADD INDEX (  `binaryhash` )

?>
