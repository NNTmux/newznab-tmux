<?php

require_once("lib/innodb/config.php");
require_once("lib/framework/db.php");

$db = new DB();

$retval = "";

if ($argc == 1) {
    echo "no arguments specified - php nzb-import.php /path/to/nzb bool_use_filenames\n";
    return;
}

$filestoprocess = Array();
$browserpostednames = Array();


$strTerminator = "\n";
$path = $argv[1];
$usenzbname = (isset($argv[2]) && $argv[2] == 'true') ? true : false;

if (substr($path, strlen($path) - 1) != '/')
    $path = $path . "/";

$groups = $db->query("SELECT ID, name FROM groups");
foreach ($groups as $group)
    $siteGroups[$group["name"]] = $group["ID"];

if (!isset($groups) || count($groups) == 0) {
    echo "no groups available in the database, add first.\n";
} else {
    $nzbCount = 0;



    if (count($filestoprocess) == 0)
        $filestoprocess = glob($path . "*.nzb");
    $start = date('Y-m-d H:i:s');

    foreach ($filestoprocess as $nzbFile) {
        $importfailed = false;
        $transaction = "";
        $nzb = file_get_contents($nzbFile);

        $xml = @simplexml_load_string($nzb);
        if (!$xml || strtolower($xml->getName()) != 'nzb') {
            continue;
        }

        $i = 0;
        foreach ($xml->file as $file) {
            //file info
            $groupID = -1;
            $name = (string)$file->attributes()->subject;
            $fromname = (string)$file->attributes()->poster;
            $unixdate = (string)$file->attributes()->date;
            $date = date("Y-m-d H:i:s", (string)$file->attributes()->date);

            //groups
            $groupArr = array();
            foreach ($file->groups->group as $group) {
                $group = (string)$group;
                if (array_key_exists($group, $siteGroups)) {
                    $groupID = $siteGroups[$group];
                }
                $groupArr[] = $group;
            }

            if ($groupID != -1) {
                $xref = implode(': ', $groupArr) . ':';

                $totalParts = sizeof($file->segments->segment);

                //insert binary
                $binaryHash = md5($name . $fromname . $groupID);


                if ($usenzbname) { //Use the binary names
                    $usename = str_replace('.nzb', '', basename($nzbFile));

                    $binarySql = sprintf("INSERT INTO binaries (name, fromname, date, xref, totalParts, groupID, binaryhash, dateadded, importname, relname, relpart, reltotalpart, procstat, categoryID, regexID, reqID) values (%s, %s, %s, %s, %s, %s, %s, NOW(), %s, replace(%s, '_', ' '), %d, %d, %d, %s, %d, %s);",
                        $db->escapeString($name), $db->escapeString($fromname), $db->escapeString($date),
                        $db->escapeString($xref), $db->escapeString($totalParts), $db->escapeString($groupID), $db->escapeString($binaryHash), $db->escapeString($nzbFile),
                        $db->escapeString($usename), 1, 1, 5, "null", "null", "null");

                    $transaction = $transaction . $binarySql;

                } else { //Don't use the binary names
                    $binarySql = sprintf("INSERT INTO binaries (name, fromname, date, xref, totalParts, groupID, binaryhash, dateadded, importname) values (%s, %s, %s, %s, %s, %s, %s, NOW(), %s);",
                        $db->escapeString($name), $db->escapeString($fromname), $db->escapeString($date),
                        $db->escapeString($xref), $db->escapeString($totalParts), $db->escapeString($groupID), $db->escapeString($binaryHash), $db->escapeString($nzbFile));

                    $transaction = $transaction . $binarySql;
                }

                $sessionVarSQL = "SET @binID = last_insert_id();";
                $transaction = $transaction . $sessionVarSQL;

                //segments (i.e. parts)
                if (count($file->segments->segment) > 0) {
                    $partsSql = "INSERT INTO parts (binaryID, messageID, number, partnumber, size, dateadded) values ";
                    foreach ($file->segments->segment as $segment) {
                        $messageId = (string)$segment;
                        $partnumber = $segment->attributes()->number;
                        $size = $segment->attributes()->bytes;

                        $partsSql .= sprintf("(%s, %s, 0, %s, %s, NOW()),",
                            "@binID", $db->escapeString($messageId), $db->escapeString($partnumber),
                            $db->escapeString($size));
                    }
                    $partsSql = substr($partsSql, 0, -1) . ";";
                    $transaction = $transaction . $partsSql;
                }

            } else {
                $importfailed = true;

                echo ("no group found for " . $name . " (one of " . implode(', ', $groupArr) . " are missing)" . $strTerminator);
                flush();
                break;
            }
        }

        if (!$importfailed) {
            $db->multiQueryTransaction($transaction);

            $nzbCount++;
            @unlink($nzbFile);

            echo ("imported " . $nzbFile . $strTerminator);
            flush();
        }
        if ($nzbCount == 100) {
            break;
        }
    }
}
$seconds = strtotime(date('Y-m-d H:i:s')) - strtotime($start);
$retval .= 'Processed ' . $nzbCount . ' nzbs in ' . $seconds . ' second(s)';

echo $retval;
die();
