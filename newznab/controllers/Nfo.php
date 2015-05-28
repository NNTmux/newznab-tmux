<?php

use newznab\db\Settings;

// Silent Error Handler (used to shut up noisy XML exceptions)
function nfoHandleError($errno, $errstr, $errfile, $errline, array $errcontext){
	if (0 === error_reporting())
		return false;
	if(!defined('E_STRICT'))define('E_STRICT', 2048);
	switch($errno){
		case E_WARNING:
		case E_NOTICE:
		case E_STRICT:
			return;
		default:
			break;
	};
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * This class looks up nfo files and handles storage/retrieval of them from the database.
 */
class Nfo
{
	/**
	 * @access public
	 * @var how many releases to process at once (hence 1 release
	 *      could have more then 10 possible nfo matches
	 */
	const NNTP_BATCH_COUNT = 10;

	/**
	 * @access public
	 * @var Number of retries to usenet on a failed batch fetching before
	 *      giving up a batch and moving on
	 */
	const USENET_RETRY_COUNT = 5;

	/**
	 * @access public
	 * @var Defines the maximum size a single segment can be before we can rule
	 *      after a binary has matched a releaseregex
	 */
	const NFO_MAX_FILESIZE = 50000;

	/**
	 * @access public
	 * @var Database flag for no NFO found
	 */
	const FLAG_NFO_MISSING = -1;

	/**
	 * @access public
	 * @var Database flag NFO pending scan
	 */
	const FLAG_NFO_PENDING = 0;

	/**
	 * Default constructor.
	 *
	 * @param bool $verbose
	 * @param bool $use_obfuscated
	 * @param bool $use_fuzzy
	 */
	public function __construct($verbose=false, $use_obfuscated=true, $use_fuzzy=false)
	{
		$this->use_fuzzy=$use_fuzzy;
		$this->use_obfuscated=$use_obfuscated;
		$this->verbose=$verbose;
	}

	private function nfo_scan(&$nzbInfo){
		//
		// Phase 1, iterate over nzb file for a relative
		//          match on a possible nfo file
		//              - 1 segment
		//              - within byte size
		//

		// Array of all possible matches to return
		$nfo_idx = array();

		// Search for all entries that have a single segment
		if (empty($nzbInfo->segmentfiles))
			// Nothing to Return
			return array();

		// Fetch Meta Information
		if (isset($nzbInfo->metadata['name']))
			$name = $nzbInfo->metadata['name'];
		else
			$name = "";

		$unordered_list=array();
		foreach($nzbInfo->segmentfiles as $segment){
			if ($segment['filesize'] > Nfo::NFO_MAX_FILESIZE)
				continue;

			$unordered_list[] = array(
				"name" => $name,
				"subject" => $segment['subject'],
				"bytes" => $segment['filesize'],
				"segment" => $segment['segments'],
				"groups" => $segment['groups']
			);
		}

		//
		// Filter built list above based on subject line info
		// hence... eliminate par2, nzb ... etc files
		//
		// processing is done in 2 steps, the first step finds the
		// most likely .nfo files, while the second keeps a backup
		// of potential others in the unlikelyhood the content
		// parsed here is bad
		//
		foreach($unordered_list as $idx => $n){
			if (preg_match("/\.(nfo)([^a-z0-9]+|$)/i", $n["subject"])){
				if($this->verbose) echo "[nfo] ";
				$nfo_idx[]=$n;
			}
		}

		// Handle Releases with Obfuscation
		// Releases titled: f4ca0f95896da1d41254bf49791a86a2
		if($this->use_obfuscated)
			foreach($unordered_list as $idx => $n){
				if (preg_match("/\.(sfv)([^a-z0-9]+|$)/i", $n["subject"]))
					continue;
				if (preg_match("/\.(nzb)([^a-z0-9]+|$)/i", $n["subject"]))
					continue;

				if (preg_match("/\.[0-9]+([^a-z0-9\.-]+|$)/i", $n["subject"])){
					if($this->verbose) echo "[obfs] ";
					$nfo_idx[]=$n;
				}
			}

		// Fuzzy Parsing sees if it can identify other possible nfo's however
		// they are appended to the end of the list obvious nfo's are always
		// processed first
		if($this->use_fuzzy)
			foreach($unordered_list as $idx => $n){
				if (preg_match("/\.(txt|diz)([^a-z0-9]+|$)/i", $n["subject"])){
					if($this->verbose) echo "[fuzz] ";
					$nfo_idx[]=$n;
				}
			}

		// Return array of matched content in order (aprox)
		// from very possible to... possible...
		return $nfo_idx;
	}

	private function is_binary(&$raw){
		// Returns true if data passed in is binary, otherwise
		// returns false,
		$has_binary = (
			0 || substr_count($raw, "^\r\n")/512 > 0.3
			|| substr_count($raw, "^ -~")/512 > 0.3
			|| substr_count($raw, "\x00") > 0
		);

		if($has_binary)
		{
			// Before we rule it completely out, see if we can detect it
			// as utf-16
			$result = iconv($in_charset = 'UTF-16LE' , $out_charset = 'UTF-8', $raw);
			if (false !== $result)
			{
				// not binary, we decoded it
				// we're dealing with a utf-16 type file...
				// store it as utf-8
				$raw = $result;
				return false;
			}
		}

		// Return the binary flag
		return ($has_binary)?true:false;
	}

	private function is_par2(&$raw){
		// Returns true if data passed in is binary, otherwise
		// returns false,
		return (substr($raw, 4) == "PAR2");
	}

	private function is_sfv(&$raw){
		// scan a content and return true if it is detected to be
		// an sfv file, otherwise return false

		// First we identify acceptable sfv lines, anything that
		// does not match against the below causes this function to
		// exit gracefully and report that were not dealing with
		// an sfv file
		$sfv_regex = array(
			// the sfv information itself
			'/^\s*([^; \t]+)\s+([^; \t]+)[ \t]*(;|$)/',
			// sfv comments
			'/^\s*;/',
			// empty lines that contain nothing
			'/^$/',
		);
		// itreate over each line of file, if all regex's match
		// on every line then we are dealing with an sfv file
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $raw) as $line){
			$matches=false;
			foreach($sfv_regex as $regex)
				if(preg_match($regex, $line)){
					$matches=true;
					break;
				}
			if(!$matches)
				return false;
		}
		return true;
	}

	private function store_blob($nfometa, $blobhash, $removed){
		// This takes a array of blobs with their index id being
		// the release id;  In the event we fetch the data and deem
		// it no good, we need to add it to the skipped array which
		// must be passed into the function.

		$db = new Settings();
		foreach($blobhash as $uid => $blob){
			$query = sprintf(
				"REPLACE INTO releasenfo (id, releaseid, binaryID, nfo) ".
				"VALUES (NULL, %d, 0, compress(%s));",
				$uid, $db->escapeString($blob));
			$id = $db->queryInsert($query);
			if(!$id){
				if($this->verbose) echo "!";
			}else{
				$query = sprintf("UPDATE releases SET releasenfoid = %d WHERE id = %d LIMIT 1",
								$id, $uid);
				$res = $db->queryExec($query);
				if($this->verbose) echo "s";
			}
		}

		// Now we update the database with entries that have no nfo files
		// associated with the release
		foreach($removed as $uid){
			$res = $this->setNfoMissing($uid);
			if($res <= 0){
				if($this->verbose) echo "!";
			}else{
				if($this->verbose) echo "s";
			}
		}
	}

	private function parse_blobs(&$nfometa, &$nfoblob){
		// Parses an array of array of blobs and determines the most
		// ideal nfo from them.
		//
		// $nfoblob is expected as follows
		//
		//	$nfoblob = array(
		//       [<releaseid>] = array(
		//           [0] = <potential nfo file>,
		//           [1] = <potential nfo file>,
		//           ...
		//       ),
		//       [<releaseid>] = array(
		//           [0] = <potential nfo file>,
		//       ),
		//       ...
		//  )
		//
		// Meanwhile, $nfometa is expected as follows:
		//	$nfometa = array(
		//       [<releaseid>] = array(
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       "alt.binaries.mygroupb",
		//                       "alt.binaries.mygroupc",
		//                       ...
		//                    )
		//           [segment] = array(<segment id>),
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       "alt.binaries.mygroupb",
		//                       ...
		//                    )
		//       ),
		//       [<releaseid>] = array(
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       ...
		//                    )
		//           [segment] = array(<segment id>),
		//       ),
		//       ...
		// The function strips indexes that appear invalid
		// and stores the most ideal match per release

		$parsed_blob = array();
		$parsed_meta = array();

		foreach($nfometa as $uid => $info){
			$ideal = Null;
			$tossed = 0;
			$total = count($info);
			foreach($info as $idx => $entry){
				// Save first 'valid' entry

				// Some simple checks right off the top... if there is
				// no blob or the data failed to fetch, we can rule
				// this entry out right away
				if(!array_key_exists($uid, $nfoblob)){
					if($this->verbose) echo '-';
					continue;
				}
				if(!array_key_exists($idx, $nfoblob[$uid])){
					if($this->verbose) echo '-';
					continue;
				}
				if($nfoblob[(string)$uid][$idx] === Null){
					if($this->verbose) echo '-';
					continue;
				}

				// Eliminate detected xml (usually nzb) files
				if(preg_match('/xmlns[^=]*="[^"]*"/i', $nfoblob[$uid][$idx]) ||
					preg_match("/(\<\?xml[\d\D]*\?\>)/i", $nfoblob[$uid][$idx])){
					if($this->verbose) echo '-';
					continue;
				}

				// We do not want to pick up sfv files
				if($this->is_sfv($nfoblob[$uid][$idx])){
					if($this->verbose) echo '-';
					continue;
				}

				// We do not want to pick up par2 files
				if($this->is_par2($nfoblob[$uid][$idx])){
					if($this->verbose) echo '-';
					continue;
				}
				// Ideally if code reaches this far
				// we can assume we've matched and we
				// san skip further parsing
				$ideal = $idx;
				break;
			}

			if($ideal !== Null){
				// An ideal match was found
				$parsed_blob[(string)$uid] = $nfoblob[$uid][$ideal];
				$parsed_meta[(string)$uid] = $nfometa[$uid][$ideal];
				if($this->verbose) echo '+';
			}else{
				// No valid data
				unset($parsed_blob[(string)$uid]);
				unset($parsed_meta[(string)$uid]);
			}
		}

		// perform swap with new parsed data by elminating the array containing
		// the possible matches with the absolute match itself...
		// no longer is anyone dealing with an array of array after calling
		// this function
		$nfoblob = $parsed_blob;
		$nfometa = $parsed_meta;

		return count($nfoblob);
	}

	private function _nfo_grab($nfometa, &$blobhash){
		// nfometa should be an array() of segments from nzb file
		// it will then populate the blobhash which uses the segments
		// as hash entries for the blob data.
		// nfometa is an array of arrays simiar to the following
		// structure:
		//
		// The list is structured in such a way that the most ideal
		// matches are at the front, while less likely ones at the
		// back of the array
		//
		//	$nfometa = array(
		//       [<releaseid>] = array(
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       "alt.binaries.mygroupb",
		//                       "alt.binaries.mygroupc",
		//                       ...
		//                    )
		//           [segment] = array(<segment id>),
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       "alt.binaries.mygroupb",
		//                       ...
		//                    )
		//       ),
		//       [<releaseid>] = array(
		//           [groups] = array(
		//                       "alt.binaries.mygroupa",
		//                       ...
		//                    )
		//           [segment] = array(<segment id>),
		//       ),
		//       ...
		//  )
		$nntp = new NNTP();

		// Connect to server (we throw an exception if we fail) which
		// is caught upstairs with the nfo_grab() function
		// no error handling is needed here
		$nntp->doConnect(1, true);
		foreach($nfometa as $uid => $matches){
			$blobhash[$uid] = array();
			foreach($matches as $idx => $match){
				$fetched = false;
				foreach($match["groups"] as $group){
					// Don not try other groups if we already got it
					if($fetched)break;

					// Select the group and then attempt to fetch the article
					$blob = $nntp->getMessages($group, $match["segment"], false);
					if ($blob === false){
						if($this->verbose) echo '*';
						continue;
					}
					// Mark that we fetched it to prevent fetching more
					// of the same thing
					$fetched = true;
					if($this->verbose) echo '.';

					// Update blob with decrypted version and store
					if ($this->is_binary($blob)){
						// Binary data is not acceptable, we only
						// work with text from here on out.
						continue;
					}
					// Read-able ascii at this point... store it
					$blobhash[$uid][$idx] = $blob;
				}
				if(!$fetched)
					// handle empty/failed segments
					$blobhash[$uid][$idx] = Null;
			}
		}
		$nntp->doQuit();
	}

	private function nfo_grab($nfometa, &$blobhash){
		// It is possible for connection to drop while attempting
		// to fetch nfo content, to accomodate for the exceptions
		// thrown during this time we wrap the real nfo_grab()
		// in a try catch block with a silent exception catcher
		$retries = nfo::USENET_RETRY_COUNT;
		set_error_handler('nfoHandleError');
		$_blobhash = array();
		while($retries >0){
			try{
				$this->_nfo_grab($nfometa, $_blobhash);
				break;
			}catch (Exception $e){
				// Connection lost
				if($this->verbose) echo sprintf("\n%s Connection lost to usenet (%d retries left).\n",
							'NfoProc', $retries);
				// Decrement retry count
				$retries--;
				// Reset blobhash
				$_blobhash = array();
				continue;
			}
		}
		// Restore handler as any future errors really are... code errors :)
		restore_error_handler();

		if($retries>0){
			foreach ($_blobhash as $k => $v)
				$blobhash[(string)$k]=$v;
			return true;
		}
		return false;
	}

	private function scan_releases(&$processed, &$total, $limit=Null){
		// Scan all nzb files whos releases match against data
		// that has no nfo files associated with it.
		//
		// nzb files are further parsed for nfo segments that can
		// be extracted and applied to the release
		$nzb = new NZB();
		$db = new Settings();

		// How many releases to handle at a time
		$batch=Nfo::NNTP_BATCH_COUNT;

		// Build NFO List
		$nfometa = array();

		// Missing NFO Query (oldest first so they don't expire on us)
		$mnfo = "SELECT id,guid, name FROM releases r ".
				"WHERE r.releasenfoid = ".Nfo::FLAG_NFO_PENDING.
				" ORDER BY postdate DESC";

		if ($limit !== Null && $limit > 0)
			$mnfo .= " LIMIT $limit";

		$res = $db->query($mnfo);
		if($res){
			foreach($res as $r){
				$nzbfile = $nzb->getNZBPath($r["guid"]);
				if(!is_file($nzbfile)){
					if($this->verbose) echo sprintf("%s Missing NZB File: %d/%s ...\n",
						'NfoProc', intval($r["id"]), $r["name"]);
					$this->setNfoMissing($r["id"]);
					continue;
				}

				$nzbInfo = new NzbInfo();
				if (!$nzbInfo->loadFromFile($nzbfile))
                {
                   if($this->verbose) echo sprintf("%s Unable to parse NZB File: %d/%s ...\n",
						'NfoProc', intval($r["id"]), $r["name"]);
					$this->setNfoMissing($r["id"]);
					continue;
                }

                $total+=1;

				$filename = basename($nzbfile);
				if($this->verbose) echo sprintf("NfoProc : Scanning %s - ", $r["name"]);

				$matches = $this->nfo_scan($nzbInfo);
				unset($nzbInfo);
				if(is_array($matches)){
					if(!count($matches)){
						if($this->verbose) echo "nfo missing.\n";
						$this->setNfoMissing($r["id"]);
						continue;
					}
				}else{
					if($this->verbose) echo "corrupt nzb.\n";
					$this->setNfoMissing($r["id"]);
					continue;
				}
				if($this->verbose) echo count($matches)." possible nfo(s).\n";
				$processed++;

				// Hash Matches by Release id
				$nfometa[(string)$r["id"]] = $matches;

				if(!($processed%$batch))
				{
					$nfoblob = array();
					if($this->verbose) echo "NfoProc : Retrieval ...";
					if($this->nfo_grab($nfometa, $nfoblob)){
						$before = array_keys($nfoblob);
						$this->parse_blobs($nfometa, $nfoblob);
						$after = array_keys($nfoblob);
						$removed = array_diff($before, $after);
						$this->store_blob($nfometa, $nfoblob, $removed);
					}
					if($this->verbose) echo "\n";

					// Reset nfo list array
					$nfometa = array();
				}
			}
			if(($processed%$batch)){
				$nfoblob = array();
				if($this->verbose) echo "NfoProc : Retrieval ...";
				if($this->nfo_grab($nfometa, $nfoblob)){
					$before = array_keys($nfoblob);
					$this->parse_blobs($nfometa, $nfoblob);
					$after = array_keys($nfoblob);
					$removed = array_diff($before, $after);
					$this->store_blob($nfometa, $nfoblob, $removed);
				}
				if($this->verbose) echo "\n";
			}
		}
		return true;
	}

	/**
	 * Delete a releasenfo row.
	 */
	public function deleteReleaseNfo($relid)
	{
		$db = new Settings();
		return $db->queryExec(sprintf("DELETE from releasenfo where releaseid = %d", $relid));
	}

	/**
	 * Mark a release as missing so it isn't ever parsed again
	 */
	private function setNfoMissing($relid)
	{
		$db = new Settings();
		$q = sprintf("UPDATE releases SET releasenfoid = %d ".
					"WHERE id = %d", Nfo::FLAG_NFO_MISSING, $relid);
		return $db->queryExec($q);
	}

	/**
	 * Returns the nfo from the database (blob)
	 */
	public function getNfo($relid, &$nfoout)
	{
		$db = new Settings();
		// Has NFO Query
		$mnfo = "SELECT uncompress(rn.nfo) as nfo FROM releases r ".
			"INNER JOIN releasenfo rn ON rn.releaseid = r.id AND rn.id = r.releasenfoid ".
			"WHERE rn.nfo IS NOT NULL AND r.id = %d LIMIT 1";
		$res = $db->queryOneRow(sprintf($mnfo, $relid));
		if($res && isset($res['nfo']))
		{
			$nfoout=$res['nfo'];
			return true;
		}
		return false;
	}

	/**
	 * Process NFO's
	 */
	public function processNfoFiles($batch=50)
	{
		$processed = 0;
		$total = 0;
		$this->scan_releases($processed, $total, $batch);
		if($this->verbose) echo sprintf("NfoProc : Complete %d NFOs detected from %d scanned NZB files.\n", $processed, $total);

		return $total;
	}
}
