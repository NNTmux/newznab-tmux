<?php

define('FS_ROOT', realpath(dirname(__FILE__)));
require_once(FS_ROOT . "/../../www/lib/framework/db.php");
require_once(FS_ROOT . "/../../www/lib/releases.php");
require_once(FS_ROOT . "/../../www/lib/nzbinfo.php");
require_once(FS_ROOT . "/../../www/lib/util.php");
require_once(FS_ROOT . "/../../www/lib/nzb.php");
require_once(FS_ROOT . "/../../www/lib/category.php");

$releases = new Releases();
$db = new Db();
$cat = new Category();
$releaseRegex = new ReleaseRegex();
$nzb = new NZB();
$page = new Page();
$num = 0;
$usefilename = true;
$dupecheck = true;
$movefiles = true;
$categoryoverride = -1;

if (empty($argc) || $argc <= 1) {
	$path = "./";
} else {
	$util = new Utility();
	$path = (!$util->endsWith($argv[1], "/") ? $argv[1] . "/" : $argv[1]);
	if (isset($argv[2]))
		$usefilename = strtolower($argv[2]) == 'true';
	if (isset($argv[3]))
		$dupecheck = strtolower($argv[3]) == 'true';
	if (isset($argv[4]))
		$movefiles = strtolower($argv[4]) == 'true';
	if (isset($argv[5]) && is_numeric($argv[5]))
		$categoryoverride = $argv[5];
}

$groups = $db->query("SELECT id, name FROM groups");
foreach ($groups as $group)
	$siteGroups[$group["name"]] = $group["id"];

echo "\nUsage: php import.php [path(string)] [usefilename(true/false)] [dupecheck(true/false)] [movefiles(true/false)] [overridecategory(number)]\n";

$filestoprocess = glob($path . "*.{nzb,nzb.gz}", GLOB_BRACE);
$items = count($filestoprocess);
$matches = array();
$digits = preg_match_all("/[0-9]/", count($filestoprocess), $matches);
$dupepath = $path . "dupe/";
$nogrouppath = $path . "nogroup/";
$importedpath = $path . "imported/";
$noregexpath = $path . "noregex/";
$errorpath = $path . "error/";
$missinggroups = array();
echo "[Importing " . $items . " *.nzb file" . ($items != 1 ? "s" : "") . " from " . $path . ($usefilename ? " U" : " Not u") . "sing filename, " . ($dupecheck ? "C" : "Not c") . "hecking for duplicates" . ($categoryoverride != -1 ? ", Forcing category to " . $categoryoverride : "") . "]\n\n";

foreach ($filestoprocess as $nzbFile) {
	$groupID = -1;
	$groupName = "";
	$num++;
	$nzbInfo = new nzbInfo;

	if (!$nzbInfo->loadFromFile($nzbFile, true)) {
		echo "Failed to load nzb from disk " . $nzbFile . "\n";
		if ($movefiles) {
			if (!file_exists($errorpath)) mkdir($errorpath);
			if (!file_exists($errorpath . basename($nzbFile))) rename($nzbFile, $errorpath . basename($nzbFile));
		}
	} else {
		if ($dupecheck) {
			$dupes = $db->queryOneRow(sprintf("SELECT EXISTS(SELECT 1 FROM releases WHERE gid = %s) as total", $db->escapeString($nzbInfo->gid)));
			if ($dupes['total'] > 0) {
				echo sprintf("%0" . $digits . "d %.2f%% Error : Dupe %s - GID(%s)\n", $items - $num, $num / $items * 100, $nzbFile, $nzbInfo->gid);
				if ($movefiles) {
					if (!file_exists($dupepath)) mkdir($dupepath);
					if (!file_exists($dupepath . basename($nzbFile))) rename($nzbFile, $dupepath . basename($nzbFile));
				}
				continue;
			}
		}

		foreach ($nzbInfo->groups as $group) {
			$group = (string)$group;
			if (array_key_exists($group, $siteGroups)) {
				$groupID = $siteGroups[$group];
				$groupName = $group;
				break;
			}
		}

		if ($groupID == -1) {
			echo sprintf("%0" . $digits . "d %.2f%% Error : Missing group %s - Group(%s)\n", $items - $num, $num / $items * 100, $nzbFile, str_replace("alt.binaries.", "a.b.", implode(",", $nzbInfo->groups)));
			if ($movefiles) {
				if (!file_exists($nogrouppath)) mkdir($nogrouppath);
				if (!file_exists($nogrouppath . basename($nzbFile))) rename($nzbFile, $nogrouppath . basename($nzbFile));
				$missinggroups = array_merge($missinggroups, $nzbInfo->groups);
			}
			continue;
		} else {
			if ($usefilename) {
				$relguid = md5(uniqid());
				$name = $releases->cleanReleaseName(str_replace(".nzb", "", basename($nzbFile)));
				$catId = $cat->determineCategory($groupName, $name);
				$relid = $releases->insertRelease($name, $nzbInfo->filecount, $groupID, $relguid, $catId, "", date("Y-m-d H:i:s", $nzbInfo->postedlast), $nzbInfo->poster, "", $page->site);
				$db->queryExec(sprintf("update releases set totalpart = %d, size = %s, completion = %d, GID=%s where id = %d", $nzbInfo->filecount, $nzbInfo->filesize, $nzbInfo->completion, $db->escapeString($nzbInfo->gid), $relid));

				$nzbfilename = $nzb->getNZBPath($relguid, $page->site->nzbpath, true);
				$fp = gzopen($nzbfilename, "w");
				if ($fp) {
					gzwrite($fp, $nzbInfo->toNzb());
					gzclose($fp);
					echo sprintf("%0" . $digits . "d %.2f%% Imported %s\n", $items - $num, $num / $items * 100, $name);
					if ($movefiles) {
						if (!file_exists($importedpath)) mkdir($importedpath);
						if (!file_exists($importedpath . basename($nzbFile))) rename($nzbFile, $importedpath . basename($nzbFile));
					}
				} else {
					echo sprintf("%0" . $digits . "d %.2f%% Error : Failed to write file to disk %s\n", $items - $num, $num / $items * 100, $nzbfilename);
					if ($movefiles) {
						if (!file_exists($errorpath)) mkdir($errorpath);
						if (!file_exists($errorpath . basename($nzbFile))) rename($nzbFile, $errorpath . basename($nzbFile));
					}
					$releases->delete($relid);
				}
			} else {
				$numbins = 0;
				$numparts = 0;
				$binaryId = 0;
				$groupRegexes = $releaseRegex->getForGroup($groupName);
				foreach ($nzbInfo->nzb as $postFile) {
					$regexMatches = array();

					foreach ($groupRegexes as $groupRegex) {
						$regexCheck = $releaseRegex->performMatch($groupRegex, $postFile["subject"]);
						if ($regexCheck !== false) {
							$regexMatches = $regexCheck;
							break;
						}
					}

					if (!empty($regexMatches)) {
						$relparts = explode("/", $regexMatches['parts']);
						$regexMatches['regcatid'] = ($categoryoverride != -1 ? $categoryoverride : $regexMatches['regcatid']);

						$sql = sprintf("INSERT INTO binaries (name, fromname, date, xref, totalParts, groupid, binaryhash, dateadded,
                        categoryid, regexid, reqid, procstat, relpart, reltotalpart, relname)
                        values (%s, %s, %s, %s, %d, %d, %s, NOW(), %s, %d, %s, %d, %d, %d, %s )",
							$db->escapeString($postFile["subject"]), $db->escapeString($postFile["poster"]),
							$db->escapeString(date("Y-m-d H:i:s", $postFile["posted"])),
							$db->escapeString(implode(': ', $nzbInfo->groups) . ':'),
							$postFile["segmenttotal"], $groupID,
							$db->escapeString(md5($postFile["subject"] . $postFile["poster"] . $groupID)),
							$regexMatches['regcatid'],
							$regexMatches['regexid'], $db->escapeString($regexMatches['reqid']),
							Releases::PROCSTAT_TITLEMATCHED, $relparts[0], $relparts[1], $db->escapeString(str_replace('_', ' ', $regexMatches['name']))
						);
						$binaryId = $db->queryInsert($sql);
						$numbins++;

						if (count($postFile['segments']) > 0) {
							$sql = "INSERT INTO parts (binaryid, messageid, number, partnumber, size) values ";
							foreach ($postFile['segments'] as $fileSegmentNum => $fileSegment) {
								$sql .= sprintf("(%d, %s, 0, %d, %d),", $binaryId, $db->escapeString($fileSegment), $fileSegmentNum, $postFile['segmentbytes'][$fileSegmentNum]);
								$numparts++;
							}
							$db->queryInsert(substr($sql, 0, -1));
						}
					}
				}
				if ($binaryId != 0) {
					echo sprintf("%0" . $digits . "d %.2f%% Imported %s (%d:%s-%d/%d)\n", $items - $num, $num / $items * 100, basename($nzbFile), $regexMatches['regcatid'], $regexMatches['regexid'], $numbins, $numparts);
					if ($movefiles) {
						if (!file_exists($importedpath)) mkdir($importedpath);
						if (!file_exists($importedpath . basename($nzbFile))) rename($nzbFile, $importedpath . basename($nzbFile));
					}
				} else {
					echo sprintf("%0" . $digits . "d %.2f%% Error : No Regex Match %s\n", $items - $num, $num / $items * 100, basename($nzbFile));
					if ($movefiles) {
						if (!file_exists($noregexpath)) mkdir($noregexpath);
						if (!file_exists($noregexpath . basename($nzbFile))) rename($nzbFile, $noregexpath . basename($nzbFile));
					}
				}
			}
		}
	}
}

if (count($missinggroups) > 0) {
	$missinggroups = array_unique($missinggroups);
	$grpsql = "INSERT INTO groups (name, backfill_target, first_record, first_record_postdate, last_record, last_record_postdate, last_updated, minfilestoformrelease, minsizetoformrelease, active, regexmatchonly, description) VALUES ('%s', 0, 0, null, 0, null, null, null, null, 0, 1, 'Added by import');\n";
	$grpout = "";
	foreach ($missinggroups as $mg)
		$grpout .= sprintf($grpsql, $mg);

	@file_put_contents(sprintf("missing_groups_%s.sql", uniqid()), $grpout);
}