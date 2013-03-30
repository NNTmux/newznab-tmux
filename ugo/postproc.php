<?php
require_once(dirname(__FILE__)."/../bin/config.php");
require_once(WWW_DIR."/lib/postprocess.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/nntp.php");
require_once(WWW_DIR."/lib/nzbinfo.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/powerprocess.php");

function watchdog ()
{
	global $buffer, $pid;

	if (isset($buffer))
	{
		$ps = shm_get_var($buffer, 1);
		if (isset($ps->myThreads[$pid]))
			if ($ps->myThreads[$pid]['time'] != time())
			{
				$ps->myThreads[$pid]['time'] = time();
				$lastto = time();
				shm_put_var($buffer, 1, $ps);
	//			trigger_error($pid." ".$ps->myThreads[$pid]['time']);
			}
	} else {
		trigger_error("$pid is orphaned");
	}
}

$postprocess = new PostProcess(true);

$s = new Sites();
$site = $s->get();
$nntp = new Nntp;

$db = new DB;
$nzb = new Nzb;

$maxattemptstocheckpassworded = 5;
$numtoProcess = 5;
$processVideoSample = ($site->ffmpegpath != '') ? true : false;
$processMediainfo = ($site->mediainfopath != '') ? true : false;
$processPasswords = ($site->unrarpath != '') ? true : false;
$processAudioSample = ($site->saveaudiopreview == 1) ? true : false;

$nntpconnected = false;
$lastto = time()+1000;

var_dump($argv);

if (is_numeric($argv[1]))
{
	if (isset($argv[3]))
	{
		$buffer = shm_attach($argv[2]);
		$pid = $argv[3];
	}

	$rel = $db->query(sprintf("select r.ID, r.guid, r.name, c.disablepreview from releases r
		left join category c on c.ID = r.categoryID
		where r.ID = %d ", $argv[1]));

	$rel = $rel[0];

	if ($rel !== false)
	{
		$tmpPath = $site->tmpunrarpath;

		if (substr($tmpPath, -strlen( '/' ) ) != '/')
		{
			$tmpPath = $tmpPath.'/';
		}

		$tmpPath = $tmpPath.$rel['guid'].'/';

		if (!file_exists($tmpPath))
			mkdir($tmpPath, 0766, true);


		// Per release defaults
		$passStatus = array(Releases::PASSWD_NONE);
		$blnTookMediainfo = false;
		$blnTookSample = ($rel['disablepreview'] == 1) ? true : false; //only attempt sample if not disabled

		if ($blnTookSample)
			$db->query(sprintf("update releases set haspreview = 0 where id = %d", $rel['ID']));

		//
		// Go through the binaries for this release looking for a rar, a sample, and a mediafile
		//
		$nzbInfo = new nzbInfo;
		$norar = 0;

		// only load nzbs and check for rar files if we are doing something with them.
		if ($processVideoSample || $processMediainfo || $processPasswords || $processAudioSample)
		{
			$nzbfile = $nzb->getNZBPath($rel['guid'], $site ->nzbpath);
			if (!$nzbInfo->loadFromFile($nzbfile))
			{
				goto end;
			}

			foreach($nzbInfo->nzb as $nzbsubject)
			{
				if (preg_match("/\w\.r00/i", $nzbsubject['subject']))
					$norar= 1;
			}
		}

		// attempt to process video sample file
		if(!empty($nzbInfo->samplefiles) && $processVideoSample && $blnTookSample === false)
		{
			$sampleFile = $nzbInfo->samplefiles[0]; //first detected sample
			$sampleMsgids = array_slice($sampleFile['segments'], 0, 1); //get first segment, increase to get more of the sample
			$sampleGroup = $sampleFile['groups'][0];

			echo "PostPrc : Fetching ".implode($sampleMsgids, ', ')." from {$sampleGroup}\n";
			if (!$nntpconnected)
				$nntpconnected = $nntp->doConnect();

			if ($nntpconnected) {
				$sampleBinary = $nntp->getMessages($sampleGroup, $sampleMsgids);
				watchdog ();
				if ($sampleBinary === false)
					echo "\nPostPrc : Couldnt fetch sample\n";
				else
				{
					$samplefile = $tmpPath.'sample.avi';

					file_put_contents($samplefile, $sampleBinary);

					$blnTookSample = $postprocess->getSample($tmpPath, $site ->ffmpegpath, $rel['guid']);
					if ($blnTookSample)
						$postprocess->updateReleaseHasPreview($rel['guid']);

					unlink($samplefile);
				}
				unset($sampleBinary);
			} else {
				echo "\nFailed to get NNTP connection\n";
			}
		}

		// attempt to process loose media file
		if(!empty($nzbInfo->mediafiles) && (($processVideoSample && $blnTookSample === false) || $processMediainfo))
		{
			$mediaFile = $nzbInfo->mediafiles[0]; //first detected media file
			$mediaMsgids = array_slice($mediaFile['segments'], 0, 2); //get first two segments
			$mediaGroup = $mediaFile['groups'][0];

			echo "PostPrc : Fetching ".implode($mediaMsgids, ', ')." from {$mediaGroup}\n";
			if (!$nntpconnected)
				$nntpconnected = $nntp->doConnect();

			if ($nntpconnected) {
				$mediaBinary = $nntp->getMessages($mediaGroup, $mediaMsgids);
				watchdog ();
				if ($mediaBinary === false)
					echo "\nPostPrc : Couldnt fetch media file\n";
				else
				{
					$mediafile = $tmpPath.'sample.avi';

					file_put_contents($mediafile, $mediaBinary);

					if ($processVideoSample && $blnTookSample === false)
					{
						$blnTookSample = $postprocess->getSample($tmpPath, $site ->ffmpegpath, $rel['guid']);
						if ($blnTookSample)
							$postprocess->updateReleaseHasPreview($rel['guid']);
					}

					if ($processMediainfo)
						$blnTookMediainfo = $postprocess->getMediainfo($tmpPath, $site ->mediainfopath, $rel['ID']);

					unlink($mediafile);
				}
				unset($mediaBinary);
			} else {
				echo "\nFailed to get NNTP connection\n";
			}
		}

		// attempt to process audio sample file
		if(!empty($nzbInfo->audiofiles) && $processAudioSample && $blnTookSample === false)
		{
			$audioFile = $nzbInfo->audiofiles[0]; //first detected audio file
			$audioMsgids = array_slice($audioFile['segments'], 0, 1); //get first segment
			$audioGroup = $audioFile['groups'][0];

			echo "PostPrc : Fetching ".implode($audioMsgids, ', ')." from {$audioGroup}\n";
			if (!$nntpconnected)
				$nntpconnected = $nntp->doConnect();

			if ($nntpconnected) {
				$audioBinary = $nntp->getMessages($audioGroup, $audioMsgids);
				watchdog ();
				if ($audioBinary === false)
					echo "\nPostPrc : Couldnt fetch audio sample\n";
				else
				{
					$audiofile = $tmpPath.'sample.mp3';

					file_put_contents($audiofile, $audioBinary);

					$blnTookSample = $postprocess->getAudioSample($tmpPath, $rel['guid']);
					if ($blnTookSample !== false)
						$postprocess->updateReleaseHasPreview($rel['guid'], 2);

					if ($processMediainfo)
						$blnTookMediainfo = $postprocess->getMediainfo($tmpPath, $site ->mediainfopath, $rel['ID']);

					if ($site ->lamepath != "")
						$postprocess->lameAudioSample($site ->lamepath, $rel['guid']);

					unlink($audiofile);
				}
				unset($audioBinary);
			} else {
				echo "\nFailed to get NNTP connection\n";
			}
		}

		if (!empty($nzbInfo->rarfiles) && ($site ->checkpasswordedrar > 0 || (($processVideoSample || $processAudioSample) && $blnTookSample === false) || $processMediainfo))
		{
			$mysqlkeepalive = 0;
			foreach($nzbInfo->rarfiles as $rarFile)
			{
				//dont process any more rars if a passworded rar has been detected and the site is set to automatically delete them
				if ($site ->deletepasswordedrelease == 1 && max($passStatus) == Releases::PASSWD_RAR)
				{
					echo "-Skipping processing of rar {$rarFile['subject']} as this release has already been marked as passworded.\n";
					goto end;
				}

				$rarMsgids = array_slice($rarFile['segments'], 0, 1); //get first segment
				$rarGroup = $rarFile['groups'][0];

				echo "PostPrc : Fetching ".implode($rarMsgids, ', ')." from {$rarGroup} (".++$mysqlkeepalive.")\n";
				if (!$nntpconnected)
					$nntpconnected = $nntp->doConnect();

				if ($nntpconnected) {
					$fetchedBinary = $nntp->getMessages($rarGroup, $rarMsgids);
					watchdog ();
					if ($fetchedBinary === false)
					{
						echo "\nPostPrc : Failed fetching rar file\n";
						$db->query(sprintf("update releases set passwordstatus = passwordstatus - 1 where ID = %d", $rel['ID']));
							goto end;
					}
					else
					{
						$relFiles = $postprocess->processReleaseFiles($fetchedBinary, $rel['ID']);

						if ($site ->checkpasswordedrar > 0 && $processPasswords)
						{
							$passStatus[] = $postprocess->processReleasePasswords($fetchedBinary, $tmpPath, $site ->unrarpath, $site ->checkpasswordedrar);
						}

						// we need to unrar the fetched binary if checkpasswordedrar wasnt 2
						if ($site ->checkpasswordedrar < 2 && $processPasswords)
						{
							$rarfile = $tmpPath.'rarfile.rar';
							file_put_contents($rarfile, $fetchedBinary);
							$execstring = '"'.$site ->unrarpath.'" e -ai -ep -c- -id -r -kb -p- -y -inul "'.$rarfile.'" "'.$tmpPath.'"';
							$output = runCmd($execstring, false, true);
							unlink($rarfile);
						}

						if ($processVideoSample && $blnTookSample === false)
						{
							$blnTookSample = $postprocess->getSample($tmpPath, $site ->ffmpegpath, $rel['guid']);
							if ($blnTookSample)
								$postprocess->updateReleaseHasPreview($rel['guid']);
						}

						$blnTookAudioSample = false;
						if ($processAudioSample && $blnTookSample === false)
						{
							$blnTookSample = $postprocess->getAudioSample($tmpPath, $rel['guid']);
							if ($blnTookSample)
							{
								$blnTookAudioSample = true;
								$postprocess->updateReleaseHasPreview($rel['guid'], 2);
							}
						}

						if ($processMediainfo && $blnTookMediainfo === false)
						{
							$blnTookMediainfo = $postprocess->getMediainfo($tmpPath, $site ->mediainfopath, $rel['ID']);
						}

						//
						// Has to be done after mediainfo
						//
						if ($blnTookAudioSample && $site ->lamepath != "")
							$postprocess->lameAudioSample($site ->lamepath, $rel['guid']);

						if ($mysqlkeepalive % 25 == 0)
						{
							$db->query("select 1");
							watchdog ();
						}
					}

					//clean up all files
					foreach(glob($tmpPath.'*') as $v)
					{
						unlink($v);
					}
				} else {
					echo "\nFailed to get NNTP connection\n";
				}

			} //end foreach msgid
		}
		elseif(empty($nzbInfo->rarfiles) && $norar == 1)
		{
			$passStatus[] = Releases::PASSWD_POTENTIAL;
		}

		$hpsql = '';
		if (!$blnTookSample)
			$hpsql = ', haspreview = 0';

		$sql = sprintf("update releases set passwordstatus = %d %s where ID = %d", max($passStatus), $hpsql, $rel["ID"]);
		$db->query($sql);

	}
end:
	foreach(glob($tmpPath.'*') as $v)
	{
		unlink($v);
	}
	foreach(glob($tmpPath.'.*') as $v)
	{
		@unlink($v);
	}
	rmdir($tmpPath);

	if ($nntpconnected)
	{
		$nntp->doQuit();
	}
	unset($postprocess);
	unset($s);
	unset($site);
	unset($nntp);
	unset($db);
	unset($nzb);
	unset($nzbInfo);

}
?>
