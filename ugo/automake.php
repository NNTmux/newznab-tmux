<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
require(dirname(__FILE__).'/../../../../../www/config.php');
//require_once(FS_ROOT."/../bin/config.php");
require_once(WWW_DIR."/lib/nntp.php");

mb_internal_encoding("UTF-8");

function makerelease ($results, $parent, $name, $db, $sect, $mail)
{
	global $relname, $suffix;
	global $qual;
	$PROCSTAT_TITLEMATCHED = 5;

	if ($relname != '' && strlen($relname) > 3)
	{
		$name = $relname;
	}

	$name = cleanstr($name);

	$name = preg_replace('/\(?yenc\)?/iU','',$name);

	$name = preg_replace("/^(\[\d+\].\[.+\].\[#?a.b.[^\]]+\].(\[))/i", " ", $name);

	$name = preg_replace("/\[(?![^\[]+\])|\((?![^\(]+\))|\{(?![^\{]+\})/i", " ", $name);

	$name = preg_replace("/^\(?\?+\)/i", " ", $name);

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

	if (isset($results[0]) && ($results[0]['reltotalpart'] <= $parts) && $parts > 0 && is_string($name))
	{
		$notyet = false;
		echo "doing update: ".$parts." ".count($results)."\n";

		$parts = count($results);

		$query = "Select * from `binaries` where `relname`= ".$db->escapeString($name).";";

		$count = $db->query($query);

		if (count($count) > 0)
		{
			$nombre = $db->escapeString($name." ".$parent["ID"]);
			$notyet = clearmatches ($name, $results, $code = -6);
			return $notyet;
		} else {
			$nombre = $db->escapeString($name);
		}

		foreach ($results as $r)
		{
			$query = sprintf("UPDATE `binaries` SET `relname`=%s, `relpart`=%d, `reltotalpart`=%d, `procstat`=%d, `regexID`=%d, `fromname`='%s' where procstat != 5 AND `ID` = %d", $nombre, $i++, $parts, $PROCSTAT_TITLEMATCHED, -(ord($sect) - ord("A") + 1), $mail, $r["ID"] );

			$db->queryDirect($query);

		}
	} else if ($parts == 1){
	}

	return $notyet;
}

function cleanstr ($str = '', $full = false)
{

	$str = preg_replace('/\(??yenc\)??/iU', '', $str);
 	$str = preg_replace('/(?<=\.\b[a-z][a-z0-9]{2})(\.\b\d{1,4}?\b)(?!\.[0-9])/U', ' ', $str);
	$str1 = preg_replace('/\s*([<>]*?(www\.)?[^\[\<>"]+?(\.\b(com|net|org|info)\b(?!\.[a-zA-Z][a-zA-Z0-9]{2})[^\[\(\"<>]*?))/U',' ', $str);

	if (strlen($str1)>0)
		$str = $str1;

	$str = preg_replace('/(\.\b[a-z][a-z0-9]{2}?\b|\.NFO|\.part[0-9]{1,4}?)+?/U',' ', $str);
	$str = preg_replace('/\.vol\d{1,4}?\+\d{1,4}?|\binfo\b|\.par2\b|\bp\d{1,4}?\b/iU', ' ', $str);

$str = preg_replace('/[\<\>]/iU', ' ', $str);

	$str = preg_replace('/[\"\:]|(?![a-z0-9])\'(?![a-z0-9])/',' ',$str);
	$str = preg_replace('/\-(?!\d\d)/',' ',$str);
	$str = preg_replace('/[\.](?!\d)(?<!\d)/iU', ' ', $str);
	$str = preg_replace('/[\s\.\-]{2,}?/iU', ' ', $str);
	$str = preg_replace('/^[\s\.\]\}\)]+?(?!\d+\])|[\s\.\[\{\(]+?$/iU', '', $str);
	$str = preg_replace('/(\d+?) ?[o0]f ?(\d+?)/iU', '\1 of \2', $str);

	return $str;

}

function getname ($namemat = array())
{
	$name = $namemat[0];

	$name = preg_replace('/^re:|Repost\b(:?)/iU','',$name);
	$name = preg_replace('/^AutoRarPar\d{3,5} /iU','',$name);


	if (strlen($name) <= 3)
	{
		$name = $namemat[count($namemat)-1];
	}

	$name = preg_replace('/^[\]\}\)]/iU','', $name);
	$name = preg_replace('/^ +/iU','', $name);

	$name = cleanstr($name);

	if (strlen($name) <= 3)
	{
		$name = $namemat[2];
		$name = preg_replace('/^[\]\}\)]/iU','', $name);
		$name = preg_replace('/^ +/iU','', $name);

		$name = cleanstr($name);
	}

return cleanstr($name);
}

function splitarray($str)
{
	$str = cleanstr($str);
	$str = preg_replace('/[\(\)\[\]\{\}\+\"]/',' ',$str);
	$str = preg_replace('/\-(?!\d\d)/',' ',$str);

	$str = preg_replace('/[\(\{\[]\d{1,4}?(?!\d\)?)(?! segs| parts)/iU','', $str);
	$str = preg_replace('/(\d*?([\.\,]\d*?)?) ?([kmg]b)/iU','$1$2',$str);

	$str = cleanstr($str);

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

		echo "attn: ".$str."\n";
	}

	if (!preg_match('/\d{1,4}?\/\d{1,4}?/iU', $fullname) || preg_match('/(File|Post) \d{1,4}? ?[o0]f ?\d{1,4}?/iU', $fullname))
	{
		$str = preg_replace('/\d{1,4}? ?([o0]f ?\d{1,4}?)/iU','$1', $post);
	} else {
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

	$postwords = $namemat = splitarray($str);

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

		$keywords = splitarray($str);

		$i = 0;
		foreach ($postwords as $p)
		{
			foreach ($keywords as $k)
			{

				if (strcmp($p,$k) == 0)
				{
					$i++;
				}
			}
		}
		$mode[] = $i;
	}

$mode = array_count_values($mode);
ksort($mode);

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

$index = key($mode);

echo "index ".$index."\n";

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
					$i++;
				}
			}
		}

		if ($i >= $index && $index > 0)
		{

			$actual[] =$m;
			$str = preg_replace('/[\.](?!\d)(?<!\d)/iU',' ', $m['name']);

			if (preg_match('/vol\d{1,4}?\+\d{1,4}?|p\d{2,3}?/iU', $str) >= 1)
			{
				$pars = $pars + $m['totalParts'];
				$extras++;

			} else if (preg_match('/sfv|\bnfo\b|\bnzb\b|par2/Ui', $str) >= 1)
			{
				$extras++;
			} else
			{
				$segsarr[] = $m['totalParts'];
			}

			$str = cleanstr($str);

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
			}
		}
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

if (count($namemat) > 1)
{
	reset($namemat);
	$i = 1;
	$max = count($actual)-1;

	while (next($namemat) < $max && current($namemat) !== false)
	{
		$i++;
	}

	$namemat = array_slice($namemat, $i,count($namemat), true);

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
			if(strripos($str, $key) !== false)
			{
				$i++;
			}
		}
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

	$name = preg_replace('/[\.\-](?!\d)(?<!\d)/iU',' ', $name);

	echo 'name = '.$name."\n";

	$name = preg_split("/[\s_\"]+/", $name, 0, PREG_SPLIT_DELIM_CAPTURE);

	foreach ($name as $n)
	{
		foreach ($namemat as $key => $value)
		{

			if (strcmp($key,$n) == 0)
			{
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

	$qual = '';

	foreach ($namemat as $key => $value)
	{
		foreach($qualities as $q)
		{
			if (stripos ($key , $q) !== false)
			{
					$qual = $qual.' '.$q;
				continue(2);
			}
		}
	}

	if ($nfo == 0)
	{
		$tmp = addnfo ($mail, $r, $db, $relname);

		echo "addnfo: ".count($actual)."\n";

		if (isset($tmp))
		{

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

	return $matches;
}

function makenzb ($nzb, $nzb1, $nfo, $nfo1)
{
	global $nntpconnected, $nntp;
	global $relname;

	if (!$nntpconnected)
	{
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

	}
	if ($nfo == 0)
	{
		$nfo = $nfo1;
	}

	if ($nfo != 0)
	{
echo "getting nfo\n";

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

function okresults (&$results, &$cuenta, $parts, $i, $patern, $patern2, $r, $mail, $dogn = true)
{
	global $pars, $relname;
	global $db;

	echo "OKr CR: ".count($results)." P2: $pars P: $parts\n";

	if (count($results) + $pars < $parts || count($results) > 2 * $parts)
	{
		$cuenta1 = preg_split($patern, $r['name'], 0, PREG_SPLIT_DELIM_CAPTURE);

		if (isset($cuenta1) && $dogn)
		{
			$cuenta1[0] = getname($cuenta1);
		}

		if (!isset($cuenta1[$i]) || strlen($cuenta1[$i]) < 3)
		{
			$cuenta1[$i] = $cuenta1[0];
		}

		$cuenta1[0] = preg_replace($patern2 ,'', $cuenta1[$i]);

echo "ok: ".$cuenta1[0]."\n";

		$query = "SELECT * FROM `binaries` where name like '%".preg_replace('/\'/','\\\'', $cuenta1[0])."%'";

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

	$cuenta = preg_split($pattern, $r['name'], 0, PREG_SPLIT_DELIM_CAPTURE);

	if (isset($cuenta[1]) && $notyet)
	{

	echo "doing ".$sect."\n";

	echo "1: ".$r['name']."\n";
	echo "2: ".$cuenta[0]."\n";

		$cuenta[0] = getname($cuenta);

		if (strlen($cuenta[0]) <= 3)
		{
		}

	echo "3: ".$cuenta[0]."\n";

		$query = "SELECT * FROM `binaries` where match (name) against (".$db->escapeString($cuenta[0])." IN NATURAL LANGUAGE MODE)";

		$mail = preg_split('/(\b[a-z0-9\.\_\-\' ]+?\b@\b[a-z0-9\.\_\-\' ]+?\b)/Ui', $r['fromname'], 0, PREG_SPLIT_DELIM_CAPTURE);

		if (!isset($mail[1]))
		{
			$mail[1] = $r['fromname'];
		}

		$mail[1] = preg_replace('/^\'|\'$/','', $db->escapeString($mail[1]));

		$query = $query." AND `fromname` like '%".$mail[1]."%'";

		$query = $query." AND name like '%".preg_replace('/\'/','\\\'', $cuenta[1])."%'";

		$matches = checkmatches($query, $r);

		$parts = intval(preg_replace('/[^\d]/','', $cuenta[1]));

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

		$name = getname($cuenta);

	echo $ptr." N:".$name." CM:".count($matches)." P:".$parts." CR:".count($results)." ".$r['age']." days ".$sect." ".$r['ID']."\n";

	echo "stats: P2:".$pars." CR:".count($results)." P:".$parts." F:".$files." N:".$nzb."\n";

		if (($nzb != 0 || $nzb1 != 0) && $files == 0)
		{
echo "D1\n";
			makenzb($nzb, $nzb1, $nfo, $nfo1);
			$jump = $jump && clearmatches($name, $results, -3);
		} else if (((((count($results) + $pars >= $parts) && ($files > 0 || count($results) == $parts)) || (count($results) >= $parts && ($files > 0 || count($results) == $parts))) && ($files <= 2 * $parts)) && $oldname != $name)
		{
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

$nzb = 0;
$nfo = 0;
$pars = 0;
$files = 0;
$qual = '';

$nntpconnected = false;

$db = new Db;
$nntp = new Nntp;

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
		echo "doing group ".$argv[1]."\n";
	}

do {

if (isset($argv[1]))
	{
		$query = "SELECT *, hour(TIMEDIFF(now(), `date`))/24 as age from binaries b where b.`ID` in (select a.ID from binaries a where a.`procstat`=0 AND a.groupID = ".$argv[1]." AND a.`name` REGEXP '([0-9] ?([o0]f|\\\\/) ?[0-9])|([\\\\(\\\\{\\\\[]?[0-9]+ *(segs|parts))' group by a.binaryhash order by null) limit ".$ptr.", 2";

		$rel = $db->query($query);
	} else {

		$rel = $db->query("UPDATE `binaries` SET `procstat`=0,`procattempts`=0,`regexID`=NULL, `relpart`=0,`reltotalpart`=0,`relname`=NULL WHERE procstat = -6");

		$query = "SELECT *, hour(TIMEDIFF(now(), `date`))/24 as age from binaries b where b.`ID` in (select a.ID from binaries a where a.`procstat`=0 AND a.`name` REGEXP '([0-9] ?([o0]f|\\\\/) ?[0-9])|([\\\\(\\\\{\\\\[]?[0-9]+ *(segs|parts))' order by a.`groupID`, a.`name`) group by binaryhash order by null limit ".$ptr.", 2";

		$rel = $db->query($query);
	}

echo " * *\n";
echo " * * * \n";
echo " * * * *\n";

	if (isset($rel[0]))
	{
		if ($oldid == $rel[0]['ID'])
		{
			$ptr++;
			continue;
		}
		$oldid = $rel[0]['ID'];
	}

	$relnum = count($rel);

	if ($relnum > 0)
	{
echo "relnum is $relnum\n";
		foreach ($rel as $r)
		{
		$j++;

		$suffix = '';
		$notyet = true;
		$jump = true;

	echo "name: ".$r['name']." ".$r['ID']."\n";

		$relname = '';

			$pattern = '/[\{\[\(]0*?1\/'.$r['totalParts'].'??[\]\}\)]$/iU';

			$r['name'] = preg_replace($pattern, '', $r['name']);

		$pattern = '/(?:File|Post) \d{1,4}? ?[o0]f ?(\d{1,4}?)/iU';

			domatching($pattern, $db, $r, $oldname, "A");

		$pattern = '/(?<![a-zA-Z])\d{1,4}? ?(\/ ?\d{1,4}?)/iU';

			domatching($pattern, $db, $r, $oldname, "C");


		$pattern = '/\[\d{1,4}?( ?[o0]f ?\d{1,4}?)\]$/iU';

			domatching($pattern, $db, $r, $oldname, "D");


		$pattern = '/\d{1,4}?( ?[o0]f ?\d{1,4}?)/iU';

			domatching($pattern, $db, $r, $oldname, "E");

		$pattern = '/[\[\(\{]\s*(\d{1,4}?)[^\]\}\)]*(?:segs|parts)/iU';

			domatching($pattern, $db, $r, $oldname, "F");

		$oldname = $name;

		if ($notyet)
		{
			$ptr++;
			echo "*****";
				echo $r['name'];
			echo "*****\n";
				$oldname = $r['name'];
			$jump = false;
		}
		if (!$jump) {
				continue 2;
			}

		}
	}
} while ($relnum > 0 && $ptr <10000);
echo "finnis ". $i." ".$j."\n";
if ($nntpconnected)
{
	$nntp->doQuit();
	$nntpconnected = false;
}

unset($db);
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
