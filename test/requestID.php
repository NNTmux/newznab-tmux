<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR. "lib/framework/db.php");
require_once(WWW_DIR. "lib/category.php");
require_once(WWW_DIR. "lib/groups.php");
require_once("functions.php");
require_once("ColorCLI.php");
require_once("consoletools.php");

class reqID
{


    public function reqID()
    {
        $c = new ColorCLI();
        echo $c->header ("\nRequestID matching Started at ".date("H:i:s")."\nMatching prehash requestID to release name");
        $db = new DB();
        $functions = new Functions();
        $timestart = TIME();
        $n = "\n";
        $category = new Category();
        $groups = new Groups();
        $res = $db->queryDirect("SELECT r.ID, r.name, g.name AS groupname FROM releases r LEFT JOIN groups g ON r.groupID = g.ID WHERE relnamestatus IN (1, 20, 21, 22) AND reqidstatus in (0, -1)");
        $total = $res->rowCount();
        if ($total > 0)
        {
            foreach ($res as $row)
            {
                if (!preg_match('/^\[\d+\]/', $row['name']))
                {
	                $db->exec('UPDATE releases SET reqidstatus = -2 WHERE ID = ' . $row["ID"]);
	                exit ($c->info ("\nNothing to do."));
                }

                $requestIDtmp = explode(']', substr($row["ID"], 1));
                $bFound = false;
                $newTitle = '';
                $updated = 0;

                if (count($requestIDtmp) >= 1)
                {
	                $requestID = (int) $requestIDtmp;
	                if ($requestID != 0 and $requestID != '')
                    {
		                // Do a local lookup
		                $newTitle = $this->localLookup($requestID, $row["groupname"], $row["name"]);
		                if ($newTitle != false && $newTitle != '')
		                {
			                $bFound = true;
		                }
	                }
                }

                if ($bFound === true)
                {
	                $groupname = $functions->getByNameByID($row["groupname"]);
	                $determinedcat = $category->determineCategory($groupname, $newTitle);
	                $run = $db->prepare(sprintf('UPDATE releases SET reqidstatus = 1, relnamestatus = 12, searchname = %s, categoryID = %d where ID = %d', $db->escapeString($newTitle), $determinedcat, $row["ID"]));
	                $run->execute();
	                $newcatname = $functions->getNameByID($determinedcat);
	                $method = "requestID :";

	                echo 	$c->headerOver($n.$n.'New name:  ').$c->primary($newTitle).
			                $c->headerOver('Old name:  ').$c->primary($oldname).
			                $c->headerOver('New cat:   ').$c->primary($newcatname).
			                $c->headerOver('Group:     ').$c->primary($groupname).
			                $c->headerOver('Method:    ').$c->primary($method).
			                $c->headerOver('ReleaseID: ').$c->primary($row["ID"]);
	                $updated++;
                }
                else
                {
	            $db->exec('UPDATE releases SET reqidstatus = -2 WHERE ID = ' . $row["ID"]);
	            echo '.';
                }

            if ($total > 0)
		        echo $c->header ( "\nRenamed ".$updated." releases.");
	        else
		        echo $c->info ("\nNothing to do.");
            }
    }
    }

    public function localLookup($requestID, $groupName, $oldname)
    {
	    $db = new DB();
	    $groups = new Groups();
        $functions = new Functions();
	    $groupID = $functions->getIDByName($groupName);
	    $run = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	    if (isset($run['title']))
		    return $run['title'];
	    if (preg_match('/\[#?a\.b\.teevee\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
	    else if (preg_match('/\[#?a\.b\.moovee\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.moovee');
	    else if (preg_match('/\[#?a\.b\.erotica\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.erotica');
	    else if (preg_match('/\[#?a\.b\.foreign\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.mom');
        else if ($groupName == 'alt.binaries.etc')
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
            //groups below are added for testing purposes
        else if (preg_match('/\[#?a\.b\.mom\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.mom');
        else if ($groupName == 'alt.binaries.mom')
		    $groupID = $functions->getIDByName('alt.binaries.mom');
        else if ($groupName == 'alt.binaries.town.xxx')
		    $groupID = $functions->getIDByName('alt.binaries.erotica');
        else if (preg_match('/\[#?a\.b\.town\.xxx\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.erotica');
        else if ($groupName == 'alt.binaries.tvseries')
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
        else if (preg_match('/\[#?a\.b\.tvseries\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
        else if ($groupName == 'alt.binaries.x264')
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
        else if (preg_match('/\[#?a\.b\.x264\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
        else if ($groupName == 'alt.binaries.town')
		    $groupID = $functions->getIDByName('alt.binaries.teevee');
        else if (preg_match('/\[#?a\.b\.town\]/', $oldname))
		    $groupID = $functions->getIDByName('alt.binaries.teevee');



	    $run = $db->queryOneRow(sprintf("SELECT title FROM prehash WHERE requestID = %d AND groupID = %d", $requestID, $groupID));
	    if (isset($run['title']))
		    return $run['title'];
        }
}



?>
