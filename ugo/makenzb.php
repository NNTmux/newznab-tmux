<?php
//define('FS_ROOT', realpath(dirname(__FILE__)));
//require_once(FS_ROOT."/config.php");
require(dirname(__FILE__).'/../../../../../www/config.php');
require_once(WWW_DIR."/lib/nntp.php");
require_once(WWW_DIR."/lib/framework/db.php");

function makenzb($id, $name = '')
{
	global $db;
	$ok = true;
	$path = FS_ROOT."/nzbs/";

	$query = "SELECT *, sum(parts.size) AS size FROM binaries INNER JOIN parts ON binaries.ID = parts.binaryID WHERE binaries.ID = ".$id;
	$row = $db->queryOneRow($query);

	if (!isset($name))
	{
		$name = $row['relname'];
	}

	if (preg_match('/\.nzb\b/i', $row['name']) && $row['size'] < 10000000)
	{
echo "doing nzb\n";
		$nntp = new Nntp;
		if ($nntp->doConnect())
		{
			$bin =  $nntp->getBinary($id);

			$name = preg_replace('/ /','_',$name);

			if (file_exists($path.$name.".nzb"))
			{
				$name = $name." ".$id;
			}

			if (strlen($bin) == 0)
				$ok = false;
			elseif (file_put_contents($path.$name.".nzb", $bin) == 0)
				$ok = false;

		}
		$nntp->doQuit();

		unset($nntp);
		unset($bin);
	}

	if ($ok)
	{
	echo "clearing \n";
		$query = "UPDATE `binaries` SET `procstat` = 6 WHERE ID = ".$id;

		$db->query($query);

	}

}


mb_internal_encoding("UTF-8");

$db = new Db;

if (isset($argv[1]))
{
	if (isset($argv[2]))
	{
		makenzb($argv[1], $argv[2]);
	} else {
		makenzb($argv[1]);
	}
} else {

	$db->query("UPDATE `binaries` SET `binaries`.`procstat` = 6 WHERE `binaries`.`procstat` = - 3 AND binaries.ID NOT IN ( SELECT `parts`.`binaryID` FROM `parts` )");

	$rel = $db->query("SELECT *  FROM `binaries` WHERE `procstat` = -3");

	foreach($rel as $bin)
	{

		$name = $bin['relname'];

		$name = preg_replace('/yenc/iU',' ',$name);

		$name = preg_replace('/\d+?\/\d+?/iU',' ',$name);

		$name = preg_replace('/[\(\)\{\}\[\]]/iU',' ',$name);

		$name = preg_replace('/[\`\'\"\:\/\-\<\>]/iU',' ',$name);

		$name = preg_replace('/\.(?!nzb)/iU',' ',$name);

		$name = preg_replace('/  +?/iU',' ',$name);

		$name = preg_replace('/^ | $/iU','',$name);

		$name = preg_replace('/ /','_',$name);

		$id = $bin['ID'];

	echo "doing $name\n";

		makenzb($id, $name);

	}
}


unset($db);
?>
