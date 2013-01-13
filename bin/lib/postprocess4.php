<?php
require_once(WWW_DIR."/lib/framework/db.php");
require_once(WWW_DIR."/lib/site.php");
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/releases.php");
require_once(WWW_DIR."/lib/rarinfo.php");
require_once(WWW_DIR."/lib/releasefiles.php");
require_once(WWW_DIR."/lib/releaseextra.php");
require_once(WWW_DIR."/lib/releaseimage.php");
require_once(WWW_DIR."/lib/tvrage.php");
require_once(WWW_DIR."/lib/thetvdb.php");
require_once(WWW_DIR."/lib/anidb.php");
require_once(WWW_DIR."/lib/movie.php");
require_once(WWW_DIR."/lib/music.php");
require_once(WWW_DIR."/lib/console.php");
require_once(WWW_DIR."/lib/nfo.php");
require_once(WWW_DIR."/lib/category.php");
require_once(WWW_DIR."/lib/book.php");
require_once(WWW_DIR."/lib/nzbinfo.php");

/**
 * This class handles all post processing performed during update_releases process.
 */
class PostProcess4
{
	
	/**
	 * Default constructor.
	 */
	function PostProcess4($echooutput=false)
	{
		$this->echooutput = $echooutput;
		$s = new Sites();
		$this->site = $s->get();
		
		$this->mediafileregex = 'AVI|VOB|MKV|MP4|TS|WMV|MOV|M4V|F4V|MPG|MPEG';
		$this->audiofileregex = 'MP3|AAC|OGG';
		$this->mp3SavePath = WWW_DIR.'covers/audio/';
	}
	
	/**
	 * Perform all post processing.
	 */
	public function processAll()
	{
		//$this->>processAdditional2();
		//$this->processNfos();
		//$this->processUnwanted();
		//$this->processMovies();
		//$this->processMusic();
		//$this->processBooks();
		//$this->processGames();
		//$this->processTv();
		//$this->processMusicFromMediaInfo();
		//$this->processOtherMiscCategory();
		//$this->processUnknownCategory();
	}
	
	public function processUnwanted()
	{
		$r = new Releases;
		$db = new DB;
		$currTime_ori = $db->queryOneRow("SELECT NOW() as now");
		
		//
		// Delete any passworded releases
		//
		if($this->site->deletepasswordedrelease == 1)
		{
			echo "PostPrc : Removing unwanted releases\n";
			$result = $db->query("select ID from releases where passwordstatus > 0"); 		
			foreach ($result as $row)
				$r->delete($row["ID"]);
		}

		//
		// Delete any releases which are older than site's release retention days
		//
		if($this->site->releaseretentiondays != 0)
		{
			echo "PostPrc : Deleting releases older than ".$this->site->releaseretentiondays." days\n";

			$result = $db->query(sprintf("select ID from releases where postdate < %s - interval %d day", $db->escapeString($currTime_ori["now"]), $this->site->releaseretentiondays)); 		
			foreach ($result as $row)
				$r->delete($row["ID"]);
		}

		//
		// Delete any audiopreviews older than site->audiopreviewprune days
		//
		if($this->site->audiopreviewprune > 0)
		{
			$result = $db->query(sprintf("select guid from releases where categoryID like '3%%' and haspreview = 2 and adddate < %s - interval %d day", $db->escapeString($currTime_ori["now"]), $this->site->audiopreviewprune)); 		
			
			echo "PostPrc : Deleting ".count($result)." audio previews older than ".$this->site->audiopreviewprune." days\n";
			foreach ($result as $row)
			{
				$r->updateHasPreview($row["guid"], 0);
				$this->deleteAudioSample($row["guid"]);
			}
		}		

		//
		// Delete any releases suspected of being spam/virus
		// 
		if($this->site->removespam != 0)
		{
			$spamIDs = array();
			
			//
			// all releases where the only file inside the rars is *.exe and they are not in the PC category
			//
			$sql = "select releasefiles.releaseID as ID from releasefiles inner join  ( select releaseID, count(*) as totnum from  releasefiles group by releaseID ) x on x.releaseID = releasefiles.releaseID and x.totnum = 1 inner join releases on releases.ID = releasefiles.releaseID left join releasenfo on releasenfo.releaseID = releases.ID where (releasefiles.name like '%.exe' or releasefiles.name like '%.scr') and (releases.categoryID not in (4000,4010,4020,4030,4040,4050) or (releases.categoryID in (4000,4010,4020,4030,4040,4050) and releasenfo.ID is null)) group by releasefiles.releaseID";
			$result = $db->query($sql); 		
			$spamIDs = array_merge($result, $spamIDs);

			//
			// all releases containing exe not in permitted categories
			//
			if ($this->site->exepermittedcategories != '')
			{
				$sql = sprintf("select releasefiles.releaseID as ID from releasefiles  inner join releases on releases.ID = releasefiles.releaseID  left join releasenfo on releasenfo.releaseID = releases.ID  where releasefiles.name like '%%.exe'  and releases.categoryID not in (%s)  group by releasefiles.releaseID", $this->site->exepermittedcategories);
				$result = $db->query($sql); 		
				$spamIDs = array_merge($result, $spamIDs);
			}

			//
			// delete all releases which contain a file with password.url in it
			//
			$sql = "select distinct releasefiles.releaseID as ID from releasefiles where name = 'password.url'";
			$result = $db->query($sql); 		
			$spamIDs = array_merge($result, $spamIDs);
			
			//
			// all releases where the only file inside the rars is *.rar
			//
			$sql = "select releasefiles.releaseID as ID from releasefiles  inner join  ( select releaseID, count(*) as totnum from releasefiles group by releaseID ) x on x.releaseID = releasefiles.releaseID and x.totnum = 1 inner join releases on releases.ID = releasefiles.releaseID where releasefiles.name like '%.rar' group by releasefiles.releaseID";
			$result = $db->query($sql); 		
			$spamIDs = array_merge($result, $spamIDs);

			//
			// all audio which contains a file with .exe in
			//
			$sql = "select distinct r.ID from releasefiles rf inner join releases r on r.id = rf.releaseID and r.categoryID like '3%' where rf.name like '%.exe'";
			$result = $db->query($sql); 		
			$spamIDs = array_merge($result, $spamIDs);

			if (count($spamIDs) > 0)
			{
				echo "PostPrc : Deleting ".count($spamIDs)." spam releases\n" ;
				foreach ($spamIDs as $row)
					$r->delete($row["ID"]);
			}
		}
	}
	
	/**
	 * Process nfo files
	 */
	public function processNfos()
	{		
		if ($this->site->lookupnfo == 1)
		{
			$nfo = new Nfo($this->echooutput);
			$nfo->processNfoFiles($this->site->lookupimdb, $this->site->lookuptvrage);		
		}
	}
	
	/**
	 * Lookup imdb if enabled
	 */
	public function processMovies()
	{	
		if ($this->site->lookupimdb == 1) 
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
		if ($this->site->lookupmusic == 1) 
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
		if ($this->site->lookupbooks == 1) 
		{
			$book = new Book($this->echooutput);
			$book->processBookReleases();
		}
	}	
	
	/**
	 * Lookup games if enabled
	 */
	public function processGames()
	{
		if ($this->site->lookupgames == 1) 
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
		$db = new DB;
		$sql = sprintf("select ID from releases where categoryID = %d", Category::CAT_NOT_DETERMINED);	
		$result = $db->query($sql);
		$rescount = sizeof($result);
		if ($rescount > 0)
		{
			echo "PostPrc : Attempting to fix ".$rescount." uncategorised release(s)\n";
		          
			$sql = sprintf("update releases inner join releasevideo rv on rv.releaseID = releases.ID set releases.categoryID = %d where imdbid is not null and categoryid = %d and videocodec = 'XVID'", Category::CAT_MOVIE_SD, Category::CAT_NOT_DETERMINED);
			$result = $db->query($sql);

			$sql = sprintf("update releases inner join releasevideo rv on rv.releaseID = releases.ID set releases.categoryID = %d where imdbid is not null and categoryid = %d and videocodec = 'V_MPEG4/ISO/AVC'", Category::CAT_MOVIE_HD, Category::CAT_NOT_DETERMINED);
			$result = $db->query($sql);

			$sql = sprintf("update releases set categoryID = %d where categoryID = %d", Category::CAT_MISC, Category::CAT_NOT_DETERMINED);	
			$result = $db->query($sql);
		}
	}
	
	/**
	 * Process all TV related releases which will assign their series/episode/rage data
	 */
	public function processTv()
	{
		if ($this->site->lookupanidb == 1) 
		{
			$anidb = new AniDB($this->echooutput);
			$anidb->animetitlesUpdate();
			$anidb->processAnimeReleases();
		}
	
		if ($this->site->lookuptvrage == 1) 
		{
			$tvrage = new TVRage($this->echooutput);
			$tvrage->processTvReleases(($this->site->lookuptvrage==1));
		}
		
		if ($this->site->lookupthetvdb == 1) 
		{
			$thetvdb = new TheTVDB($this->echooutput);
			$thetvdb->processReleases();
		}
	}
	
	/**
	 * Process releases without a proper name and try to look it up in the nfo
	 */
	public function processOtherMiscCategory($numToProcess = 10)
	{
		$db = new DB();
		
		$res = $db->query(sprintf("select r.searchname, r.ID, r.guid, g.name as groupname from releases r inner join releasenfo rn on rn.releaseID = r.ID left join groups g on g.ID = r.groupID where (r.categoryID = %d or r.categoryID = %d) order by r.ID desc limit %d", Category::CAT_MISC, Category::CAT_NOT_DETERMINED, $numToProcess));
		
		if ($res)
		{
			$rescount = sizeof($res);
			
			if ($this->echooutput)
				echo "PostPrc : Attempting to categorise ".$rescount." Other-Misc releases\n";
				
			foreach($res as $rel)
			{
				$filenameRes = $db->query(sprintf("select dirname from predb where filename = %s limit 2", $db->escapeString($rel['searchname'])));
				if (count($filenameRes) == 1)
					$foundName = $filenameRes[0]['dirname'];
				else
				{
					$nfoRes = $db->queryOneRow(sprintf("select uncompress(nfo) as nfo from releasenfo where releaseID = %d", $rel['ID']));
					$nfo = $nfoRes['nfo'];

					$foundName = '';
					//Typical scene regex
					if (preg_match('/(?P<source>Source\s*?:|fix fornuke)?(?:\s|\]|\[)?(?P<name>[a-z0-9\']+(?:\.|_)[a-z0-9\.\-_\'&]+\-[a-z0-9&]+)(?:\s|\[|\])/i', $nfo, $matches))
					{	
						if (empty($matches['source']))
							$foundName = $matches['name'];
					}
					//IMAGiNE releases
					elseif(preg_match('/\*\s+([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- ]+ \- imagine)\s+\*/i', $nfo, $matches))
					{
						$foundName = $matches[1];
					}
					//SANTi releases
					elseif(preg_match('/\b([a-z0-9]+(?:\.|_| )[a-z0-9\.\_\- \']+\-santi)\b/i', $nfo, $matches))
					{
						$foundName = $matches[1];
					}
				}

				if ($foundName != '')
				{
					$category = new Category();
					$categoryID = $category->determineCategory($rel['groupname'], $foundName);
					$name = str_replace(' ', '_', $foundName);
					$searchname = str_replace('_', ' ', $foundName);
					
					$db->query(sprintf("UPDATE releases SET name = %s, searchname = %s, categoryID = %d WHERE ID = %d", $db->escapeString($name), $db->escapeString($searchname), $categoryID, $rel['ID']));
				} 
			}
		}
	}	
	
	/**
	 * Check for passworded releases, RAR contents and Sample/Media info
	 */
	public function processAdditional4()
	{
		require_once(WWW_DIR."/lib/nntp.php");
	
		$maxattemptstocheckpassworded = 5;
		$numtoProcess = 100;
		$processVideoSample = ($this->site->ffmpegpath != '') ? true : false;
		$processMediainfo = ($this->site->mediainfopath != '') ? true : false;
		$processPasswords = ($this->site->unrarpath != '') ? true : false;
		$processAudioSample = ($this->site->saveaudiopreview == 1) ? true : false;
		
		$tmpPath = $this->site->tmpunrarpath;
		$tmpPath .= '4';
		if (substr($tmpPath, -strlen( '/' ) ) != '/')
		{
			$tmpPath = $tmpPath.'/';								
		}
		
		if (!file_exists($tmpPath))
			mkdir($tmpPath, 0766, true);		
		
		$db = new DB;
		$nntp = new Nntp;
		$nzb = new Nzb;
		
		//
		// Get out all releases which have not been checked more than max attempts for password.
		//
		$result = $db->query(sprintf("select r.ID, r.guid, r.name, c.disablepreview from releases r 
			left join category c on c.ID = r.categoryID
			where (r.passwordstatus between %d and -1)
			or (r.haspreview = -1 and c.disablepreview = 0) order by r.guid desc  limit %d, %d ", ($maxattemptstocheckpassworded + 1) * -1, 7 * $numtoProcess, $numtoProcess ));
		
		$iteration = $rescount = sizeof($result);
		if ($rescount > 0)
		{
			echo "Post processing by guid on ".$rescount." releases ...";		
			$nntpconnected = false;
			
			foreach ($result as $rel)
			{
				echo $iteration--.".";
			
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
				$nzbfile = $nzb->getNZBPath($rel['guid'], $this->site->nzbpath);
				if (!$nzbInfo->loadFromFile($nzbfile))
				{
					continue;
				}
				$norar = 0;

				foreach($nzbInfo->nzb as $nzbsubject)
				{
					if (preg_match("/\w\.r00/i", $nzbsubject['subject']))
						$norar= 1;
				}
								
				// attempt to process video sample file
				if(!empty($nzbInfo->samplefiles) && $processVideoSample && $blnTookSample === false)
				{
					$sampleFile = $nzbInfo->samplefiles[0]; //first detected sample
					$sampleMsgids = array_slice($sampleFile['segments'], 0, 1); //get first segment, increase to get more of the sample
					$sampleGroup = $sampleFile['groups'][0];

					//echo "PostPrc : Fetching ".implode($sampleMsgids, ', ')." from {$sampleGroup}\n";
					if (!$nntpconnected)
					{
						$nntp->doConnect();
						$nntpconnected = true;
					}

					$sampleBinary = $nntp->getMessages($sampleGroup, $sampleMsgids);
					if ($sampleBinary === false)
						echo "\nPostPrc : Couldnt fetch sample\n";
					else
					{						
						$samplefile = $tmpPath.'sample.avi';
						
						file_put_contents($samplefile, $sampleBinary);
						
						$blnTookSample = $this->getSample($tmpPath, $this->site->ffmpegpath, $rel['guid']);
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
					{
						$nntp->doConnect();
						$nntpconnected = true;
					}					
					$mediaBinary = $nntp->getMessages($mediaGroup, $mediaMsgids);
					if ($mediaBinary === false) 
						echo "\nPostPrc : Couldnt fetch media file\n";
					else
					{						
						$mediafile = $tmpPath.'sample.avi';
						
						file_put_contents($mediafile, $mediaBinary);
						
						if ($processVideoSample && $blnTookSample === false)
						{
							$blnTookSample = $this->getSample($tmpPath, $this->site->ffmpegpath, $rel['guid']);
							if ($blnTookSample)
								$this->updateReleaseHasPreview($rel['guid']);
						}
						
						if ($processMediainfo)
							$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->site->mediainfopath, $rel['ID']);
						
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
					{
						$nntp->doConnect();
						$nntpconnected = true;
					}					
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
							$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->site->mediainfopath, $rel['ID']);
						
						if ($this->site->lamepath != "")
							$this->lameAudioSample($this->site->lamepath, $rel['guid']);
						
						unlink($audiofile);
					}
					unset($audioBinary);
				}
				
				if (!empty($nzbInfo->rarfiles) && ($this->site->checkpasswordedrar > 0 || (($processVideoSample || $processAudioSample) && $blnTookSample === false) || $processMediainfo))
				{
					$mysqlkeepalive = 0;
					foreach($nzbInfo->rarfiles as $rarFile)
					{
						//dont process any more rars if a passworded rar has been detected and the site is set to automatically delete them
						if ($this->site->deletepasswordedrelease == 1 && max($passStatus) == Releases::PASSWD_RAR)
						{
							echo "-Skipping processing of rar {$rarFile['subject']} as this release has already been marked as passworded.\n";
							continue;
						}
						
						$rarMsgids = array_slice($rarFile['segments'], 0, 1); //get first segment
						$rarGroup = $rarFile['groups'][0];
						
						//echo "PostPrc : Fetching ".implode($rarMsgids, ', ')." from {$rarGroup} (".++$mysqlkeepalive.")\n";
						if (!$nntpconnected)
						{
							$nntp->doConnect();
							$nntpconnected = true;
						}						
						$fetchedBinary = $nntp->getMessages($rarGroup, $rarMsgids);	
						if ($fetchedBinary === false) 
						{			
							echo "\nPostPrc : Failed fetching rar file\n";
							$db->query(sprintf("update releases set passwordstatus = passwordstatus - 1 where ID = %d", $rel['ID']));
							continue;
						}
						else
						{
							$relFiles = $this->processReleaseFiles($fetchedBinary, $rel['ID']);
							
							if ($this->site->checkpasswordedrar > 0 && $processPasswords)
							{
								$passStatus[] = $this->processReleasePasswords($fetchedBinary, $tmpPath, $this->site->unrarpath, $this->site->checkpasswordedrar);
							}
							
							// we need to unrar the fetched binary if checkpasswordedrar wasnt 2
							if ($this->site->checkpasswordedrar < 2 && $processPasswords)
							{
								$rarfile = $tmpPath.'rarfile.rar';
								file_put_contents($rarfile, $fetchedBinary);
								$execstring = '"'.$this->site->unrarpath.'" e -ai -ep -c- -id -r -kb -p- -y -inul "'.$rarfile.'" "'.$tmpPath.'"';
								$output = runCmd($execstring, false, true);
								unlink($rarfile);
							}
							
							if ($processVideoSample && $blnTookSample === false)
							{
								$blnTookSample = $this->getSample($tmpPath, $this->site->ffmpegpath, $rel['guid']);
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
								$blnTookMediainfo = $this->getMediainfo($tmpPath, $this->site->mediainfopath, $rel['ID']);
							}

							//
							// Has to be done after mediainfo
							//
							if ($blnTookAudioSample && $this->site->lamepath != "")
								$this->lameAudioSample($this->site->lamepath, $rel['guid']);
							
							if ($mysqlkeepalive % 25 == 0)
								$db->query("select 1");
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
				
				$sql = sprintf("update releases set passwordstatus = %d %s where ID = %d", max($passStatus), $hpsql, $rel["ID"]);
				$db->query($sql);
				
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
		$retval = array();
		$rar = new RarInfo;
		$rf = new ReleaseFiles;
		$db = new DB;
			
		if ($rar->setData($fetchedBinary))
		{
			$files = $rar->getFileList();		
			foreach ($files as $file) 
			{
				$rf->add($relid, $file['name'], $file['size'], $file['date'], $file['pass'] );
				$retval[] = $file['name'];
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
		$potentiallypasswordedfileregex = "/\.(ace|cab|tar|gz)$/i";
		$definetlypasswordedfileregex = "/password/i";
		$rar = new RarInfo;
		$filecount = 0;

		$rarfile = $tmpPath.'rarfile.rar';
		file_put_contents($rarfile, $fetchedBinary);
		
		if ($rar->open($rarfile))
		{
			if ($rar->isEncrypted)
			{
				$passStatus = Releases::PASSWD_RAR;
			}
			else
			{
				$files = $rar->getFileList(true);
				foreach ($files as $file) 
				{
					$filecount++;
					//
					// individual file rar passworded
					//
					if ($file['pass'] == 1 || preg_match($definetlypasswordedfileregex, $file["name"]))
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
					$output = runCmd($execstring, false, true);

					// delete the rar
					unlink($rarfile);
					
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
							$output2 = runCmd($execstring, false, true);
							unlink($tmpPath.$israr[$i]);
						}
						else
						{
							if ($tmp == 1 || $tmp == 2)
							{
								$passStatus = Releases::PASSWD_RAR;
								unlink($tmpPath.$israr[$i]);
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
		unset($filelist);
		$rar = new RarInfo;
		if ($rar->open($rarfile))
		{
			if ($rar->isEncrypted)
			{
				return 1;
			}
			else
			{
				$files = $rar->getFileList(true);			
				foreach ($files as $file) 
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
					$xmlarray = runCmd($execstring);
					
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
					$execstring = '"'.$ffmpeginfo.'" -q:v 0 -i "'.$samplefile.'" -vframes 300 "'.$ramdrive.'zzzz%03d.jpg"';
					$output = runCmd($execstring, false, true);		
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
		$minacceptableencodefilesize = 10000;
		$samplefile = $this->mp3SavePath.$releaseguid.'.mp3';

		if (file_exists($samplefile))
		{
			$outfile = $this->mp3SavePath.$releaseguid.'_l.mp3';
			
			//
			// lame the sample down to 96kb and replace it. alternatives could be 
			// V8 for low quality variable.
			//
			$execstring = '"'.$lameinfo.'" -b 96 "'.$samplefile.'" "'.$outfile.'"';
			$output = runCmd($execstring, false, true);		
			
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
					
					return true;
				}
			}
		}
		
		return false;
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
		$processMediainfo = ($this->site->mediainfopath != '') ? true : false;
		$processAudioSample = ($this->site->saveaudiopreview == 1) ? true : false;
		$processMusic = ($this->site->lookupmusic == 1) ? true : false;
		
		if ($processMusic && $processMediainfo && $processAudioSample) 
		{
			$music = new Music($this->echooutput);
			$ret = $music->processMusicReleaseFromMediaInfo();
			return $ret;
		}
		
		return false;
	}	
}

