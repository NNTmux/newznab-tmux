<?php
require_once NN_LIBS . 'rarinfo/par2info.php';
require_once NN_LIBS . 'rarinfo/archiveinfo.php';

use newznab\db\Settings;
use newznab\utility\Utility;

/**
 * This class handles all post processing performed during update_releases process.
 */
class PostProcess
{

	/**
	 * @var newznab\db\Settings
	 */
	public $pdo;

	/**
	 * @var bool
	 */
	public $echooutput;

	/**
	 * Default constructor.
	 *
	 * @param bool $echooutput
	 */
	public function __construct($echooutput = false)
	{
		$this->echooutput = (NN_ECHOCLI && $echooutput);;
		$this->pdo = new Settings();
		$this->mediafileregex = 'AVI|VOB|MKV|MP4|TS|WMV|MOV|M4V|F4V|MPG|MPEG|M2TS';
		$this->audiofileregex = 'MP3|AAC|OGG';
		$this->mp3SavePath = NN_COVERS .'audio' . DS;
	}

	/**
	 * Perform all post processing.
	 */
	public function processAll()
	{
		$this->processAdditional();
		$this->processNfos();
		$this->processSpotNab();
		$this->processUnwanted();
		$this->processMovies();
		$this->processMusic();
		$this->processBooks();
		$this->processGames();
		$this->processTv();
		$this->processMusicFromMediaInfo();
		$this->processOtherMiscCategory();
		$this->processUnknownCategory();
	}

	public function processUnwanted()
	{
		$r = new Releases;
		$currTime_ori = $this->pdo->queryOneRow("SELECT NOW() as now");

		//
		// Delete any passworded releases
		//
		if($this->pdo->getSetting('deletepasswordedrelease') == 1)
		{
			echo "PostPrc : Removing unwanted releases\n";
			$result = $this->pdo->query("select id from releases where passwordstatus > 0");
			foreach ($result as $row)
				$r->delete($row["id"]);
		}

		//
		// Delete any releases which are older than site's release retention days
		//
		if($this->pdo->getSetting('releaseretentiondays') != 0)
		{
			echo "PostPrc : Deleting releases older than ".$this->pdo->getSetting('releaseretentiondays')." days\n";

			$result = $this->pdo->query(sprintf("select id from releases where postdate < %s - interval %d day", $this->pdo->escapeString($currTime_ori["now"]), $this->pdo->getSetting('releaseretentiondays')));
			foreach ($result as $row)
				$r->delete($row["id"]);
		}

		//
		// Delete any audiopreviews older than site->audiopreviewprune days
		//
		if($this->pdo->getSetting('audiopreviewprune') > 0)
		{
			$result = $this->pdo->query(sprintf("select guid from releases where categoryid in (select id from category where parentid = ".Category::CAT_PARENT_MUSIC.") and haspreview = 2 and adddate < %s - interval %d day", $this->pdo->escapeString($currTime_ori["now"]), $this->pdo->getSetting('audiopreviewprune')));

            if (sizeof($result) > 0)
            {
                echo "PostPrc : Deleting ".count($result)." audio previews older than ".$this->pdo->getSetting('audiopreviewprune')." days\n";
                foreach ($result as $row)
                {
                    $r->updateHasPreview($row["guid"], 0);
                    $this->deleteAudioSample($row["guid"]);
                }
            }
		}

		//
		// Delete any releases suspected of being spam/virus
		//
		if($this->pdo->getSetting('removespam') != 0)
		{
			$spamIDs = [];

			//
			// all releases where the only file inside the rars is *.exe and they are not in the PC category
			//
			$sql = "select releasefiles.releaseid as id from releasefiles inner join  ( select releaseid, count(*) as totnum from  releasefiles group by releaseid ) x on x.releaseid = releasefiles.releaseid and x.totnum = 1 inner join releases on releases.id = releasefiles.releaseid left join releasenfo on releasenfo.releaseid = releases.id where (releasefiles.name like '%.exe' or releasefiles.name like '%.scr') and (releases.categoryid not in (select id from category where parentid = ".Category::CAT_PARENT_PC.") or (releases.categoryid in (select id from category where parentid = ".Category::CAT_PARENT_PC.") and releasenfo.id is null)) group by releasefiles.releaseid";
			$result = $this->pdo->query($sql);
			$spamIDs = array_merge($result, $spamIDs);

			//
			// all releases containing exe not in permitted categories
			//
			if ($this->pdo->getSetting('exepermittedcategories') != '')
			{
				$sql = sprintf("select releasefiles.releaseid as id from releasefiles  inner join releases on releases.id = releasefiles.releaseid  left join releasenfo on releasenfo.releaseid = releases.id  where releasefiles.name like '%%.exe'  and releases.categoryid not in (%s)  group by releasefiles.releaseid", $this->pdo->getSetting('exepermittedcategories'));
				$result = $this->pdo->query($sql);
				$spamIDs = array_merge($result, $spamIDs);
			}

			//
			// delete all releases which contain a file with password.url in it
			//
			$sql = "select distinct releasefiles.releaseid as id from releasefiles where name = 'password.url'";
			$result = $this->pdo->query($sql);
			$spamIDs = array_merge($result, $spamIDs);

			//
			// all releases where the only file inside the rars is *.rar
			//
			$sql = "select releasefiles.releaseid as id from releasefiles  inner join  ( select releaseid, count(*) as totnum from releasefiles group by releaseid ) x on x.releaseid = releasefiles.releaseid and x.totnum = 1 inner join releases on releases.id = releasefiles.releaseid where releasefiles.name like '%.rar' group by releasefiles.releaseid";
			$result = $this->pdo->query($sql);
			$spamIDs = array_merge($result, $spamIDs);

			//
			// all audio which contains a file with .exe in
			//
			$sql = "select distinct r.id from releasefiles rf inner join releases r on r.id = rf.releaseid and r.categoryid in (select id from category where parentid = ".Category::CAT_PARENT_MUSIC.") where (rf.name like '%.exe' or rf.name like '%.bin')";
			$result = $this->pdo->query($sql);
			$spamIDs = array_merge($result, $spamIDs);

			if (count($spamIDs) > 0)
			{
				echo "PostPrc : Deleting ".count($spamIDs)." spam releases\n" ;
				foreach ($spamIDs as $row)
					$r->delete($row["id"]);
			}
		}
	}

	/**
	 * Process nfo files
	 */
	public function processNfos()
	{
		if ($this->pdo->getSetting('lookupnfo') == 1)
		{
			$nfo = new Nfo($this->echooutput);
			$nfo->processNfoFiles();
		}
	}

	/**
	 * Process Global IDs
	 */
	public function processSpotNab()
	{
		$spotnab = new SpotNab();
		$processed = $spotnab->processGID(500);
        if ($processed > 0)
		    printf("PostPrc : Updating GID in releases table %d release(s) updated\n", $processed);
		$spotnab->auto_post_discovery();
		$spotnab->fetch_discovery();
		$spotnab->fetch();
		$spotnab->post();
		$spotnab->auto_clean();
	}

	/**
	 * Lookup imdb if enabled
	 */
	public function processMovies()
	{
		if ($this->pdo->getSetting('lookupimdb') == 1)
		{
			$movie = new Movie($this->echooutput);
			$movie->processMovieReleases();
		}
	}

	/**
	 * Lookup music if enabled
	 */
	public function processMusic()
	{
		if ($this->pdo->getSetting('lookupmusic') == 1)
		{
			$music = new Music($this->echooutput);
			$music->processMusicReleases();
		}
	}

	/**
	 * Lookup book if enabled
	 */
	public function processBooks()
	{
		if ($this->pdo->getSetting('lookupbooks') == 1)
		{
			$book = new Books($this->echooutput);
			$book->processBookReleases();
		}
	}

	/**
	 * Lookup games if enabled
	 */
	public function processGames()
	{
		if ($this->pdo->getSetting('lookupgames') == 1)
		{
			$console = new Console($this->echooutput);
			$console->processConsoleReleases();
		}
	}

	/**
	 * Work out any categories which were not assigned by regex or determinecategory
	 * Done in post process as releasevideo/audio will have been performed by now.
	 */
	public function processUnknownCategory()
	{
		$sql = sprintf("select id from releases where categoryid = %d", Category::CAT_NOT_DETERMINED);
		$result = $this->pdo->query($sql);
		$rescount = sizeof($result);
		if ($rescount > 0)
		{
			echo "PostPrc : Attempting to fix ".$rescount." uncategorised release(s)\n";

			$sql = sprintf("update releases inner join releasevideo rv on rv.releaseid = releases.id set releases.categoryid = %d where imdbid is not null and categoryid = %d and videocodec = 'XVID'", Category::CAT_MOVIE_SD, Category::CAT_NOT_DETERMINED);
			$this->pdo->queryExec($sql);

			$sql = sprintf("update releases inner join releasevideo rv on rv.releaseid = releases.id set releases.categoryid = %d where imdbid is not null and categoryid = %d and videocodec = 'V_MPEG4/ISO/AVC'", Category::CAT_MOVIE_HD, Category::CAT_NOT_DETERMINED);
			$this->pdo->queryExec($sql);

			$sql = sprintf("update releases set categoryid = %d where categoryid = %d", Category::CAT_MISC_OTHER, Category::CAT_NOT_DETERMINED);
			$this->pdo->queryExec($sql);
		}
	}

	/**
	 * Process all TV related releases which will assign their series/episode/rage data
	 */
	public function processTv()
	{
		if ($this->pdo->getSetting('lookupanidb') == 1)
		{
			$anidb = new AniDB($this->echooutput);
			$anidb->animetitlesUpdate();
			$anidb->processAnimeReleases();
		}

		if ($this->pdo->getSetting('lookuptvrage') == 1)
		{
			$tvrage = new TVRage($this->echooutput);
			$tvrage->processTvReleases(($this->pdo->getSetting('lookuptvrage') == 1));
		}

		if ($this->pdo->getSetting('lookupthetvdb') == 1)
		{
			$thetvdb = new TheTVDB($this->echooutput);
			$thetvdb->processReleases();
		}
	}

	/**
	 * Process releases without a proper name
	 */
	public function processOtherMiscCategory()
	{
        $p = null;
        if($this->pdo->getSetting('updatecleanup') == 1)
        {
            $p = new Parsing(false, true, false);
            $p->cleanup();
        }

        if($this->pdo->getSetting('updateparsing') == 1)
        {
            if ($p == null)
                $p = new Parsing(false, true, false);
            $p->process();
        }

        if($this->pdo->getSetting('removespecial') == 1)
        {
            if ($p == null)
                $p = new Parsing(false, true, false);
            $p->removeSpecial();
        }
 	}

	/**
	 * Check for passworded releases, RAR contents and Sample/Media info
	 */
	public function processAdditional($numtoProcess = 100)
	{

		$maxattemptstocheckpassworded = 5;
		$processVideoSample = ($this->pdo->getSetting('ffmpegpath') != '') ? true : false;
		$processMediainfo = ($this->pdo->getSetting('mediainfopath') != '') ? true : false;
		$processPasswords = ($this->pdo->getSetting('unrarpath') != '') ? true : false;
		$processAudioSample = ($this->pdo->getSetting('saveaudiopreview') == 1) ? true : false;

		$tmpPath = $this->pdo->getSetting('tmpunrarpath');
		if (substr($tmpPath, -strlen( '/' ) ) != '/')
		{
			$tmpPath = $tmpPath.'/';
		}

		if (!file_exists($tmpPath))
			mkdir($tmpPath, 0766, true);

		$nntp = new NNTP;
		$nzb = new NZB;

		//
		// Get out all releases which have not been checked more than max attempts for password.
		//
        $sql = sprintf("select r.id, r.guid, r.name, c.disablepreview from releases r
			left join category c on c.id = r.categoryid
			where (r.passwordstatus between %d and -1)
			or (r.haspreview = -1 and c.disablepreview = 0) order by r.postdate desc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess);
		$result = $this->pdo->query($sql);

		$iteration = $rescount = sizeof($result);
		if ($rescount > 0)
		{
			echo "PostPrc : Performing additional post processing on last ".$rescount." releases ...";
			$nntpconnected = false;

			foreach ($result as $rel)
			{
				echo $iteration--.".";

				// Per release defaults
                $passStatus = array(Releases::PASSWD_NONE);
                $blnTookMediainfo = false;
                $blnTookSample = ($rel['disablepreview'] == 1) ? true : false; //only attempt sample if not disabled

                if ($blnTookSample)
                    $this->pdo->queryExec(sprintf("update releases set haspreview = 0 where id = %d", $rel['id']));

                //
                // Go through the binaries for this release looking for a rar, a sample, and a mediafile
                //
                $nzbInfo = new nzbInfo;
                $norar = 0;

                // only load nzbs and check for rar files if we are doing something with them.
                if ($processVideoSample || $processMediainfo || $processPasswords || $processAudioSample)
                {
                    $nzbfile = $nzb->getNZBPath($rel['guid'], $this->pdo->getSetting('nzbpath'));
                    if (!$nzbInfo->loadFromFile($nzbfile))
                    {
                        continue;
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

					//echo "PostPrc : Fetching ".implode($sampleMsgids, ', ')." from {$sampleGroup}\n";
					if (!$nntpconnected)
						$nntpconnected = $nntp->doConnect();

					$sampleBinary = $nntp->getMessages($sampleGroup, $sampleMsgids);
					if ($sampleBinary === false)
						echo "\nPostPrc : Couldnt fetch sample\n";
					else
					{
						$samplefile = $tmpPath.'sample.avi';

						file_put_contents($samplefile, $sampleBinary);

						$blnTookSample = $this->getSample($tmpPath, $this->pdo->getSetting('ffmpegpath'), $rel['guid']);
						if ($blnTookSample)
							$this->updateReleaseHasPreview($rel['guid']);

						unlink($samplefile);
					}
					unset($sampleBinary);
				}

				// attempt to process loose media file
				if(!empty($nzbInfo->mediafiles) && (($processVideoSample && $blnTookSample === false) || $processMediainfo))
				{
					$mediaFile = $nzbInfo->mediafiles[0]; //first detected media file
					$mediaMsgids = array_slice($mediaFile['segments'], 0, 2); //get first two segments
					$mediaGroup = $mediaFile['groups'][0];

					//echo "PostPrc : Fetching ".implode($mediaMsgids, ', ')." from {$mediaGroup}\n";
					if (!$nntpconnected)
						$nntpconnected = $nntp->doConnect();

					$mediaBinary = $nntp->getMessages($mediaGroup, $mediaMsgids);
					if ($mediaBinary === false)
						echo "\nPostPrc : Couldnt fetch media file\n";
					else
					{
						$mediafile = $tmpPath.'sample.avi';

						file_put_contents($mediafile, $mediaBinary);

						if ($processVideoSample && $blnTookSample === false)
						{
							$blnTookSample = $this->getSample($tmpPath, $this->pdo->getSetting('ffmpegpath'), $rel['guid']);
							if ($blnTookSample)
								$this->updateReleaseHasPreview($rel['guid']);
						}

						if ($processMediainfo)
							$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->pdo->getSetting('mediainfopath'), $rel['id']);

						unlink($mediafile);
					}
					unset($mediaBinary);
				}

				// attempt to process audio sample file
				if(!empty($nzbInfo->audiofiles) && $processAudioSample && $blnTookSample === false)
				{
					$audioFile = $nzbInfo->audiofiles[0]; //first detected audio file
					$audioMsgids = array_slice($audioFile['segments'], 0, 1); //get first segment
					$audioGroup = $audioFile['groups'][0];

					//echo "PostPrc : Fetching ".implode($audioMsgids, ', ')." from {$audioGroup}\n";
					if (!$nntpconnected)
						$nntpconnected = $nntp->doConnect();

					$audioBinary = $nntp->getMessages($audioGroup, $audioMsgids);
					if ($audioBinary === false)
						echo "\nPostPrc : Couldnt fetch audio sample\n";
					else
					{
						$audiofile = $tmpPath.'sample.mp3';

						file_put_contents($audiofile, $audioBinary);

						$blnTookSample = $this->getAudioSample($tmpPath, $rel['guid']);
						if ($blnTookSample !== false)
							$this->updateReleaseHasPreview($rel['guid'], 2);

						if ($processMediainfo)
							$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->pdo->getSetting('mediainfopath'), $rel['id']);

						if ($this->pdo->getSetting('lamepath') != "")
							$this->lameAudioSample($this->pdo->getSetting('lamepath'), $rel['guid']);

						unlink($audiofile);
					}
					unset($audioBinary);
				}

				if (!empty($nzbInfo->rarfiles) && ($this->pdo->getSetting('checkpasswordedrar') > 0 || (($processVideoSample || $processAudioSample) && $blnTookSample === false) || $processMediainfo))
				{
					$mysqlkeepalive = 0;
					foreach($nzbInfo->rarfiles as $rarFile)
					{
						//dont process any more rars if a passworded rar has been detected and the site is set to automatically delete them
						if ($this->pdo->getSetting('deletepasswordedrelease') == 1 && max($passStatus) == Releases::PASSWD_RAR)
						{
							echo "-Skipping processing of rar {$rarFile['subject']} as this release has already been marked as passworded.\n";
							continue;
						}

						$rarMsgids = array_slice($rarFile['segments'], 0, 1); //get first segment
						$rarGroup = $rarFile['groups'][0];

						//echo "PostPrc : Fetching ".implode($rarMsgids, ', ')." from {$rarGroup} (".++$mysqlkeepalive.")\n";
						if (!$nntpconnected)
							$nntpconnected = $nntp->doConnect();

						$fetchedBinary = $nntp->getMessages($rarGroup, $rarMsgids);
						if ($fetchedBinary === false)
						{
							//echo "\nPostPrc : Failed fetching rar file\n";
							$this->pdo->queryExec(sprintf("update releases set passwordstatus = passwordstatus - 1 where id = %d", $rel['id']));
							continue;
						}
						else
						{
							$relFiles = $this->processReleaseFiles($fetchedBinary, $rel['id']);

							if ($this->pdo->getSetting('checkpasswordedrar') > 0 && $processPasswords)
							{
								$passStatus[] = $this->processReleasePasswords($fetchedBinary, $tmpPath, $this->pdo->getSetting('unrarpath'), $this->pdo->getSetting('checkpasswordedrar'));
							}

							// we need to unrar the fetched binary if checkpasswordedrar wasnt 2
							if ($this->pdo->getSetting('checkpasswordedrar') < 2 && $processPasswords)
							{
								$rarfile = $tmpPath.'rarfile.rar';
								file_put_contents($rarfile, $fetchedBinary);
								$execstring = '"'.$this->pdo->getSetting('unrarpath').'" e -ai -ep -c- -id -r -kb -p- -y -inul "'.$rarfile.'" "'.$tmpPath.'"';
								$output = Utility::runCmd($execstring, false, true);
								unlink($rarfile);
							}

							if ($processVideoSample && $blnTookSample === false)
							{
								$blnTookSample = $this->getSample($tmpPath, $this->pdo->getSetting('ffmpegpath'), $rel['guid']);
								if ($blnTookSample)
									$this->updateReleaseHasPreview($rel['guid']);
							}

							$blnTookAudioSample = false;
							if ($processAudioSample && $blnTookSample === false)
							{
								$blnTookSample = $this->getAudioSample($tmpPath, $rel['guid']);
								if ($blnTookSample)
								{
									$blnTookAudioSample = true;
									$this->updateReleaseHasPreview($rel['guid'], 2);
								}
							}

							if ($processMediainfo && $blnTookMediainfo === false)
							{
								$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->pdo->getSetting('mediainfopath'), $rel['id']);
							}

							//
							// Has to be done after mediainfo
							//
							if ($blnTookAudioSample && $this->pdo->getSetting('lamepath') != "")
								$this->lameAudioSample($this->pdo->getSetting('lamepath'), $rel['guid']);

							if ($mysqlkeepalive % 25 == 0)
								$this->pdo->query("select 1");
						}

						//clean up all files
						foreach(glob($tmpPath.'*') as $v)
						{
							unlink($v);
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

				$sql = sprintf("update releases set passwordstatus = %d %s where id = %d", max($passStatus), $hpsql, $rel["id"]);
				$this->pdo->queryExec($sql);

			} //end foreach result

			if ($nntpconnected)
			{
				$nntp->doQuit();
			}

			echo "\n";
		}
	}

	/**
	 * Work out all files contained inside a rar
	 */
	public function processReleaseFiles($fetchedBinary, $relid)
	{
		$retval = [];
        $rar = new \ArchiveInfo();
		$rf = new ReleaseFiles;

        $rar->setData($fetchedBinary, true);
        if ($rar->error)
        {
            return false;
        }

        $files = $rar->getArchiveFileList();
        if ($files !== false)
        {
            foreach ($files as $file)
            {
                if (isset($file['name']))
                {
                    $rf->add($relid, utf8_encode($file['name']), (isset($file['size']) ? $file['size'] : ""), (isset($file['date']) ? $file['date'] : ""), (isset($file['pass']) ? $file['pass'] : "") );
                    $retval[] = $file['name'];
                }
            }
        }

		unset($fetchedBinary);
		return $retval;
	}


	/**
	 * Work out if a release is passworded
	 */
	public function processReleasePasswords($fetchedBinary, $tmpPath, $unrarPath, $checkpasswordedrar)
	{
		$passStatus = Releases::PASSWD_NONE;
        $potentiallypasswordedfileregex = "/\.(ace|cab|tar|gz|url)$/i";
        $definetlypasswordedfileregex = "/password\.url/i";
        $rar = new ArchiveInfo();
		$filecount = 0;

		$rarfile = $tmpPath.'rarfile.rar';
		file_put_contents($rarfile, $fetchedBinary);

		if ($rar->open($rarfile))
		{
            $rarsummary = $rar->getSummary(true, false);
            if ((isset($rarsummary["is_encrypted"]) && $rarsummary["is_encrypted"] != 0) || !empty($rar->isEncrypted))
            {
                $passStatus = Releases::PASSWD_RAR;
            }
			else
			{
				$files = $rar->getArchiveFileList();

				if ($files !== false)
				{
					foreach ($files as $file)
					{
						if (isset($file['pass']) && isset($file["name"]))
						{
							$filecount++;
							//
							// individual file rar passworded
							//
							if ($file['pass'] == true || preg_match($definetlypasswordedfileregex, $file["name"]))
							{
								$passStatus = Releases::PASSWD_RAR;
							}

							//
							// individual file looks suspect
							//
							elseif (preg_match($potentiallypasswordedfileregex, $file["name"]) && $passStatus != Releases::PASSWD_RAR)
							{
								$passStatus = Releases::PASSWD_POTENTIAL;
							}
						}
					}
				}


				//
				// Deep Checking
				//
				if ($checkpasswordedrar == 2)
				{

					$israr = $this->isRar($rarfile);
					for ($i=0;$i<sizeof($israr);$i++)
					{
						if (preg_match('/\\\\/',$israr[$i]))
						{
							$israr[$i] = ltrim((strrchr($israr[$i],"\\")),"\\");
						}
					}

					$execstring = '"'.$unrarPath.'" e -ai -ep -c- -id -r -kb -p- -y -inul "'.$rarfile.'" "'.$tmpPath.'"';
					$output = Utility::runCmd($execstring, false, true);

					// delete the rar
					@unlink($rarfile);

					// ok, now we have all the files extracted from the rar into the tempdir and
					// the rar file deleted, now to loop through the files and recursively unrar
					// if any of those are rars, we don't trust their names and we test every file
					// for the rar header
					for ($i=0;$i<sizeof($israr);$i++)
					{
						// even though its in the rar filelist there may not have been enough data
						// to extract this file so dont attempt to read the file if it doesnt exist
						if (!file_exists($tmpPath.$israr[$i]))
							continue;

						$tmp = $this->isRar($tmpPath.$israr[$i]);

						if (is_array($tmp))
						// it's a rar
						{
							for ($x=0;$x<sizeof($tmp);$x++)
							{
								if (preg_match('/\\\\/',$tmp[$x]))
								{
									$tmp[$x] = ltrim((strrchr($tmp[$x],"\\")),"\\");
								}
								$israr[] = $tmp[$x];
							}

							$execstring = '"'.$unrarPath.'" e -ai -ep -c- -id -r -kb -p- -y -inul "'.$tmpPath.$israr[$i].'" "'.$tmpPath.'"';
							$output2 = Utility::runCmd($execstring, false, true);
							@unlink($tmpPath.$israr[$i]);
						}
						else
						{
							if ($tmp == 1 || $tmp == 2)
							{
								$passStatus = Releases::PASSWD_RAR;
								@unlink($tmpPath.$israr[$i]);
							}
						}
						unset($tmp);
					}
				}
			}
		}
		@unlink($rarfile);
		unset($fetchedBinary);
		return $passStatus;
	}

	/**
	 * Work out if a rar is passworded
	 */
	public function isRar($rarfile)
	{
		// returns 0 if not rar
		// returns 1 if encrypted rar
		// returns 2 if passworded rar
		// returns array of files in the rar if normal rar
        $filelist = [];
		$rar = new ArchiveInfo;
		if ($rar->open($rarfile))
		{
            if (!empty($rar->isEncrypted))
			{
				return 1;
			}
			else
			{
				$files = $rar->getArchiveFileList();
				foreach ($files as $file)
				{
					if (isset($file['pass']) && isset($file["name"]))
					{
						$filelist[] = $file['name'];
						if ($file['pass'] == true)
						//
						// individual file rar passworded
						//
						{
							return 2;
							// passworded
						}
					}
				}
				return ($filelist);
				// normal rar
			}
		}
		else
		{
			return 0;
			// not a rar
		}
	}

	/**
	 * Work out all files contained inside a rar
	 */
	public function getMediainfo($ramdrive,$mediainfo,$releaseID)
	{
		$retval = false;
		$mediafiles = glob($ramdrive.'*.*');
		if (is_array($mediafiles))
		{
			foreach($mediafiles as $mediafile)
			{
				if (preg_match("/\.(".$this->mediafileregex.'|'.$this->audiofileregex.")$/i",$mediafile))
				{
					$execstring = '"'.$mediainfo.'" --Output=XML "'.$mediafile.'"';
					$xmlarray = Utility::runCmd($execstring);

					if (is_array($xmlarray))
					{
						$xmlarray = implode("\n",$xmlarray);
						$re = new ReleaseExtra();
						$re->addFull($releaseID,$xmlarray);
						$re->addFromXml($releaseID,$xmlarray);
						$retval = true;
					}
					else
					{
						echo "PostPrc : Failed to process mediainfo for ".$mediafile." release (".$releaseID.")\n";
					}
				}
			}
		}
		else
		{
			echo "PostPrc: Couldn't open temp drive ".$ramdrive."\n";
		}
		return $retval;
	}

	/**
	 * Get a sample from a release using ffmpeg
	 */
	public function getSample($ramdrive, $ffmpeginfo, $releaseguid)
	{
		$ri = new ReleaseImage();
		$retval = false;

		$samplefiles = glob($ramdrive.'*.*');
		if (is_array($samplefiles))
		{
			foreach($samplefiles as $samplefile)
			{
				if (preg_match("/\.(".$this->mediafileregex.")$/i",$samplefile))
				{
                    //1.0
                    //$execstring = '"'.$ffmpeginfo.'" -sameq -i "'.$samplefile.'" -vframes 300 "'.$ramdrive.'zzzz%03d.jpg"';

                    //1.1
                    //$execstring = '"'.$ffmpeginfo.'" -i "'.$samplefile.'" -qscale:v 1 -vframes 300 "'.$ramdrive.'zzzz%03d.jpg"';

                    //2.0
                    //$execstring = '"'.$ffmpeginfo.'" -i "'.$samplefile.'"  -vframes 300 "'.$ramdrive.'zzzz%03d.jpg"';

                    //1.x
                    $execstring = '"'.$ffmpeginfo.'" -q:v 0 -i "'.$samplefile.'" -vframes 300 "'.$ramdrive.'zzzz%03d.jpg"';

                    Utility::runCmd($execstring, false, true);
					$all_files = scandir($ramdrive,1);
					if(preg_match("/zzzz\d{3}\.jpg/",$all_files[1]))
					{
						$ri->saveImage($releaseguid.'_thumb', $ramdrive.$all_files[1], $ri->imgSavePath, 800, 600);
						$retval = true;
					}

					//clean up all files
					foreach(glob($ramdrive.'*.jpg') as $v)
					{
						unlink($v);
					}
				}
			}
		}
		else
		{
			echo "PostPrc: Couldn't open temp drive ".$ramdrive."\n";
		}
		return $retval;
	}

	/**
	 * Has to be performed after mediainfo, as lame strips id3 tags.
	 */
    public function lameAudioSample($lameinfo, $releaseguid)
    {
        $returnval = false;

        $minacceptableencodefilesize = 10000;
        $samplefile = $this->mp3SavePath.$releaseguid.'.mp3';
        $samplefileogg = $this->mp3SavePath.$releaseguid.'.ogg';
        $ffmpeginfo = $this->pdo->getSetting('ffmpegpath');

        if (file_exists($samplefile))
        {
            $outfile = $this->mp3SavePath.$releaseguid.'_l.mp3';
            $outfileogg = $this->mp3SavePath.$releaseguid.'_l.ogg';

            //
            // lame the sample down to 96kb and replace it. alternatives could be
            // V8 for low quality variable.
            //

            $execstring = '"'.$lameinfo.'" -b 96 "'.$samplefile.'" "'.$outfile.'"';
            $execstringogg = '"'.$ffmpeginfo.'" -i "'.$samplefile.'" -acodec libvorbis "'.$outfileogg.'"';
            $output = Utility::runCmd($execstring, false, true);
            $output = Utility::runCmd($execstringogg, false, true);

            //
            // lame can create bad/small files if the source was corrupt
            // if it creates a file thats surprisingly small, then ignore it and retain
            // original
            //
            if (file_exists($outfile))
            {
                if (filesize($outfile) < $minacceptableencodefilesize)
                    unlink($outfile);
                else
                {
                    unlink($samplefile);
                    rename($outfile, $samplefile);

                    $returnval = true;
                }
            }
            if (file_exists($outfileogg))
            {
                if (filesize($outfileogg) < $minacceptableencodefilesize)
                    unlink($outfileogg);
                else
                {
                    rename($outfileogg, $samplefileogg);
                    $returnval = true;
                }
            }
        }

        return $returnval;
    }

	/**
	 * Get an audio sample from a release.
	 */
	public function getAudioSample($ramdrive, $releaseguid)
	{
		$retval = false;

		$audiofiles = glob($ramdrive.'*.*');
		if (is_array($audiofiles))
		{
			foreach($audiofiles as $audiofile)
			{
				if (preg_match("/\.(".$this->audiofileregex.")$/i",$audiofile))
				{
					if (copy($audiofile, $this->mp3SavePath.$releaseguid.'.mp3') !== false)
						$retval = true;
					else
						echo "PostPrc : Failed to get audio sample from ".$audiofile."\n";
				}
			}
		}
		else
		{
			echo "PostPrc: Couldn't open temp drive ".$ramdrive."\n";
		}
		return $retval;
	}

	/**
	 * Delete an audio sample from a release.
	 */
	public function deleteAudioSample($releaseguid)
	{
		$preview = $this->mp3SavePath.$releaseguid.'.mp3';
		if (file_exists($preview))
			unlink($preview);
	}

	/**
	 * Update release to indicate a preview has been obtained.
	 */
	public function updateReleaseHasPreview($guid, $prevtype=1)
	{
		$rel = new Releases;
		$rel->updateHasPreview($guid, $prevtype);
	}

	/**
	 * Process untagged music releases using information from mediainfo if config permits.
	 */
	public function processMusicFromMediaInfo()
	{
		$processMediainfo = ($this->pdo->getSetting('mediainfopath') != '') ? true : false;
		$processAudioSample = ($this->pdo->getSetting('saveaudiopreview') == 1) ? true : false;
		$processMusic = ($this->pdo->getSetting('lookupmusic') == 1) ? true : false;

		if ($processMusic && $processMediainfo && $processAudioSample)
		{
			$music = new Music($this->echooutput);
			$ret = $music->processMusicReleaseFromMediaInfo();
			return $ret;
		}

		return false;
	}
}
