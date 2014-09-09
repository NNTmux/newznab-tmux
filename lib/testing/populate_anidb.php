<?php
/* This script is designed to gather all show data from anidb and add it to the anidb table for newznab, as part of this process we need the number of PI queries that can be executed max and whether or not we want debuging the first argument if unset will try to do the entire list (a good way to get banned), the second option can be blank or true for debugging.
* IF you are using this script then then you also want to edit anidb.php in www/lib and locate "604800" and replace it with 1204400, this will make sure it never tries to connect to anidb as this will fail
*/

require_once(dirname(__FILE__) . "/../../bin/config.php");
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/releaseimage.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");

class AniDBstandAlone {

	const CLIENTVER = 1;
	function __construct($echooutput=false) {
		$this->pdo = new DB();
		$t = new Tmux();
		$this->tmux = $t->get();
		$s = new Sites();
		$this->site = $s->get();
		$maxanidbprocessed = $this->tmux->maxanidbprocessed;
		$this->aniqty = (!empty($maxanidbprocessed)) ? $maxanidbprocessed : 100;
		$this->echooutput = $echooutput;
		$this->imgSavePath = WWW_DIR . 'covers/anime' . '/';
		$this->APIKEY = $this->site->anidbkey;
		$this->c = new ColorCLI();
		}

	// ===== function getanimetitlesUpdate =================================================================
	public function animetitlesUpdate() {


		$lastUpdate = $this->pdo->queryOneRow('SELECT max(unixtime) as utime FROM animetitles');
		if (isset($lastUpdate['utime']) && (time() - $lastUpdate['utime']) < 604800) {
			if ($this->echooutput) {
				echo "\n";
				echo $this->c->info("Last update occurred less than 7 days ago, skipping full dat file update.\n\n");
				}
			return;
			}

		if ($this->echooutput) {
			echo $this->c->header("Updating animetitles by grabbing full dat AniDB dump.\n\n");
			}
		$zh = gzopen('http://anidb.net/api/anime-titles.dat.gz', 'r');
		preg_match_all('/(\d+)\|\d\|.+\|(.+)/', gzread($zh, '10000000'), $animetitles);

		if (!$animetitles) {
			return false;
			}

		$this->pdo->queryExec('DELETE FROM animetitles WHERE anidbID IS NOT NULL');
		if ($this->echooutput) {
			echo $this->c->header("Total of ".count($animetitles[1])." titles to add\n\n");
			}
		for ($loop = 0; $loop < count($animetitles[1]); $loop++) {
			$this->pdo->queryInsert(sprintf('INSERT IGNORE INTO animetitles (anidbID, title, unixtime) VALUES (%d, %s, %d)',
			$animetitles[1][$loop], $this->pdo->escapeString(html_entity_decode($animetitles[2][$loop], ENT_QUOTES, 'UTF-8')), time()
			));
			}
		if ($loop % 2500 == 0 && $this->echooutput) {
			echo $this->c->header("Completed Processing " . $loop . " titles.\n\n");
			}
		gzclose($zh);
		if ($this->echooutput) {
			echo $this->c->header("Completed animetitles update.\n\n");
			}
		}

	// ===== new getAniDBInfo ==============================================================================
	public function getAniDBInfo($exitcount) {

		// Declare and set main variables

		$ri = new ReleaseImage();
		$apicount = 0;

		$this->c->doEcho($this->c->header("Start getAniDBInfo at " . date('D M d, Y G:i a')));

		$notinani = sprintf("SELECT animetitles.anidbID FROM animetitles
			INNER JOIN anidb ON animetitles.anidbID = anidb.anidbID"
			);

		// Used for information purposes in main echo
		$animetitles = $this->pdo->query('SELECT DISTINCT anidbID FROM animetitles');
		$anidbtitles = $this->pdo->query('SELECT DISTINCT anidbID FROM anidb');
		$anidbjointitles = $this->pdo->query(sprintf("SELECT * FROM animetitles
					INNER JOIN anidb ON animetitles.anidbID = anidb.anidbID"
					));
		$anidbmissingtitles = $this->pdo->query(sprintf("SELECT * FROM animetitles
					WHERE anidbID NOT IN (%s)", $notinani
					));

		// Stage declarations
		$aniremovedstage0 = $this->pdo->query(sprintf("SELECT anidbID FROM anidb WHERE anidbID NOT IN (%s)", $notinani));
		$animissstage1 = $this->pdo->query(sprintf("SELECT DISTINCT anidbID FROM animetitles WHERE anidbID NOT IN (%s)", $notinani));
		$anirunnstage2 = $this->pdo->query('SELECT anidbID FROM anidb WHERE (startdate < CURDATE() AND (enddate > CURDATE() OR enddate IS NULL)) AND (unixtime < UNIX_TIMESTAMP(NOW()- INTERVAL 7 DAY)) ORDER BY unixtime');
		$anioldstage3 = $this->pdo->query('SELECT anidbID FROM anidb WHERE (unixtime < UNIX_TIMESTAMP(NOW()- INTERVAL 90 DAY)) ORDER BY unixtime');
		echo  $this->c->header("Total of " . count($animetitles) . " distinct titles present in animetitles.\n" .
					  "Total of " . count($anidbtitles) . " distinct titles present in anidb.\n" .
					  "Total of " . count($anidbjointitles) . " titles in both anidb and animetitles.\n" .
					  "Total of " . count($anidbmissingtitles) . " missing titles in anidb table.\n" .
					  "Total of " . count($animissstage1) . " missing distinct titles in anidb table.\n" .
					  "Total of " . count($aniremovedstage0) . " orphaned anime titles no longer in animetitles to be removed from anidb table.\n" .
					  "Total of " . count($anirunnstage2) . " running anime titles in anidb table not updated for 7 days.\n" .
					  "Total of " . count($anioldstage3) . " anime titles in anidb table not updated for 90 days.\n");

		if ($this->APIKEY == '') {
			echo $this->c->error("Error: You need an API key from AniDB.net to use this.  Try adding \"newznab\" in Site Edit.\n");
			return;
		}

		// Show the data for 10 sec before starting
		echo $this->c->info("Starting in 10 sec...\n");
		sleep(10);
		// end debug

		// now do this list:
		// 0) remove removed anidbID's from anidb nnot in animetitles, as these can't be updated
		// 1) insert missing titles until exitcount reached
		// 2) update running shows until exitcount reached
		// 3) update show data older than xxx day's until exitcount reached
		// todo: what to do with anidb.anidbID no longer available in animetitles.anidbID?? ( I have 6 so far)

		// running series:
		// anidb.startdate NULL AND enddate NULL =>> ignore?? (why?) Can only be updated in stage 3!!!!
		// anidb.startdate > CURDATE(); // start date in the future thus it is not in progress as it has not started yet ==> ignore
		// anidb.startdate < CURDATE() AND (enddate IS NULL OR enddate > CURDATE()) => running show without enddate or date in future

		// Begin Stage 0: Remove Orphaned AniDB entries from anidb table if no longer in animetitles table

		$this->c->doEcho($this->c->header("[".date('d-m-Y G:i')."] Stage 0 -> Remove deleted anidbID."));

		foreach ($aniremovedstage0 as $value) {
			$anidbID = (int)$value['anidbID'];
			if ($this->echooutput) {
				// Remove AniDB ID from anidb
				echo 'Removing AniDB ID '.$anidbID."\n";
				}
			$this->deleteTitle($anidbID);
			$image_file = $this->imgSavePath . $anidbID;

			// Remove AniDB image if exists
			//if (!file_exists($image_file) {
				//}
			}

		// Begin Stage 1: Insert Missing AniDB entries into AniDB table from animetitles table

		$this->c->doEcho($this->c->header("[".date('d-m-Y G:i')."] Stage 1 -> Insert missing anidbID into anidb table."));

		foreach ($animissstage1 as $value) {
			$anidbID = (int)$value['anidbID'];
			if ($this->echooutput) {
				echo 'Adding AniDB ID ' . $anidbID . "\n";
			}

			// Pull information from AniDB for this ID and increment API counter -- if false (banned) exit
			$AniDBAPIArray = $this->AniDBAPI($anidbID);
			$apicount++;
			if ($AniDBAPIArray['banned']){
				if ($this->echooutput) {
					echo "AniDB Banned, import will fail, please wait 24 hours before retrying\n";
				}
				return;
			}
			$this->addTitle($AniDBAPIArray);

			// Save the image to covers directory
			if ($AniDBAPIArray['picture']) {
				$ri->saveImage($AniDBAPIArray['anidbID'], 'http://img7.anidb.net/pics/anime/'.$AniDBAPIArray['picture'], $this->imgSavePath);
			}
			// Print total count added
			if ($apicount != 0 && $this->echooutput) {
				echo $this->c->header("Processed " . $apicount . " anidb entries of a total possible of " . $exitcount . " for this session\n");
			}
			// Sleep 4 Minutes for Every 10 Records
			if ($apicount % 10 == 0 && $apicount != 0) {
				$sleeptime = 180 + rand(30,90);
				if ($this->echooutput) {
					$this->c->doEcho($this->c->primary("[".date('d-m-Y G:i')."] Start waitloop for " . $sleeptime . " seconds to prevent banning.\n"));
					}
				sleep($sleeptime);
				}
			}

		// using exitcount if this number of API calls is reached exit
		if ($apicount >= $exitcount) {
			return;
			}

		// Begin Stage 2: Update running series in anidb table -- we only update series already existing in db
		$this->c->doEcho($this->c->header("[".date('d-m-Y G:i')."] Stage 2 -> Update running series."));

		foreach ($anirunnstage2 as $value) {
			$anidbID = (int)$value['anidbID'];

			if ($this->echooutput) {
				echo 'Updating AniDB ID '.$anidbID."\n";
				}
			// actually get the information on this anime from anidb
			$AniDBAPIArrayNew = $this->AniDBAPI($anidbID);

			// if it is false we can simply exit
			if ($AniDBAPIArrayNew['banned']) {
				if ($this->echooutput) {
					echo $this->c->error("AniDB Banned, import will fail, please wait 24 hours before retrying.\n");
					}
				return;
				}
			// increment apicount on API access
			$apicount++;
			// update the stored information with updated data
			$this->updateTitle($AniDBAPIArrayNew['anidbID'], $AniDBAPIArrayNew['title'], $AniDBAPIArrayNew['type'],
			$AniDBAPIArrayNew['startdate'], $AniDBAPIArrayNew['enddate'], $AniDBAPIArrayNew['related'],
			$AniDBAPIArrayNew['creators'], $AniDBAPIArrayNew['description'], $AniDBAPIArrayNew['rating'],
			$AniDBAPIArrayNew['categories'], $AniDBAPIArrayNew['characters'], $AniDBAPIArrayNew['epnos'],
			$AniDBAPIArrayNew['airdates'], $AniDBAPIArrayNew['episodetitles']);

			$image_file = $this->imgSavePath . $anidbID;

			// if the image is present we do not need to replace it
			if (!file_exists($image_file)) {
				if ($AniDBAPIArrayNew['picture']) {
					// save the image to the covers page
					$ri->saveImage($AniDBAPIArrayNew['anidbID'],
					'http://img7.anidb.net/pics/anime/'.$AniDBAPIArrayNew['picture'], $this->imgSavePath);
					}
				}

			// update how many we have done of the total to do in this session
			if ($apicount != 0 && $this->echooutput) {
				echo 'Processed ' . $apicount . " anidb entries of a total possible of " . $exitcount . " for this session.\n";
				}
			// every 10 records sleep for 4 minutes before continuing
			if ($apicount % 10 == 0 && $apicount != 0) {
				$sleeptime=180 + rand(30, 90);
				}
			if ($this->echooutput) {
				$this->c->doEcho($this->c->primary("[".date('d-m-Y G:i')."] Start waitloop for " . $sleeptime . " sec to prevent banning."));
				}
			sleep($sleeptime);

			// using exitcount if this number of API calls is reached exit
			if ($apicount >= $exitcount) {
				return;
				}
			}

		// now for stage 3: update rest of records not updated for a loooooong time
		// same as step2: but other for loop (so we need to make a proper function out of this?!)
		$this->c->doEcho($this->c->header("[".date('d-m-Y G:i')."] Stage 3 -> Update 90+ day old series."));

		foreach($anioldstage3 as $value) {
			$anidbID = (int)$value['anidbID'];
			if ($this->echooutput) {
				echo 'Updating AniDB ID '.$anidbID."\n";
				}
			// actually get the information on this anime from anidb
			$AniDBAPIArrayNew = $this->AniDBAPI($anidbID);
			if ($AniDBAPIArrayNew['banned']) {
				if ($this->echooutput) {
					echo "AniDB Banned, import will fail, please wait 24 hours before retrying\n";
					}
				return;
				}
			// increment apicount on API access
			$apicount++;

			// update the stored information with updated data
			$this->updateTitle($AniDBAPIArrayNew['anidbID'], $AniDBAPIArrayNew['title'], $AniDBAPIArrayNew['type'],
			$AniDBAPIArrayNew['startdate'], $AniDBAPIArrayNew['enddate'], $AniDBAPIArrayNew['related'],
			$AniDBAPIArrayNew['creators'], $AniDBAPIArrayNew['description'], $AniDBAPIArrayNew['rating'],
			$AniDBAPIArrayNew['categories'], $AniDBAPIArrayNew['characters'], $AniDBAPIArrayNew['epnos'],
			$AniDBAPIArrayNew['airdates'], $AniDBAPIArrayNew['episodetitles']);

			$image_file = $this->imgSavePath . $anidbID;

			// if the image is present we do not need to replace it
			if (!file_exists($image_file)) {
				if ($AniDBAPIArrayNew['picture']) {
					// save the image to the covers page
					$ri->saveImage($AniDBAPIArrayNew['anidbID'],
					'http://img7.anidb.net/pics/anime/'.$AniDBAPIArrayNew['picture'], $this->imgSavePath);
					}
				}

			// update how many we have done of the total to do in this session
			if ($apicount != 0 && $this->echooutput) {
				echo 'Processed '.$apicount." anidb entries of a total possible of " . $exitcount . " for this session\n";
				}
			// every 10 records sleep for 4 minutes before continuing
			if ($apicount % 10 == 0 && $apicount != 0) {
				$sleeptime = 180 + rand(30, 90);
				if ($this->echooutput) {
					$this->c->doEcho($this->c->primary("[".date('d-m-Y G:i')."] Start waitloop for " . $sleeptime . " sec to prevent banning"));
					}
				sleep($sleeptime);
				}

			// using exitcount if this number of API calls is reached exit
			if ($apicount >= $exitcount) {
				return;
				}

			}

	} // end public function getAniDBInfo($exitcount)

	public function addTitle($AniDBAPIArray) {

		$this->pdo->queryInsert(sprintf("INSERT INTO anidb VALUES (%d, 0, 0, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)",
			$AniDBAPIArray['anidbID'], $this->pdo->escapeString($AniDBAPIArray['title']), $this->pdo->escapeString($AniDBAPIArray['type']),
			(empty($AniDBAPIArray['startdate']) ? 'null' : $this->pdo->escapeString($AniDBAPIArray['startdate'])),
			(empty($AniDBAPIArray['enddate']) ? 'null' : $this->pdo->escapeString($AniDBAPIArray['enddate'])),
			$this->pdo->escapeString($AniDBAPIArray['related']), $this->pdo->escapeString($AniDBAPIArray['creators']),
			$this->pdo->escapeString($AniDBAPIArray['description']), $this->pdo->escapeString($AniDBAPIArray['rating']),
			$this->pdo->escapeString($AniDBAPIArray['picture']), $this->pdo->escapeString($AniDBAPIArray['categories']),
			$this->pdo->escapeString($AniDBAPIArray['characters']), $this->pdo->escapeString($AniDBAPIArray['epnos']),
			$this->pdo->escapeString($AniDBAPIArray['airdates']), $this->pdo->escapeString($AniDBAPIArray['episodetitles']), time()
			));
		}

	public function updateTitle($anidbID, $title, $type, $startdate, $enddate, $related, $creators, $description, $rating, $categories, $characters, $epnos, $airdates, $episodetitles) {

              $this->pdo->queryExec(sprintf('UPDATE anidb SET title = %s, type = %s, startdate = %s, enddate = %s, related = %s, creators = %s, description = %s,
					rating = %s, categories = %s, characters = %s, epnos = %s, airdates = %s, episodetitles = %s, unixtime = %d
					WHERE anidbID = %d',
					$this->pdo->escapeString($title), $this->pdo->escapeString($type), (empty($AniDBAPIArray['startdate']) ? 'null' : $this->pdo->escapeString($AniDBAPIArray['startdate'])),
					(empty($AniDBAPIArray['enddate']) ? 'null' : $this->pdo->escapeString($AniDBAPIArray['enddate'])), $this->pdo->escapeString($related), $this->pdo->escapeString($creators),
					$this->pdo->escapeString($description), $this->pdo->escapeString($rating), $this->pdo->escapeString($categories), $this->pdo->escapeString($characters), $this->pdo->escapeString($epnos),
					$this->pdo->escapeString($airdates), $this->pdo->escapeString($episodetitles), time(), $anidbID
					));
		}

	public function deleteTitle($anidbID) {

		$this->pdo->queryExec(sprintf('DELETE FROM anidb WHERE anidbID = %d', $anidbID));
		}

	public function getAnimeInfo($anidbID) {

		$animeInfo = $this->pdo->query(sprintf('SELECT * FROM anidb WHERE anidbID = %d', $anidbID
					));
		return isset($animeInfo[0]) ? $animeInfo[0] : false;
		}


	public function AniDBAPI($anidbID) {
		$ch = curl_init('http://api.anidb.net:9001/httpapi?request=anime&client='.$this->APIKEY.'&clientver='.self::CLIENTVER.'&protover=1&aid='.$anidbID);
		if ($this->echooutput) {
			echo 'http://api.anidb.net:9001/httpapi?request=anime&client='.$this->APIKEY.'&clientver='.self::CLIENTVER.'&protover=1&aid='.$anidbID."\n";
			}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		$apiresponse = curl_exec($ch);

		if ($this->echooutput) {
			echo "Response: '".$apiresponse."'\n";
			}
		if (!$apiresponse) {
			return false;
			}
		curl_close($ch);

		//TODO: SimpleXML - maybe not.

		$AniDBAPIArray['anidbID'] = $anidbID;

		// if we are banned simply return false
		if (preg_match("/\<error\>Banned\<\/error\>/", $apiresponse)) {
			$AniDBAPIArray['banned'] = true;
			return $AniDBAPIArray;
		} else {
			$AniDBAPIArray['banned'] = false;
			preg_match_all('/<title xml:lang="x-jat" type="(?:official|main)">(.+)<\/title>/i', $apiresponse, $title);
			$AniDBAPIArray['title'] = isset($title[1][0]) ? $title[1][0] : '';
			preg_match_all('/<(type|(?:start|end)date)>(.+)<\/\1>/i', $apiresponse, $type_startenddate);
			$AniDBAPIArray['type'] = isset($type_startenddate[2][0]) ? $type_startenddate[2][0] : '';
			// new checks for correct start and enddate
			// Warning: missing date info is added to default January 1st, 2008 (2008 -> 2008-01-01)
			if (isset($type_startenddate[2][1])) {
				if (($timestamp = strtotime($type_startenddate[2][1])) === false) {
					// Timestamp is bad -- set ''
					$AniDBAPIArray['startdate']= '';
					}
				// Startdate valid for php, convert in case only year or month is given to sql date
				$AniDBAPIArray['startdate'] = date('Y-m-d', strtotime($type_startenddate[2][1]));
			} else {
				$AniDBAPIArray['startdate'] = '';
			}
			if (isset($type_startenddate[2][2])) {
				if (($timestamp = strtotime($type_startenddate[2][2])) === false) {
					// Timestamp not good->make it null";
					$AniDBAPIArray['enddate']= '';
					}
				// Startdate valid for php, convert in case only year or month is given to sql date
				$AniDBAPIArray['enddate'] = date('Y-m-d', strtotime($type_startenddate[2][2]));
			} else {
				// echo "Null date ".$type_startenddate[2][2]."\n";
				$AniDBAPIArray['enddate'] = '';
			}

			preg_match_all('/<anime id="\d+" type=".+">([^<]+)<\/anime>/is', $apiresponse, $related);
			$AniDBAPIArray['related'] = isset($related[1]) ? implode($related[1], '|') : '';
			preg_match_all('/<name id="\d+" type=".+">([^<]+)<\/name>/is', $apiresponse, $creators);
			$AniDBAPIArray['creators'] = isset($creators[1]) ? implode($creators[1], '|') : '';
			preg_match('/<description>([^<]+)<\/description>/is', $apiresponse, $description);
			$AniDBAPIArray['description'] = isset($description[1]) ? $description[1] : '';
			preg_match('/<permanent count="\d+">(.+)<\/permanent>/i', $apiresponse, $rating);
			$AniDBAPIArray['rating'] = isset($rating[1]) ? $rating[1] : '';
			preg_match('/<picture>(.+)<\/picture>/i', $apiresponse, $picture);
			$AniDBAPIArray['picture'] = isset($picture[1]) ? $picture[1] : '';
			preg_match_all('/<category id="\d+" parentid="\d+" hentai="(?:true|false)" weight="\d+">\s+<name>([^<]+)<\/name>/is', $apiresponse, $categories);
			$AniDBAPIArray['categories'] = isset($categories[1]) ? implode($categories[1], '|') : '';
			preg_match_all('/<character id="\d+" type=".+" update="\d{4}-\d{2}-\d{2}">\s+<name>([^<]+)<\/name>/is', $apiresponse, $characters);
			$AniDBAPIArray['characters'] = isset($characters[1]) ? implode($characters[1], '|') : '';
			// if there are no episodes defined this can throw an error we should catch and handle this, but currently we do not
			preg_match('/<episodes>\s+<episode.+<\/episodes>/is', $apiresponse, $episodes);
			preg_match_all('/<epno>(.+)<\/epno>/i', $episodes[0], $epnos);
			$AniDBAPIArray['epnos'] = isset($epnos[1]) ? implode($epnos[1], '|') : '';
			preg_match_all('/<airdate>(.+)<\/airdate>/i', $episodes[0], $airdates);
			$AniDBAPIArray['airdates'] = isset($airdates[1]) ? implode($airdates[1], '|') : '';
			preg_match_all('/<title xml:lang="en">(.+)<\/title>/i', $episodes[0], $episodetitles);
			$AniDBAPIArray['episodetitles'] = isset($episodetitles[1]) ? implode($episodetitles[1], '|') : '';

			$sleeptime = 10 + rand(2, 10);

			if ($this->echooutput) {
				$this->c->doEcho($this->c->primary("[".date('d-m-Y G:i')."] Start waitloop for " . $sleeptime . " seconds to comply with flooding rule."));
				}
			sleep($sleeptime);
			return $AniDBAPIArray;
		}
	}
} // end class AniDBstandAlone

$c = new ColorCLI();

if (isset($argv[1]) && is_numeric($argv[1])) {
	// create a new AniDB object
	$anidb = new AniDBstandAlone(true);

	// next get the title list and populate the DB, update animetitles once a week
	$anidb->animetitlesUpdate();

	// sleep between 1 and 3 minutes before it starts, this way if from a cron process the start times are random
	if (isset($argv[2]) && $argv[2] == 'cron') {
		sleep(rand(60, 180));
		}
	// then get the titles, this is where we will make the real changes
	$anidb->getAniDBInfo((int)$argv[1] + rand(1, 12));
} else {
	echo $c->error("This script is designed to gather all show data from anidb and add it to the anidb table for newznab, as part of this process we need the number of API queries that can be executed max.\nTo execute this script run:\nphp populate_anidb.php 30\n");
}