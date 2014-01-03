<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/category.php");
require_once(WWW_DIR."lib/groups.php");
require_once(WWW_DIR."lib/nfo.php");
require_once(WWW_DIR."lib/site.php");
require_once ("consoletools.php");
require_once ("namecleaner.php");
require_once ("functions.php");
require_once ("ColorCLI.php");

//this script is adapted from nZEDb decrypt_hashes.php
$c = new ColorCLI();
if (!isset($argv[1]) || ( $argv[1] != "all" && $argv[1] != "full" && !is_numeric($argv[1])))
	exit($c->error("\nThis script tries to match an MD5 of the releases.name or releases.searchname to predb.md5.\n"
		."php predb_hash_decrypt.php 1000		...: to limit to 1000 sorted by newest postdate.\n"
		."php predb_hash_decrypt.php full 		...: to run on full database.\n"
		."php predb_hash_decrypt.php all 		...: to run on all hashed releases(including previously renamed).\n"));

echo $c->header ("\nHash Decryption Started at ".date("H:i:s")."\nMatching preDB MD5 to md5(releases.name or releases.searchname)");
preName($argv);

function preName($argv)
{
	$db = new DB();
    $c = new ColorCLI();
	$timestart = TIME();
	if (isset($argv[1]) && $argv[1] === "all")
		$res = $db->queryDirect('SELECT ID, name, searchname, groupID, categoryID FROM releases WHERE (bitwise & 512) = 512');
	else if (isset($argv[1]) && $argv[1] === "full")
		$res = $db->queryDirect('SELECT ID, name, searchname, groupID, categoryID FROM releases WHERE (bitwise & 512) = 512 AND dehashstatus BETWEEN -6 AND 0');
	else if (isset($argv[1]) && is_numeric($argv[1]))
		$res = $db->queryDirect('SELECT ID, name, searchname, groupID, categoryID FROM releases WHERE (bitwise & 512) = 512 AND dehashstatus BETWEEN -6 AND 0 ORDER BY postdate DESC LIMIT '.$argv[1]);
	$total = $res->rowCount();
	$counter = 0;
	$show = '';
	if($total > 0)
	{
	    $precount = $db->queryOneRow('SELECT COUNT(*) AS count FROM predb');
		echo $c->primary ('Comparing '.number_format($total).' releases against '.number_format($precount['count'])." preDB records");
		$consoletools = new ConsoleTools();
		$category = new Category();
        $functions = new Functions();
		$reset = 0;
		$loops = 1;
		$n = "\n";
		foreach ($res as $row)
		{
			$success = false;
			if (preg_match('/([0-9a-fA-F]{32})/', $row['searchname'], $match) || preg_match('/([0-9a-fA-F]{32})/', $row['name'], $match))
			{
				$pre = $db->queryOneRow(sprintf("SELECT dirname FROM predb WHERE hash = %s", $db->escapeString($match[1])));
				if ($pre !== false)
				{
					$determinedcat = $category->determineCategory($row["groupID"], $pre['dirname']);
					$result = $db->prepare(sprintf("UPDATE releases SET dehashstatus = 1, bitwise = ((bitwise & ~37)|37), searchname = %s, categoryID = %d WHERE ID = %d", $db->escapeString($pre['dirname']), $determinedcat, $row['ID']));
                    $result->execute();
                    $total = $result->rowCount();
					if ( $total > 0)
					{
						$groups = new Groups();
                        $functions = new Functions();
						$groupname = $functions->getByNameByID($row["groupID"]);
						$oldcatname = $functions->getNameByID($row["categoryID"]);
						$newcatname = $functions->getNameByID($determinedcat);

						echo $c->primary ($n."New name:  ".$pre['dirname'].$n.
							"Old name:  ".$row["searchname"].$n.
							"New cat:   ".$newcatname.$n.
							"Old cat:   ".$oldcatname.$n.
							"Group:     ".$groupname.$n.
							"Method:    "."preDB md5".$n.
							"ReleaseID: ". $row["ID"].$n);

						$success = true;
						$counter++;
					}
				}
			}
			if ($success == false)
			{
				$db->exec(sprintf("UPDATE releases SET dehashstatus = dehashstatus - 1 WHERE ID = %d", $row['ID']));
			}
		}
	}
	if ($total > 0)
		echo $c->header ("\nRenamed ".$counter." releases in ".$consoletools->convertTime(TIME() - $timestart).".");
	else
		echo $c->info ("\nNothing to do.");
}
