<?php
require_once(dirname(__FILE__) . '/../bin/config.php');
require_once(WWW_DIR . '/lib/framework/db.php');
require_once(WWW_DIR . '/lib/nntp.php');
require_once(WWW_DIR . '/lib/nzbinfo.php');
require_once(WWW_DIR . '/lib/nzb.php');
require_once(WWW_DIR . '/lib/site.php');
require_once(WWW_DIR . '/lib/category.php');
require_once('functions.php');
require_once('ColorCLI.php');
require_once('nzbcontents.php');
require_once('Pprocess.php');
require_once('Info.php');
require_once('namefixer.php');

$c = new ColorCLI();

$site = (new Sites())->get();
if (empty($site->tmpunrarpath)) {
	exit ('The tmpunrarpath site setting must not be empty!' . PHP_EOL);
}
$tmpPath = $site->tmpunrarpath;
if (substr($site->tmpunrarpath, -1) !== '/') {
	$tmpPath .= '/';
}

$tmpPath .= 'u4e' . '/';

if (!is_dir($tmpPath)) {
	$old = umask(0777);
	@mkdir($tmpPath, 0777, true);
	@chmod($tmpPath, 0777);
	@umask($old);
	if (!is_dir($tmpPath)) {
		exit('Unable to create temp directory:' . $tmpPath . PHP_EOL);
	}
}

if (empty($site->tmpunrarpath)) {
	exit ('The site setting for the unrar path must not be empty!' . PHP_EOL);
}

$db = new DB();
$functions = new Functions();
$nntp = new NNTP;
$nzbContents = new NZBContents(
	[
		'db'   => $db,
		'echo' => true,
		'nfo'  => new Info(true),
		'pp'   => new PProcess(true),
		'nntp' => $nntp
	]
);
$categorize = new Category();

$releases = $db->queryDirect(
	sprintf('
		SELECT rf.name AS filename, r.categoryID, r.name, r.guid, r.ID, r.groupID, r.postdate, r.searchname AS oldname, g.name AS groupname
		FROM releasefiles rf
		INNER JOIN releases r ON rf.releaseID = r.ID
		INNER JOIN groups g ON r.groupID = g.ID
		WHERE (r.isrenamed = 0 OR r.categoryID = 8010)
		AND r.passwordstatus = 0
		AND rf.name %s
		ORDER BY r.postdate DESC',
		$functions->likeString('Linux_2rename.sh')
	)
);

if ($releases !== false) {

	$nntp->doConnect();

	foreach ($releases as $release) {

		// Clear old files.
		foreach (glob($tmpPath . '*') as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}

		// Load up the NZB as a XML file.
		$nzbXML = $nzbContents->LoadNZB($release['guid']);
		if ($nzbXML === false) {
			continue;
		}

		// Try to get the first RAR message-id.
		$messageID = '';
		foreach ($nzbXML->file as $file) {
			if (preg_match('/part0*1\.rar/i', (string)$file->attributes()->subject)) {
				$messageID = (string)$file->segments->segment;
				break;
			}
		}

		// If we didn't find a messageID, try again with a less strict regex.
		if ($messageID === '') {
			foreach ($nzbXML->file as $file) {
				if (preg_match('/\.r(ar|0[01])/i', (string)$file->attributes()->subject)) {
					$messageID = (string)$file->segments->segment;
					break;
				}
			}
		}

		if ($messageID === '') {
			echo 'ERROR: Could not find the message-id for the rar file' . PHP_EOL;
			continue;
		}

		$sampleBinary = $nntp->getMessages($release['groupname'], $messageID);
		if ($sampleBinary === false) {
			echo 'ERROR: Could not fetch the binary from usenet.' . PHP_EOL;
			continue;
		} else {
			@file_put_contents($tmpPath . 'u4e_l2r.rar', $sampleBinary);
		}

		if (!is_file($tmpPath . 'u4e_l2r.rar')) {
			echo 'ERROR: Could not write RAR file to temp folder!' . PHP_EOL;
			continue;
		}

		// Extract the RAR file.
		$functions->runCmd(
			'"' .
			$site->unrarpath .
			'" e -ai -ep -c- -id -inul -kb -or -p- -r -y "' .
			$tmpPath . 'u4e_l2r.rar" "' .
			$tmpPath . '"'
		);

		@unlink($tmpPath . 'u4e_l2r.rar');

		$files = scandir($tmpPath);
		if ($files === false) {
			echo 'ERROR: Could not get list of files in temp folder!' . PHP_EOL;
			continue;
		}

		$fileName = '';
		foreach ($files as $file) {
			if (preg_match('/linux.*\.sh/i', $file)) {
				$fileName = $file;
				break;
			}
		}

		if ($fileName === '') {
			echo 'ERROR: Could not find Linux_2rename.sh in the temp folder!' . PHP_EOL;
			continue;
		}

		if (!is_file($tmpPath . $fileName)) {
			echo 'ERROR: The Linux_2rename.sh does not exist!' . PHP_EOL;
			@unlink($tmpPath . $fileName);
			continue;
		}

		$newName = '';
		$handle = @fopen($tmpPath . $fileName, 'r');
		if ($handle) {
			while (($buffer = fgets($handle, 16384)) !== false) {
				if (stripos($buffer, 'mkdir') !== false) {
					$newName = trim(str_replace('mkdir', '', $buffer));
					break;
				}
			}
			fclose($handle);
		}
		@unlink($tmpPath . $fileName);

		if ($newName === '') {
			echo 'ERROR: New name is empty!' . PHP_EOL;
			continue;
		}

		$determinedCat = $categorize->determineCategory($release['groupID'], $newName);

		NameFixer::echoChangedReleaseName(array(
				'new_name'     => $newName,
				'old_name'     => $release['oldname'],
				'new_category' => $functions->getNameByid($determinedCat),
				'old_category' => $functions->getNameByid($release['categoryID']),
				'group'        => $release['groupname'],
				'release_id'   => $release['ID'],
				'method'       => 'lib/rename_u4e.php'
			)
		);

		$db->exec(
			sprintf('
				UPDATE releases
					SET rageid = -1, seriesfull = NULL, season = NULL, episode = NULL,
						tvtitle = NULL, tvairdate = NULL, imdbID = NULL, musicinfoID = NULL,
						consoleinfoID = NULL, bookinfoID = NULL, anidbID = NULL, prehashID = 0,
						searchname = %s, isrenamed = 1, iscategorized = 1, proc_files = 1, categoryID = %d
					WHERE ID = %d',
				$db->escapeString(substr($newName, 0, 255)),
				$determinedCat,
				$release['ID']
			)
		);
	}
	$nntp->doQuit();
}