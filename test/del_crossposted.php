<?php

// Crossposted releases.
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."lib/framework/db.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."lib/groups.php");
require_once(WWW_DIR."lib/category.php");
require_once(WWW_DIR."lib/releases.php");
require_once("functions.php");
require_once("consoletools.php");

if (!(isset($argv[1]) && ($argv[1]=="full" || is_numeric($argv[1]))))
	exit("To run this script on full db:\nphp del_crossposted.php full\n\nTo run against last x(where x is numeric) hours:\nphp del_crossposted.php x\n");

crossPost($argv);


function crossPost($argv)
       {
        $db = new DB();
        $s = new Sites();
        $category = new Category();
        $functions = new Functions();
        $consoletools = new ConsoleTools();
        $site = $s->get();
        $dupecount = 0;
        $counter = 1;
        $timer = TIME();

			if (isset($argv[1]) && $argv[1]=="full")
			    $resrel = $db->exec(sprintf("SELECT ID, guid FROM releases GROUP BY GID HAVING COUNT(GID) > 1"));
		    elseif (isset($argv[1]) && is_numeric($argv[1]))
			    $resrel = $db->prepare(sprintf("SELECT ID, guid FROM releases WHERE adddate > (NOW() - INTERVAL %d HOUR) GROUP BY GID HAVING COUNT(GID) > 1", $argv[1]));
                $resrel->execute();
			    $total = $resrel->rowCount();
				if($total > 0)
				{
					foreach ($resrel as $rowrel)
					{
						$functions->fastDelete($rowrel['ID'], $rowrel['guid'], $site);
						$dupecount ++;
                        $consoletools->overWrite("Removed: [".$dupecount."] ".$consoletools->percentString($counter++,$total));
					}
				}
                if ($total > 0)
                     {
	                    echo "\n"."Query took ".(TIME() - $timer)." seconds.\n";
                        echo "Removed ".number_format($dupecount)." crossposted releases.\n";
                     }
                else
                {
                  echo "\n"."Query took ".(TIME() - $timer)." seconds.\n";
                  echo "There were no crossposted releases for removal"."\n";
                }
}





