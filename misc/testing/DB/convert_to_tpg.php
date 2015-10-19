<?php
require_once realpath(dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'indexer.php');

use newznab\db\Settings;
use newznab\Groups;
use newznab\ConsoleTools;


/* This script will allow you to move from single binaries/parts tables to TPG without having to run reset_truncate.
  Please STOP all update scripts before running this script.

  Use the following options to run:
  php convert_to_tpg.php true               Convert c/b/p to tpg leaving current binaries/parts tables in-tact.
  php convert_to_tgp.php true delete        Convert c/b/p to tpg and TRUNCATE current binaries/parts tables.
 */
$debug = false;
$pdo = new Settings();
$groups = new Groups(['Settings' => $pdo]);
$consoletools = new ConsoleTools(['ColorCLI' => $pdo->log]);
$DoPartRepair = ($pdo->getSetting('partrepair') == '0') ? false : true;

if ((!isset($argv[1])) || $argv[1] != 'true') {
	exit($pdo->log->error("\nMandatory argument missing\n\n"
		. "This script will allow you to move from single binaries/parts tables to TPG without having to run reset_truncate.\n"
		. "Please STOP all update scripts before running this script.\n\n"
		. "Use the following options to run:\n"
		. "php $argv[0] true             ...: Convert b/p to tpg leaving current binaries/parts tables in-tact.\n"
		. "php $argv[0] true delete      ...: Convert b/p to tpg and TRUNCATE current binaries/parts tables.\n"
	));
}

$blen = $pdo->queryOneRow('SELECT COUNT(*) AS total FROM binaries;');
$bdone = 0;
$bcount = 1;
$gdone = 1;
$actgroups = $groups->getActive();
$glen = count($actgroups);
$newtables = $glen * 3;
$begintime = time();

echo "Creating new binaries, and parts tables for each active group...\n";

foreach ($actgroups as $group) {
	if ($groups->createNewTPGTables($group['id']) === false) {
		exit($pdo->log->error("There is a problem creating new parts/files tables for group ${group['name']}."));
	}
	$consoletools->overWrite("Tables Created: " . $consoletools->percentString($gdone * 3, $newtables));
	$gdone++;
}
$endtime = time();
echo "\nTable creation took " . $consoletools->convertTime($endtime - $begintime) . ".\n";
$starttime = time();
echo "\nNew tables created, moving data from old tables to new tables.\nThis will take awhile....\n\n";
while ($bdone < $blen['total']) {
	// Only load 1000 binaries per loop to not overload memory.
	$binaries = $pdo->queryAssoc('SELECT * FROM binaries LIMIT ' . $bdone . ',1000;');

	if ($binaries instanceof \Traversable) {
		foreach ($binaries as $binary) {
			$binary['name'] = $pdo->escapeString($binary['name']);
			$binary['fromname'] = $pdo->escapeString($binary['fromname']);
			$binary['date'] = $pdo->escapeString($binary['date']);
			$binary['binaryhash'] = $pdo->escapeString($binary['binarynhash']);
			$binary['dateadded'] = $pdo->escapeString($binary['dateadded']);
			$binary['xref'] = $pdo->escapeString($binary['xref']);
			$binary['releaseid'] = $pdo->escapeString($binary['releaseid']);
			$binary['categoryid'] = $pdo->escapeString($binary['categoryid']);
			$binary['totalparts'] = $pdo->escapeString($binary['totalparts']);
			$binary['relpart'] = $pdo->escapeString($binary['relpart']);
			$binary['reltotalpart'] = $pdo->escapeString($binary['reltotalpart']);
			$oldbid = array_shift($binary);

			if ($debug) {
				echo "\n\nBinaries insert:\n";
				print_r($binary);
				echo sprintf("\nINSERT INTO binaries_%d (name, fromname, date, xref, groupid,  dateadded, releaseid, categoryid, totalparts, binaryhash, relpart, reltotalpart) VALUES (%s)\n\n", $binary['groupid'], implode(', ', $binary));
			}
			$newbid = array('binaryid' => $pdo->queryInsert(sprintf('INSERT INTO binaries_%d (NAME, fromname, date, xref, groupid, dateadded, releaseid, categoryid, totalparts,  binaryhash, relpart, reltotalpart) VALUES (%s);', $binary['groupid'], implode(', ', $binarynew))));

			//Get parts and split to correct group tables.
			$parts = $pdo->queryAssoc('SELECT * FROM parts WHERE binaryid = ' . $oldbid . ';');
			if ($parts instanceof \Traversable) {
				$firstpart = true;
				$partsnew = '';
				foreach ($parts as $part) {
					$oldpid = array_shift($part);
					$partnew = array_replace($part, $newbid);

					$partsnew .= '(\'' . implode('\', \'', $partnew) . '\'), ';
				}
				$partsnew = substr($partsnew, 0, -2);
				if ($debug) {
					echo "\n\nParts insert:\n";
					echo sprintf("\nINSERT INTO parts_%d (binaryid, messageid, number, partnumber, size) VALUES %s;\n\n", $binary['groupid'], $partsnew);
				}
				$sql = sprintf('INSERT INTO parts_%d (binaryid, messageid, number, partnumber, size) VALUES %s;', $binary['groupid'], $partsnew);
				$pdo->queryExec($sql);
			}

		}
		$bcount++;
	}
	$bdone += 1000;
}

if ($DoPartRepair === true) {
	foreach ($actgroups as $group) {
		$pcount = 1;
		$pdone = 0;
		$sql = sprintf('SELECT COUNT(*) AS total FROM partrepair WHERE groupid = %d;', $group['id']);
		$plen = $pdo->queryOneRow($sql);
		while ($pdone < $plen['total']) {
			// Only load 10000 partrepair records per loop to not overload memory.
			$partrepairs = $pdo->queryAssoc(sprintf('SELECT * FROM partrepair WHERE groupid = %d LIMIT %d, 10000;', $group['id'], $pdone));
			if ($partrepairs instanceof \Traversable) {
				foreach ($partrepairs as $partrepair) {
					$partrepair['numberid'] = $pdo->escapeString($partrepair['numberid']);
					$partrepair['groupid'] = $pdo->escapeString($partrepair['groupid']);
					$partrepair['attempts'] = $pdo->escapeString($partrepair['attempts']);
					if ($debug) {
						echo "\n\nPart Repair insert:\n";
						print_r($partrepair);
						echo sprintf("\nINSERT INTO partrepair_%d (numberid, groupid, attempts) VALUES (%s, %s, %s)\n\n", $group['id'], $partrepair['numberid'], $partrepair['groupid'], $partrepair['attempts']);
					}
					$pdo->queryExec(sprintf('INSERT INTO partrepair_%d (numberid, groupid, attempts) VALUES (%s, %s, %s);', $group['id'], $partrepair['numberid'], $partrepair['groupid'], $partrepair['attempts']));
					$consoletools->overWrite('Part Repairs Completed for ' . $group['name'] . ':' . $consoletools->percentString($pcount, $plen['total']));
					$pcount++;
				}
			}
			$pdone += 10000;
		}
	}
}

$endtime = time();
echo "\nTable population took " . $consoletools->convertTimer($endtime - $starttime) . ".\n";

//Truncate old tables to save space.
if (isset($argv[2]) && $argv[2] == 'delete') {
	echo "Truncating old tables...\n";
	$pdo->queryDirect('TRUNCATE TABLE binaries;');
	$pdo->queryDirect('TRUNCATE TABLE parts');
	$pdo->queryDirect('TRUNCATE TABLE partrepair');
	echo "Complete.\n";
}
// Update TPG setting in site-edit.
$pdo->queryExec('UPDATE settings SET value = 1 WHERE setting = \'tablepergroup\';');
$pdo->queryExec('UPDATE tmux SET value = 2 WHERE setting = \'releases\';');
echo "New tables have been created.\nTable Per Group has been set to \"TRUE\" in site-edit.\nUpdate Releases has been set to Threaded in tmux-edit.\n";

function multi_implode($array, $glue)
{
	$ret = '';

	foreach ($array as $item) {
		if (is_array($item)) {
			$ret .= '(' . multi_implode($item, $glue) . '), ';
		} else {
			$ret .= $item . $glue;
		}
	}

	$ret = substr($ret, 0, 0 - strlen($glue));

	return $ret;
}
