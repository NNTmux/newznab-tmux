<?php

//This id a modified copy of nzb-import.php, to run this you need to copy it to your /www/admin folder. This script will only import 100 nzb's at a time.
//The idea is that you can call this script from your update script every run, and your import will go smooth without babysitting. 

require(dirname(__FILE__)."/config.php");
//require(WWW_DIR.'/lib/adminpage.php');
require_once(WWW_DIR."/lib/framework/db.php");
$db = new DB();
$using_cli = false;

if (empty($argc))
{
	$page = new AdminPage();
}
elseif ($argc == 1)
{
	echo "no arguments specified - php nzb-import.php /path/to/nzb bool_use_filenames\n";
	return;
}
else
{
	$using_cli = true;
}

$filestoprocess = Array();
$browserpostednames = Array();

if ($using_cli || $page->isPostBack() )
{
	$retval = "";

	//
	// Via browser, build an array of all the nzb files uploaded into php /tmp location
	//
	if (isset($_FILES["uploadedfiles"]))
	{
		foreach ($_FILES["uploadedfiles"]["error"] as $key => $error)
		{
			if ($error == UPLOAD_ERR_OK)
			{
				$tmp_name = $_FILES["uploadedfiles"]["tmp_name"][$key];
				$name = $_FILES["uploadedfiles"]["name"][$key];
				$filestoprocess[] = $tmp_name;
				$browserpostednames[$tmp_name] = $name;
			}
		}
	}

	if ($using_cli)
	{
		$strTerminator = "\n";
		$path = $argv[1];
		$usenzbname = (isset($argv[2]) && $argv[2] == 'true') ? true : false;
	}
	else
	{
		$strTerminator = "<br />";
		$path = (isset($_POST["folder"]) ? $_POST["folder"] : "");
		$usenzbname = (isset($_POST['usefilename']) && $_POST["usefilename"] == 'on') ? true : false;
	}

	if (substr($path, strlen($path) - 1) != '/')
		$path = $path."/";

	$groups = $db->query("SELECT ID, name FROM groups");
	foreach ($groups as $group)
		$siteGroups[$group["name"]] = $group["ID"];

	if (!isset($groups) || count($groups) == 0)
	{
		if ($using_cli)
			echo "no groups available in the database, add first.\n";
		else
			$retval.= "no groups available in the database, add first.".$strTerminator;
	}
	else
	{
		$nzbCount = 0;

		//
		// read from the path, if no files submitted via the browser
		//
		if (count($filestoprocess) == 0)
			$filestoprocess = glob($path."*.nzb"); 
		$start=date('Y-m-d H:i:s');

		foreach($filestoprocess as $nzbFile) 
		{
			$importfailed = false;
			$nzb = file_get_contents($nzbFile);

			$xml = @simplexml_load_string($nzb);
			if (!$xml || strtolower($xml->getName()) != 'nzb') 
			{
				continue;
			}

			$i=0;
			foreach($xml->file as $file) 
			{
				//file info
				$groupID = -1;
				$name = (string)$file->attributes()->subject;
				$fromname = (string)$file->attributes()->poster;
				$unixdate = (string)$file->attributes()->date;
				$date = date("Y-m-d H:i:s", (string)$file->attributes()->date);

				//groups
				$groupArr = array();
				foreach($file->groups->group as $group) 
				{
					$group = (string)$group;
					if (array_key_exists($group, $siteGroups)) 
					{
						$groupID = $siteGroups[$group];
					}
					$groupArr[] = $group;
				}

				if ($groupID != -1)
				{
					$xref = implode(': ', $groupArr).':';

					$totalParts = sizeof($file->segments->segment);

					//insert binary
					$binaryHash = md5($name.$fromname.$groupID);
					$binarySql = sprintf("INSERT INTO binaries (name, fromname, date, xref, totalParts, groupID, binaryhash, dateadded, importname) values (%s, %s, %s, %s, %s, %s, %s, NOW(), %s)", 
							$db->escapeString($name), $db->escapeString($fromname), $db->escapeString($date),
							$db->escapeString($xref), $db->escapeString($totalParts), $db->escapeString($groupID), $db->escapeString($binaryHash), $db->escapeString($nzbFile) );

					$binaryId = $db->queryInsert($binarySql);

					if ($usenzbname) 
					{
						$usename = str_replace('.nzb', '', (!$using_cli ? $browserpostednames[$nzbFile] : basename($nzbFile)));

						$db->query(sprintf("update binaries set relname = replace(%s, '_', ' '), relpart = %d, reltotalpart = %d, procstat=%d, categoryID=%s, regexID=%d, reqID=%s where ID = %d", 
							$db->escapeString($usename), 1, 1, 5, "null", "null", "null", $binaryId));
					}

					//segments (i.e. parts)
					if (count($file->segments->segment) > 0)
					{
						$partsSql = "INSERT INTO parts (binaryID, messageID, number, partnumber, size, dateadded) values ";
						foreach($file->segments->segment as $segment) 
						{
							$messageId = (string)$segment;
							$partnumber = $segment->attributes()->number;
							$size = $segment->attributes()->bytes;

							$partsSql .= sprintf("(%s, %s, 0, %s, %s, NOW()),", 
									$db->escapeString($binaryId), $db->escapeString($messageId), $db->escapeString($partnumber), 
									$db->escapeString($size));
						}
						$partsSql = substr($partsSql, 0, -1);
						$partsQuery = $db->queryInsert($partsSql);
					}
				}
				else
				{
					$importfailed = true;
					if ($using_cli)
					{
						echo ("no group found for ".$name." (one of ".implode(', ', $groupArr)." are missing)".$strTerminator);
						flush();
					}
					else
					{
						$retval.= "no group found for ".$name." (one of ".implode(', ', $groupArr)." are missing)".$strTerminator;
					}
					break;
				}
			}

			if (!$importfailed)
			{
				$nzbCount++;
				@unlink($nzbFile);

				if ($using_cli)
				{
					echo ("imported ".$nzbFile.$strTerminator);
					flush();
				}
				else
				{
					$retval.= "imported ".$nzbFile.$strTerminator;
				}
			}


                        //get variables from defaults.sh
                        $path = dirname(__FILE__);
                        $varnames = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \= -f1 | awk '{print $2;}'");
                        $vardata = shell_exec("cat ".$path."/../defaults.sh | grep ^export | cut -d \\\" -f2 | awk '{print $1;}'");
                        $varnames = explode("\n", $varnames);
                        $vardata = explode("\n", $vardata);
                        $array = array_combine($varnames, $vardata);
                        unset($array['']);

			if ($nzbCount == $array['NZBCOUNT'])
			{
			break;
			}
		}
	}
	$seconds = strtotime(date('Y-m-d H:i:s')) - strtotime($start);
	$retval.= 'Processed '.$nzbCount.' nzbs in '.$seconds.' second(s)';

	if ($using_cli)
	{
		echo $retval;
		die();
	}

	$page->smarty->assign('output', $retval);
}

$page->title = "Import Nzbs";
$page->content = $page->smarty->fetch('nzb-import.tpl');
$page->render();


