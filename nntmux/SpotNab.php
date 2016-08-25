<?php
namespace nntmux;


use nntmux\db\Settings;
use app\extensions\util\Yenc;

// Help out those who don't have SSL enabled
if(!defined('OPENSSL_KEYTYPE_RSA')) {
	// OPENSSL_KEYTYPE_RSA is defined as 0 in php v4 and v5
	// so just give it a value to silence possible Notice Errors
	// for Windows Users (and give it the correct value)
	define('OPENSSL_KEYTYPE_RSA', 0);
}

// Define OpenSSL Config File
define('OPENSSL_CFG_PATH', NN_WWW. '/lib/openssl/openssl.cnf');

// JSON Encode Support (for those missing the constants)
if(!defined('JSON_HEX_TAG')) define('JSON_HEX_TAG', 1);
if(!defined('JSON_HEX_AMP')) define('JSON_HEX_AMP', 2);
if(!defined('JSON_HEX_APOS')) define('JSON_HEX_APOS', 4);
if(!defined('JSON_HEX_QUOT')) define('JSON_HEX_QUOT', 8);
if(!defined('JSON_UNESCAPED_UNICODE')) define('JSON_UNESCAPED_UNICODE', 256);

// Create a NNTP \Exception type so we can identify it from others
class SpotNabException extends \Exception { }

class SpotNab {
	// Segment Identifier domain is used to help build segments
	// prior to them being posted.
	const SEGID_DOMAIN = 'sample.com';

	// Subject line can look like this:
	// 01c9478809c80ccb07246d19852ed33b0a5f5d8d-20130125030511
	// 517a6210bd588e964654f75b807d65d55d420f5e-20130125002943
	// 33012186754c9c75050848d825ad6c2ae1af2e4d-20130125002849
	// To speed up fetch process, we can parse this to determine wether
	// or not to continue or not.

	// The TYPE is always prefixed on the subject, in this case, all
	// Comment types are always 00
	const FETCH_COMMENT_TYPE = "00";
	const FETCH_COMMENT_SUBJECT_REGEX =
		'/^(?P<checksum>[0-9a-z]{40})-(?P<utcref>[0-9]{14})$/i';

	// Discovery types are always 01
	const FETCH_DISCOVERY_TYPE = "01";
	const FETCH_DISCOVERY_SUBJECT_REGEX =
		'/^(?P<checksum>[0-9a-z]{40})-(?P<utcref>[0-9]{14})$/i';

	// The Message id can be parsed as follows
	const FETCH_MSGID_REGEX =
		'/^<(?P<crap>[a-z0-9]{30})\.(?P<type>[0-9]{2})\.(?P<local>[0-9]+)[^@]*@(?P<domain>.*)>$/i';

	// How many consecutive misses in a row do we allow while trying to retrieve
	// historic messages do we allow before assuming that we've exceeded the
	// the retention area.  In which case we just return the last date we
	// matched before the miss count started
	const FETCH_MAX_MISSES = 15;

	// Maximum number of messages to process at one time
	// Setting this to too large of a value can cause your usenet to
	// ignore and deny your request.
	const FETCH_USENET_BATCH = 25000;

	// Maximum number of messages to look back if one source
	// is lookin like it hasn't posted anything in a very long
	// time... we stop counting back headers when we reached
	// this figure
	const FETCH_MAXIMUM_HEADERS = 200000;

	// Maximum age (in seconds) we look back for a source
	const FETCH_MAXIMUM_AGE = 172800;

	// The maximum number of comments that can exist within 1 post to usenet
	const POST_MAXIMUM_COMMENTS = 500;

	// The number of seconds to wait before sending a new broadcast
	const POST_BROADCAST_INTERVAL = 2592000;

	// Verify Fetch Range; This is the number of records to look back after
	// a post to be sure that the post was successful.  The number may appear
	// kind of high, but consider a large active group and
	// this number makes sense.  Plus it doesn't take that long to rescan
	// a few headers.
	const VERIFY_FETCH_HEADER_COUNT = 300;

	// The php function openssl_public_encrypt() seems to fail whenever it's
	// passed more then this many characters into its buffer, therefore
	// we need to encrypt in batches if the content is longer or we
	// fail hard. DO NOT CHANGE THIS VALUE!! - EVER
	const SSL_MAX_BUF_LEN = 117;

	// If SSL_MAX_BUF_LEN is to large, we need to use delimiters
	// to help separate the batches as they are processed
	const SSL_BUF_DELIMITER = "\n";

	// Auto Discovery Functionality
	// Autodisovery allows us to post encrypted information other potential
	// sources we can use. We also post our source information so others can
	// refer to our comments as well.
	const AUTODISCOVER_POST_GROUP = "alt.binaries.aac";

	const AUTODISCOVER_POST_USER = "auto";

	const AUTODISCOVER_POST_EMAIL = "auto@ohnohs.ru";

	protected $_nntp;
	private $_site;
	protected $_globals;

	// Meta Information is fetched from DB
	private $_post_site;
	private $_post_code;
	private $_post_privacy;
	private $_post_title;

	private $_post_user;
	private $_post_email;
	private $_post_group;

	/* SSL Public & Private Keys */
	private $_ssl_pubkey;
	private $_ssl_prvkey;

	/* SSL Auto Discovery Public & Private Keys */
	protected $_ssl_auto_pubkey;
	protected $_ssl_auto_prvkey;

	/* Some booleans to make code reading easier */
	private $_can_post;
	private $_can_discover;
	private $_can_broadcast;
	private $_auto_enable;

	/* Track the last article scanned when preforming a discovery */
	private $_discovery_lastarticle;

	public function __construct($post_user = NULL, $post_email = NULL, $post_group = NULL) {
		$this->_pdo = new Settings();
		$this->_nntp = new NNTP(['Settings' => $this->_pdo]);
		$this->releaseImage =  new ReleaseImage($this->_pdo);
		$this->nzb = new NZB($this->_pdo);

		$this->_post_user = $post_user;
		$this->_post_email = $post_email;
		$this->_post_group = $post_group;

		// Fetch Meta information
		$this->_post_code = trim($this->_pdo->getSetting('code'))?
			$this->_pdo->getSetting('code'):NULL;
		$this->_post_title = trim($this->_pdo->getSetting('title'))?
			$this->_pdo->getSetting('title'):NULL;

		if ($this->_post_user === NULL){
			// Fetch the SpotNab UserID
			$this->_post_user = trim($this->_pdo->getSetting('spotnabuser'))?
				$this->_pdo->getSetting('spotnabuser'):NULL;
		}

		if ($this->_post_email === NULL){
			// Fetch the SpotNab EmailID
			$this->_post_email = trim($this->_pdo->getSetting('spotnabemail'))?
				$this->_pdo->getSetting('spotnabemail'):NULL;
		}

		if ($this->_post_group === NULL){
			// Fetch the SpotNab Usenet Group
			$this->_post_group = trim($this->_pdo->getSetting('spotnabgroup'))?
				$this->_pdo->getSetting('spotnabgroup'):NULL;
		}

		// Public Key
		$this->_ssl_pubkey = trim($this->_pdo->getSetting('spotnabsitepubkey'))?
			$this->_pdo->getSetting('spotnabsitepubkey'):false;
		if($this->_ssl_pubkey)
			$this->_ssl_pubkey = $this->decompstr($this->_ssl_pubkey);

		// Private Key
		$this->_ssl_prvkey = trim($this->_pdo->getSetting('spotnabsiteprvkey'))?
			$this->_pdo->getSetting('spotnabsiteprvkey'):false;
		if($this->_ssl_prvkey)
			$this->_ssl_prvkey = $this->decompstr($this->_ssl_prvkey);

		// Track Discovery Article
		$this->_discovery_lastarticle = intval($this->_pdo->getSetting('spotnablastarticle'));

		// Posting Flag
		$this->_can_post = (trim($this->_pdo->getSetting('spotnabpost')) == 1)?
			true:false;

		// Auto Enable Flag
		$this->_auto_enable = (trim($this->_pdo->getSetting('spotnabautoenable')) == 1)?
			true:false;

		// Spotnab Privacy Posting
		$this->_post_privacy = (trim($this->_pdo->getSetting('spotnabprivacy')) == 1)?
			true:false;

		// Auto-Discovery Private Key (used for Posting)
		$this->_ssl_auto_prvkey = "eJxtk7mOo0AARHO+YnI0Moe5woYGppv7PjLABmzAgDFg"
			."/PU7u/FWWlJJT6X3/f0bWdWR/eUH4Mv1UQxC9ctQs7/FN2EhpKQekgEw5MZTTi+Kt"
			."9pujGz84DeF8xeLY2vXODzDIbvERbWEs4TFGqebjAeXlXjb8Qa7kHrd6P1Ie646ng"
			."DBmYU1zOmZmrJE+7j4yCjf2XgDxbpc4NlVteGVikgTCyLb4z7Q1fPakDGXinjqKkE"
			."riorE1dzA/FHSil5Eu4/yNbRu0UMXlUF7OxLeEQQekAkw6jLAL9gmaRvKca7pyy4D"
			."S9zLoWx4LP/Q67EKaluREVSG8GzXdLOdaVHk2TZE4odQaOQNkc+8nGCxEzWQIsBQk"
			."GNV6vr8fD4K3bpzdrqLG0zNZkhLvxyi5Urz6w5v/Xw+iLW/to9Zv5SqA6kkNbTYg5"
			."Jl66Em2u2cvvAx2EWSM3JxWyiNa0GnAikzHkZdaA4pSIRDlYO0OE8Xf/rkTPth46d"
			."vuTaXPGx20TKXNBszaIKHhl3bAsGJ1zuW3DSGa6lB22Oi1nM0P6al2vEvej16m96i"
			."4/QDynQF49oIw4QaAMiBbYO+T19Xw8NVuyZ9K9zeXKgSMnifYIXbe/PBjIHuvHWTs"
			."/BqUQFJKXy6UiyleDpv5Wo0i+1lnATEps7RVcut8OMgIEyuSRcU90d+rNTGh3g3Yc"
			."/zUyHQLDlxjTazOx2bCZWZqxxgm0PlpRREyAa/w55CEre7Go2DNxWkC9vRVHqhflR"
			."NVduIZhOoJOdho9jeZRjxMtAuNae29fNze0p2Zr6PbFKJJezphdnOgXE+CrFJLq+S"
			."A528knKChLjTlKovVc3gJ5fpX6F8uY/syDXPg/xsu9wpKsEvmznLcENOVUXSVXova"
			."pt/CnrA5O8BuOqAbvAO/Qy8d0j800e14f+1+gPs3xif";

		// Auto-Discovery Public Key (used for discovering)
		$this->_ssl_auto_pubkey = "eJxlz7lygkAAgOGep7BnGCEIwXLv2UWOBTk7ZAZkUCKR"
			."Q/L0Odr85d99mvYTJIz7uzCBJ452Lil+n6Z4nDUe0BmKRxbzi4klgUAmAByYDzCCn"
			."XRhK9F+0m3v2n8kvhjsBVnR07PMJnQ3RbqB2mchb46iyExBLXZ6k/g5v/x0wf1Znz"
			."pj3fKbVW+fgOPRxA0ujVF/FBn9CsVWKHoULLbLUwYrMYaE3qfc4dSpijW9xYwc5lZ"
			."NrdwRj75+p1VVq6IeW1wOFwOxKlkjhZfz2euSgTnoTl/BUawcAwmg8ickPv6H/gbI"
			."LVDl";

		// Auto-Discovery Flags
		$this->_can_broadcast = (trim($this->_pdo->getSetting('spotnabbroadcast')) == 1)?
			true:false;
		$this->_can_broadcast = ($this->_can_broadcast && $this->_can_post);

		$this->_can_discover = (trim($this->_pdo->getSetting('spotnabdiscover')) == 1)?
			true:false;

		if (!$this->has_openssl()){
			// Can SpotNab even work; if not, we disable all flags
			$this->_can_broadcast = false;
			$this->_can_post = false;
		}
	}

	// ***********************************************************************
	public function has_openssl(){
		// return true if ssl is correctly configured and installed
		// otherwise return a fail
		return (is_readable(OPENSSL_CFG_PATH) && extension_loaded("openssl"));
	}

	// ***********************************************************************
	public function auto_clean($max_days=90){
		// automatically sweep old sources lingering that have not shown any
		// sort of life what-so-ever for more then 90 days
		$sql = "DELETE FROM spotnabsources WHERE "
			."lastbroadcast IS NOT NULL AND "
			."lastbroadcast < NOW() - INTERVAL $max_days DAY";
		$res = $this->_pdo->queryExec($sql);
	}

	// ***********************************************************************
	public function orphan_comment_clean($max_days=1, $batch=500){
		// Clean out orphan comments that are older then at least 1 day
		// this is to address people who do not wish to hold on to
		// comments they do not have a release for... Makes sense :)
		$offset = 0;
		$sql = "SELECT DISTINCT(gid) as gid FROM release_comments "
			."WHERE releases_id = 0 "
			."AND createddate < NOW() - INTERVAL $max_days DAY "
			."ORDER BY createddate "
			."LIMIT %d,%d";

		$sql_rel = "SELECT gid FROM releases WHERE gid IN ('%s') ";
		$sql_del = "DELETE FROM release_comments WHERE gid IN ('%s')";
		$total_delcnt = 0;
		while(1)
		{
			$res = $this->_pdo->query(sprintf($sql, $offset, $batch));
			if(!$res)break;

			# Assemble results into list
			$gids_found = [];
			$gids_matched = [];
			foreach($res as $item)
				$gids_found[] = $item['gid'];

			#echo 'B:'.sprintf($sql_rel, implode("','", $gids_found))."\n";
			$res2 = $this->_pdo->query(sprintf($sql_rel, implode("','", $gids_found)));
			foreach($res2 as $item)
				$gids_matched[] = $item['gid'];
			# Now we want to create an inverted list by eliminating the
			# matches we just fetched
			$gids_missing = array_diff($gids_found, $gids_matched);
			//print_r($gids_missing);
			if(count($gids_missing)){
				$s_gids_missing = implode("','", $gids_missing);
				$dresc = $this->_pdo->queryExec(sprintf($sql_del, $s_gids_missing));
				$total_delcnt += count($gids_missing);
				$offset += $batch - count($gids_missing);
			}else{
				$offset += $batch;
			}
			# make noise
			echo '.';

			if(!count($res))break;
		}
		return $total_delcnt;
	}

	// ***********************************************************************
	public function soft_reset(){
		// A harmless function that resets spotnab without losing sources
		// Calling this function will reset spotnab to think:
		//	- it hasn't fetched anything yet from existing sources
		//	- it needs to gracefully build a starting point from scratch
		//    using existing sources
		//  - it has never posted discovery information
		//  - it has never scanned for existing discoveries

		// resets sources so they need to query again
		$sources = "UPDATE spotnabsources SET "
			."lastupdate = NULL,"
			."lastbroadcast = NULL,"
			."lastarticle = 0";
		$discovery_a = "UPDATE settings SET "
			."value = '0' "
			."WHERE setting = 'spotnablastarticle'";
		$broadcast = "Update settings SET "
			."updateddate = '1980-01-01 00:00:00' "
			."WHERE setting = 'spotnabbroadcast'";

		// Discovery should only be set back X days worth defined
		// by the maximum age a broadcast can be.
		$reftime = date("Y-m-d H:i:s",
			time()-(SpotNab::POST_BROADCAST_INTERVAL));
		$discovery_b = "Update settings SET "
			."updateddate = '1980-01-01 00:00:00' "
			."WHERE setting = 'spotnabdiscover'";

		$post = "Update settings SET "
			."updateddate = '$reftime' "
			."WHERE setting = 'spotnabpost'";
		$this->_pdo->queryExec($sources);
		$this->_pdo->queryExec($discovery_a);
		$this->_pdo->queryExec($discovery_b);
		$this->_pdo->queryExec($broadcast);
		$this->_pdo->queryExec($post);
	}

	// ***********************************************************************
	public function fetch_discovery($reftime = NULL, $retries=3){
		$last = $first = NULL;

		// Return Value; Initialize it to Okay
		// we'll change it to false if we have to.
		$fetch_okay = true;

		// Track how many records were inserted, updated
		$inserted = 0;
		$updated = 0;

		if (!$this->_can_discover){
			// discovery disabled
			return false;
		}

		if($reftime === NULL){
			$q = "SELECT updateddate FROM settings WHERE "
				."setting = 'spotnabdiscover'";
			$res = $this->_pdo->queryOneRow($q);
			if($res){
				$reftime = $res['updateddate'];
			}else{
				// Fetch local time (but look back the maximum duration
				// that a discovery message can exist for
				$reftime = $this->utc2local((time()-(SpotNab::POST_BROADCAST_INTERVAL)));
			}
		}

		// Connect to server
		try{
			if (($this->_pdo->getSetting('alternate_nntp') == 1 ? $this->_nntp->doConnect(true, true) : $this->_nntp->doConnect()) !== true) {
				exit($this->_pdo->log->error("Unable to connect to usenet." . PHP_EOL));
			}
		}
		catch(\Exception $e){
			printf("Failed to connect to Usenet\n");
			return false;
		}

		echo "Spotnab : Discovery ";
		$summary = $this->_nntp->selectGroup(
			SpotNab::AUTODISCOVER_POST_GROUP);

		$first = $this->_discovery_lastarticle;
		if($first <= 0 || $first > $summary['last'] ){
			// Look back until reftime
			$first = $this->_first_article_by_date(
				SpotNab::AUTODISCOVER_POST_GROUP,
				$reftime
			);
		}

		if($first === false){
			// Fail
			echo "Failed\n";
			return false;
		}

		// Group Processing Initialization
		$processed = 0;
		$batch = $last = intval($summary['last']);
		$total = abs($last-$first);

		// Select Group
		while($fetch_okay && $processed < $total)
		{
			try
			{
				// Prepare Initial Batch
				if ($total > SpotNab::FETCH_USENET_BATCH)
					$batch = $first + SpotNab::FETCH_USENET_BATCH;

				// Batch Processing
				while ($processed < $total)
				{
					$headers = $this->_get_headers(SpotNab::AUTODISCOVER_POST_GROUP,
						"$first-$batch", $retries);

					if($headers === false){
						// Retry Atempts exausted
						$fetch_okay = false;
						break;
					}

					// Process the header batch
					$saved = $this->process_discovery_headers($headers);
					if($saved !== false)
					{
						$inserted += $saved[0];
						$updated += $saved[1];
					}

					$processed += ($batch-$first);
					// Increment starting index
					$first += ($batch-$first);

					if ($last-$first >= SpotNab::FETCH_USENET_BATCH){
						// Fetch next batch
						$batch = $first + SpotNab::FETCH_USENET_BATCH;
					}else{
						$batch = $last;
					}
					//echo "$first-$batch, processed=$processed\n";
					//print_r($headers);
				}

			}catch(\Exception $e){
				// Reset Connection
				$fetch_okay = $this->_nntpReset(SpotNab::AUTODISCOVER_POST_GROUP);

				// Track retry attempts
				$retries--;
				if($retries <= 0){
					// Retry Atempts exausted
					$fetch_okay = false;
					break;
				}
				continue;
			}
		}
		$sql = sprintf("Update settings SET value = '%d' "
			."WHERE setting = 'spotnablastarticle'", $last);
		$this->_pdo->queryExec($sql);
		printf("%d new and %d updated source(s).\n", $inserted, $updated);

		// Update reference point
		$q = "Update settings SET updateddate = NOW() WHERE "
			."setting = 'spotnabdiscover'";
		$this->_pdo->queryExec($q);

		return $inserted + $updated;
	}

	// ***********************************************************************
	public function auto_post_discovery($repost_sec = SpotNab::POST_BROADCAST_INTERVAL){
		// performs a post discovery once the time in seconds has elapsed
		$q = "SELECT updateddate FROM settings WHERE "
			."setting = 'spotnabbroadcast'";
		$res = $this->_pdo->queryOneRow($q);
		$then = strtotime($res['updateddate']);
		$now = time();
		if(($now - $then) > $repost_sec){
			// perform a post
			if($this->post_discovery())
			{
				// Update post time
				$q = "Update settings SET updateddate = NOW() WHERE "
					."setting = 'spotnabbroadcast'";
				$res = $this->_pdo->queryExec($q);
			}
		}
	}

	// ***********************************************************************
	public function post_discovery($reftime = NULL, $retries=3){
		$reftime_local = $reftime;
		$article = NULL;
		$rc = new ReleaseComments();
		$us = new Users();

		if($reftime_local === NULL){
			// Fetch local time
			$reftime_local = $this->utc2local();
		}
		// some error checking....
		if(!$this->_can_broadcast){
			// Broadcasting not possible
			return false;
		}

		// Generate keys if one doesn't exist
		if(!($this->_ssl_prvkey && $this->_ssl_pubkey))
			if($this->keygen(false, true) === false)
				return false;

		// Get Discovery Private Key
		$prvkey = $this->decompstr($this->_ssl_auto_prvkey);
		if (!$prvkey){
			// This is a serious problem because the hard-coded discovery
			// key should always decrypt!
			return false;
		}

		printf("Spotnab : Broadcast ...");

		// Fetch some date ranges
		$last_month = date("Y-m-d",strtotime(
			date("Y-m-d", time()) . " - 30 day"));
		$last_year = date('Y-m-d',strtotime(
			date("Y-m-d", time()) . " - 365 day"));

		// Header
		$message = [
			'site' => [
				// title & code taken out to keep things anonymous for now
				//'title' => $this->_post_title,
				//'code' => $this->_post_code,
				'id' => md5($this->_pdo->getSetting('siteseed')),
				'users' => $us->getCount()
			],
			'posts' => [
				'user' => $this->_post_user,
				'email' => $this->_post_email,
				'group' => $this->_post_group,
				'privacy' => $this->_post_privacy,
				'public_key' => $this->compstr($this->_ssl_pubkey)
			],
			'comments' => [
				'past_month' => $rc->getCommentCount($last_month, true),
				'past_year' => $rc->getCommentCount($last_year, true),
				'total' => $rc->getCommentCount(NULL, true)
			],
			'postdate_utc' => $this->local2utc($reftime_local),
		];

		// Encode Message so it can be posted
		$article = $this->encodePost(
			$message, $reftime_local, false,
			$prvkey, NULL, true,
			SpotNab::FETCH_DISCOVERY_TYPE,
			SpotNab::AUTODISCOVER_POST_USER,
			SpotNab::AUTODISCOVER_POST_EMAIL,
			SpotNab::AUTODISCOVER_POST_GROUP
		);

		if($article === false){
			echo "Failed.\n";
			return false;
		}

		// Post message
		if ($this->_postArticle($article, $retries))
		{
			// Post is good; update database
			//echo "Done.\n";
			return true;
		}
		echo "Failed.\n";
		return false;
	}

	// ***********************************************************************

	/**
	 * This function queries all enabled sources and fetches any content
	 * they are sharing.
	 * The specified $reftime is presumed to be local *not utc*
	 *
	 * @param null $reftime
	 * @param int  $retries
	 *
	 * @return int
	 */
	public function fetch($reftime = NULL, $retries = 3) {

		$first = NULL;

		// Return Value; Initialize it to Okay
		// we'll change it to false if we have to.
		$fetch_okay = true;
		$backfill = false;

		// We set a cap on how many days in the past we look
		$_max_age = time() - SpotNab::FETCH_MAXIMUM_AGE;
		if($reftime === NULL){
			// Fetch local time (but look back X days)
			$reftime = $this->utc2local($_max_age);
		}else{
			// Someone specified a date range to query from
			$backfill = true;

			if(is_string($reftime)){
				$reftime = date("Y-m-d H:i:s", strtotime($reftime));
			}else if(is_int($reftime)){
				$reftime = date("Y-m-d H:i:s", $reftime);
			}
			$_max_age = strtotime($reftime);
		}

		// First we find all active sources and build a hash table we can
		// use to simplify fetching.
		$res = $this->_pdo->query('SELECT * FROM spotnabsources WHERE active = 1 ORDER BY usenetgroup,lastupdate DESC');
		$group_hash = [];
		$group_article_start = [];
		$id_hash = [];

		if(!count($res))
			return true;

		foreach($res as $source){
			$ghash = trim($source['usenetgroup']);
			if(!array_key_exists($ghash, $group_hash)){
				// Because our results are sorted by group, if we enter
				// here then we're processing a brand new group...
				$group_hash[$ghash] = [];

				// Initialize our article start point
				$group_article_start[$ghash] = 0;

				// Initialize id Hash
				$id_hash[$ghash] = [];
			}

			// Reference time is in UTC on Usenet but local in our database
			// this isn't intentionally confusing, this is done so all our local
			// times reflect those across the world, and it also makes joins to
			// the table much easier since they join doesn't have to accomodate
			// for the utc time itself.

			// Therefore, we need to take the lastupdate time and convert it to
			// UTC for processing.
			$ref = $backfill?date("Y-m-d H:i:s", $_max_age):$source['lastupdate'];

			if(!$ref){
				// We've never fetched from the group before, so we'll use
				// the reftime passed to the function
				$ref = $reftime;
			}

			// Therefore, we need to take the lastupdate time and convert it to
			// UTC for processing.
			$article = abs(intval($source['lastarticle']));
			if($article > 0){
				if($group_article_start[$ghash] == 0)
					$group_article_start[$ghash] = $article;
				else
					$group_article_start[$ghash] = ($article < $group_article_start[$ghash])?
						$article:$group_article_start[$ghash];
			}

			// Store id
			$id_hash[$ghash][] = $source['id'];

			// Store Source Details
			$group_hash[$ghash][] = [
				'id' => $source['id'],
				'key' => $this->decompstr(trim($source['publickey'])),
				'user' => trim($source['username']),
				'email' => trim($source['useremail']),

				// Store local reference time
				'ref' => strtotime($ref),

				// Store last article reference
				'article' => $article
			];
		}

		// We want to resort the internal arrays by they're ref time
		// so that the oldest (longest without an update) is processed
		// first
		foreach(array_keys($group_hash) as $key){
			$_ref = [];
			foreach($group_hash[$key] as $id => $source){
				# Source Time (within reason)
				if($backfill)
					$_ref[$id] = $_max_age;
				else
					$_ref[$id] =
						($source['ref'] < $_max_age)?$_max_age:$source['ref'];
			}
			// Sort results (oldest in time first)
			array_multisort($_ref, SORT_ASC, $group_hash[$key]);
		}

		// Now we fetch headers

		// Connect to server
		try{
			if (($this->_pdo->getSetting('alternate_nntp') == 1 ? $this->_nntp->doConnect(true, true) : $this->_nntp->doConnect()) !== true) {
				exit($this->_pdo->log->error("Unable to connect to usenet." . PHP_EOL));
			}
		}
		catch(\Exception $e){
			printf("Failed to connect to Usenet");
			return false;
		}

		// Track how many records were inserted, updated
		$inserted = 0;
		$updated = 0;

		foreach($group_hash as $group => $hash){
			printf("Spotnab : %d source(s)...", count($hash));

			$summary = $this->_nntp->selectGroup($group);
			// Get our article id
			$first = ($backfill)?0:$group_article_start[$group];
			if($first == 0){
				// We can safely use the first $hash entry since we've
				// already sorted it in ascending order above, so this
				// is the time furthest back
				$first = $this->_first_article_by_date($group, $hash[0]['ref']);
				if($first === false){
					continue;
				}
			}

			// Group Processing Initialization
			$processed = 0;
			$batch = $last = intval($summary['last']);
			$total = abs($last-$first);

			// Select Group
			while($fetch_okay && $processed < $total)
			{
				try
				{
					// Prepare Initial Batch
					if ($total > SpotNab::FETCH_USENET_BATCH)
						$batch = $first + SpotNab::FETCH_USENET_BATCH;

					// Batch Processing
					while ($processed>=0 && $processed < $total)
					{
						$headers = $this->_get_headers($group,
							"$first-$batch", $retries);

						if($headers === false){
							// Retry Atempts exausted
							$fetch_okay = false;
							break;
						}

						// Process the header batch
						$saved = $this->process_comment_headers($headers, $hash);
						if($saved !== false)
						{
							$inserted += $saved[0];
							$updated += $saved[1];
						}

						$processed += ($batch-$first);
						// Increment starting index
						$first += ($batch-$first);

						if ($last-$first >= SpotNab::FETCH_USENET_BATCH){
							// Fetch next batch
							$batch = $first + SpotNab::FETCH_USENET_BATCH;
						}else{
							$batch = $last;
						}
						//echo "$first-$batch, processed=$processed\n";
						//print_r($headers);
					}

				}catch(\Exception $e){
					// Reset Connection
					$fetch_okay = $this->_nntpReset($group);

					// Track retry attempts
					$retries--;
					if($retries <= 0){
						// Retry Atempts exausted
						$fetch_okay = false;
						break;
					}
					continue;
				}
			}
			$this->_pdo->queryExec(sprintf('UPDATE spotnabsources SET lastarticle = %d WHERE id IN (%s)',
				$last,
				implode(",", $id_hash[$group])));
			echo "\n";
		}

		// Ensure We're not connected
		try{$this->_nntp->doQuit();}
		catch(\Exception $e)
		{/* do nothing */}

		return $inserted + $updated;
	}

	public function processGID($limit=500, $batch=5000, $delete_broken_releases = false){
		// Process until someone presses cntrl-c

		$nzb = new NZB();

		$processed = 0;

		// We need an offset for tracking unhandled issues
		$offset = 0;

		$fsql = 'SELECT id, name, guid FROM releases '
			.'WHERE gid IS NULL ORDER BY adddate DESC LIMIT %d,%d';
		$usql = "UPDATE releases SET gid = '%s' WHERE id = %d";

		while(1){
			// finish
			if($limit > 0 && $processed >= $limit)
				break;
			$batch=($limit > 0 && $batch > $limit)?$limit:$batch;
			$res = $this->_pdo->query(sprintf($fsql, $offset, $batch));
			if(!$res)break;
			if(count($res) <= 0)break;
			$offset += $batch;

			foreach ($res as $r){
				$nzbfile = $nzb->getNZBPath($r["guid"]);
				if($nzbfile === NULL){
					continue;
				}

				$nzbInfo = new NZBInfo();
				if (!$nzbInfo->loadFromFile($nzbfile))
				{
					if($delete_broken_releases){
						$release = new Releases();
						$release->deleteSingle(['g' => $r['guid'], 'i' => $r['id']], $this->nzb, $this->releaseImage);
						// Free the variable in an attempt to recover memory
						unset($release);
						echo '-';
					}else{
						// Skip over this one for future fetches
						$offset++;
					}
					continue;
				}
				$gid = false;
				if (!empty($nzbInfo->gid))
					$gid = $nzbInfo->gid;
				// Free the variable in an attempt to recover memory
				unset($nzbInfo);

				if(!$gid){
					if($delete_broken_releases){
						$release = new Releases();
						$release->$release->deleteSingle(['g' => $r['guid'], 'i' => $r['id']], $this->nzb, $this->releaseImage);
						unset($release);
						echo '-';
					}else{
						// Skip over this one for future fetches
						$offset++;
					}
					continue;
				}

				// Update DB With Global Identifer
				$ures = $this->_pdo->queryExec(sprintf("UPDATE releases SET gid = %s WHERE id = %d", $this->_pdo->escapeString($gid), $r['id']));
				if($ures->rowCount() == 0){
					printf("\nPostPrc : Failed to update: %s\n", $r['name']);
				}
				// make noise...
				echo '.';
				$processed += 1;
			}
		}

		$affected = $this->_pdo->queryExec(sprintf('UPDATE release_comments, releases SET release_comments.gid = UNHEX(releases.nzb_guid),
											release_comments.nzb_guid = UNHEX(releases.nzb_guid)
											WHERE releases.id = release_comments.releases_id
											AND release_comments.gid IS NULL
											AND UNHEX(release_comments.nzb_guid) = "0"
											AND UNHEX(releases.nzb_guid) IS NOT NULL
											AND releases.gid IS NOT NULL '
			)
		);
		$rows = $affected->rowCount();
		if($rows > 0)
			$processed += $rows;
		return $processed;
	}

	// ***********************************************************************
	public function keygen($print = true, $force_regen = false){
		// Simply generate a Public/Private Key pair if they don't already
		// exist

		// A small boolean we safely toggle after performing
		// a few checks first to make sure it's safe to do so
		$do_keygen = true;

		if($force_regen === false){
			// Not forcing, so we immediately toggle the keygen
			// flag, we'll toggle it back if we feel the need
			$do_keygen = false;

			if($this->_ssl_pubkey && $this->_ssl_prvkey){

				$str_in = $this->getRandomStr(80);
				$str_out = $this->decrypt($this->encrypt($str_in));

				if($str_in != $str_out){
					// Our key isn't good for nothin...
					// regen a new one
					$do_keygen = true;
				}
			}
		}

		if($do_keygen)
		{
			// Set new Key
			$keys = $this->_keygen();
			if(is_array($keys)){
				// Force New Username
				$sql = sprintf("Update settings SET value = %s "
					."WHERE setting = 'spotnabuser'",
					$this->_pdo->escapeString(sprintf("nntp-%s",substr(md5($keys['pubkey']), 0, 4))));
				$this->_pdo->queryExec($sql);
				// Force New Email
				$sql = sprintf("Update settings SET value = %s "
					."WHERE setting = 'spotnabemail'",
					$this->_pdo->escapeString(sprintf("nntp-%s@%s.com",
						substr(md5($keys['pubkey']), 4, 8),
						substr(md5($keys['pubkey']), 8, 16)
					)));
				$this->_pdo->queryExec($sql);
				// Save Keys
				$sql = sprintf("Update settings SET value = %s ".
					"WHERE setting = 'spotnabsitepubkey'",
					$this->_pdo->escapeString($keys['pubkey']));
				$this->_pdo->queryExec($sql);
				//echo $keys['pubkey']."\n";

				$sql = sprintf("Update settings SET value = %s ".
					"WHERE setting = 'spotnabsiteprvkey'",
					$this->_pdo->escapeString($keys['prvkey']));
				$this->_pdo->queryExec($sql);

				// Update settings Information
				$this->_post_user = trim($this->_pdo->getSetting('spotnabuser'));
				$this->_post_email = trim($this->_pdo->getSetting('spotnabemail'));
				$this->_ssl_pubkey = $this->decompstr($this->_pdo->getSetting('spotnabsitepubkey'));
				$this->_ssl_prvkey = $this->decompstr($this->_pdo->getSetting('spotnabsiteprvkey'));
			}else{
				// echo "Failed.";
				return false;
			}
		}

		if($print){
			printf("SPOTNAB USER  : %s\n", $this->_post_user);
			printf("SPOTNAB EMAIL : %s\n", $this->_post_email);
			printf("SPOTNAB GROUP : %s\n", $this->_post_group);
			printf("SPOTNAB PUBLIC KEY (Begin copy from next line):\n%s\n",
				$this->_pdo->getSetting('spotnabsitepubkey'));
		}

		return [
			'pubkey' => $this->_pdo->getSetting('spotnabsitepubkey'),
			'prvkey' => $this->_pdo->getSetting('spotnabsiteprvkey')
		];
	}

	// ***********************************************************************
	protected function _first_article_by_date($group, $refdate, $limit = SpotNab::FETCH_MAXIMUM_HEADERS, $retries=3){
		// fetches the first article starting at the time specified
		// by ref time.
		//
		// ref time is expected to be a local time in format:
		// YYYY-MM-DD hh:mm:ss or as integer
		//
		// This function returns the first message id to scan from
		// based on the time specified.  If no articles are found
		// or something bad happens, false is returned.

		$interval = 1;

		// if we start charting into an area where retention period
		// isn't present, we're dealing with a blank/dead record that
		// is lost.... to many blanks and we have to abort.
		$misses = 0;

		// curfews are a way of not letting an infinit while
		// loop from taking over while we hunt for a date...
		// since usenet is not always listed chronologically
		// we can get in endless loops trying to find the ealiest
		// date.  To handle that we set a curfew and drop back
		// a set amount of records and work with that
		// no one is perfect right?
		$curfew = 10;

		if(is_string($refdate)){
			// Convert to Integer (Local Time)
			$refdate = strtotime($refdate);
		}

		while(($retries > 0) && ($interval > 0)){
			$summary = $this->_nntp->selectGroup($group);

			$_last = $last = intval($summary['last']);
			$first = intval($summary['first']);

			$curdate = $lastdate = NULL;
			$curid = $lastid = $first;
			$interval = 0;
			while($retries > 0){

				// Save Last Interval
				$lastinterval = $interval;

				// Adjust Interval
				if(($last - $first) > 3)
					$interval = floor(($last - $first)/2);
				else
					$interval = 1;

				if($misses >= SpotNab::FETCH_MAX_MISSES){
					// Misses reached
					$last = intval($summary['last']);
					// Adjust pointer
					$lastid = ($lastid=== false)?$first:$lastid;
					if($lastid >0){
						if (($last-$lastid) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$lastid = $last - $limit;
						}
						echo " ".(abs($last-$lastid))." record(s) back.";
						return $lastid;
					}else{
						if (($_last-$last) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$last = $_last - $limit;
						}
						echo " ".(abs($_last-$last))." record(s) back.";
						return $last;
					}
				}

				// Swap
				$lastdate = $curdate;
				if($curid > 0)
					$lastid = $curid;

				$msgs = $this->_get_headers(
					$group, ($last-$interval), $retries);

				if($msgs === false){
					if (($_last-$last) > $limit){
						// We exceeded our maximum header limit
						// adjust accordingly
						$last = $_last - $limit;
					}
					echo " ".(abs($_last-$last))." record(s) back.";
					return $last;
				}

				// Reset Miss Count
				$misses = 0;

				// Save Tracker
				$curdate = strtotime($msgs[0]['Date']);
				$curid = intval($msgs[0]['Number']);
				if($curid <= 0){
					$lastid = ($lastid=== false)?$first:$lastid;
					if($lastid >0){
						if (($_last-$lastid) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$lastid = $_last - $limit;
						}
						echo " ".(abs($_last-$lastid))." record(s) back.";
						return $lastid;
					}else{
						if (($_last-$last) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$last = $_last - $limit;
						}
						echo " ".(abs($_last-$last))." record(s) back.";
						return $last;
					}
				}

				if($interval == 1){
					// We're soo close now...
					$curfew --;
					if($curfew <= 0)
					{
						if (($_last-$curid) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$curid = $_last - $limit;
						}
						// curfew met... just deal with our current spot
						echo " ".($_last-$curid)." record(s) back.";
						return $curid;
					}

					if($refdate > $curdate && $refdate > $lastdate){
						if (($_last-$curid) > $limit){
							// We exceeded our maximum header limit
							// adjust accordingly
							$curid = $_last - $limit;
						}
						// Found content
						echo " ".($_last-$curid)." record(s) back.";
						return $curid;
					}else if($refdate > $curdate && $refdate > $lastdate){
						// Close... Shuffle forward a bit
						$first+=2;
					}else{
						// Slide window and try again
						$last-=2;
					}
					$interval=2;
					continue;
				}

				// Make some noise
				if($interval%2)echo ".";

				// Adjust Boundaries
				if($curdate > $refdate){
					// We need to look further forward
					$last = $curid+1;
				}else if ($curdate <= $refdate){
					// We need To look further back
					$first = $curid-1;
				}
			}
		}
		//echo "n/a m:$misses,i:$interval\n";
		return false;
	}

	// ***********************************************************************
	public function process_comment_headers($headers, $group_hash, $save = true){
		/*
		*	We iterate over the provided headers (generated by
		*	$this->_get_headers() to a structure that is at the very
		*	minimum looking like this:
		*
		*	array (
		*		[0] => array (
		*				'Number': <int>
		*				'Subject': <string>
		*				'From': <string>
		*				'Date': <string>
		*				'Message-ID': <string>
		*				'Bytes': <int>
		*				'Lines': <int>
		*				'Epoch': <int>
		*			),
		*			...
		*		)
		*	From the structure above, we process our group hash and retrieve
		*	all the binary data we need on valid content.
		*
		*	A group_hash() record looks like this:
		*		array(
		*			array(
		*				'id': <int>,
		*				'key': <string>,
		*				'user': <string>,
		*				'email': <string>,
		*				'ref': <int>,
		*			),
		*			array(
		*				'id': <int>,
		*				'key': <string>,
		*				'user': <string>,
		*				'email': <string>,
		*				'ref': <int>,
		*			),
		*		)
		*/

		if(!count($group_hash)){
			// Nothing to process
			return [];
		}

		//
		// Prepare some general SQL Commands for saving later if all goes well
		//


		// Comments
		$sql_new_cmt = "INSERT INTO release_comments (".
			"id, sourceid, username, users_id, gid, cid, isvisible, ".
			"releases_id, text, createddate, issynced, nzb_guid) VALUES (".
			"NULL, %d, %s, 0, %s, %s, %d, 0, %s, %s, 1, UNHEX(%s))";
		$sql_upd_cmt = "UPDATE release_comments SET ".
			"isvisible = %d, text = %s".
			"WHERE sourceid = %d AND gid = %s AND cid = %s AND nzb_guid = UNHEX(%s)";
		$sql_fnd_cmt = "SELECT count(id) as cnt FROM release_comments ".
			"WHERE sourceid = %d AND gid = %s AND cid = %s";

		// Sync Times
		$sql_sync = "UPDATE spotnabsources SET lastupdate = %s ".
			"WHERE id = %d";

		$matches = NULL;
		$processed = 0;
		$updates = 0;
		$inserts = 0;
		foreach ($headers as $header){
			// Preform some general scanning the header to determine
			// if it could possibly be a valid post.
			if(!preg_match(SpotNab::FETCH_MSGID_REGEX,
				$header['Message-ID'], $matches)){
				continue;
			}
			if($matches['domain'] != SpotNab::SEGID_DOMAIN)
				continue;

			if($matches['type'] != SpotNab::FETCH_COMMENT_TYPE)
				continue;

			// Now we check the subject line; it provides the first part of
			// the key to determining if we should handle the message or not
			if(!preg_match(SpotNab::FETCH_COMMENT_SUBJECT_REGEX,
				$header['Subject'], $matches)){
				continue;
			}

			// We have a match; So populate potential variables
			$checksum = $matches['checksum'];
			$refdate = $matches['utcref'];
			$refdate_epoch = @strtotime($matches['utcref']. " UTC");
			if($refdate_epoch === false || $refdate_epoch < 0){
				// Bad time specified
				continue;
			}
			// PreKey is used to attempt to run the decode algorithm
			// a head of time.. if we can decrypt this we can probably
			// assume the body will decode too (and won't be a waste of
			// time to download it)

			foreach($group_hash as $hash){
				// Track how many records we handled
				$processed++;

				// First check the ref date... if it's newer then what we've
				// already processed, then we'll just keep on chugging along.
				if($refdate_epoch <= $hash['ref']){
					continue;
				}

				// Scan header information for supported matches
				if(!preg_match('/^(?P<user>[^<]+)<(?P<email>[^>]+)>$/',
					$header['From'], $matches))
					continue;

				// Match against our sources posts
				if(trim($matches['user']) != $hash['user'])
					continue;
				if(trim($matches['email']) != $hash['email'])
					continue;

				// If we reach here, we've found a header we can process
				// The next step is to download the header's body

				// We'll do some final verifications on it such as detect
				// if the checksum is okay, and verify that the timestamp
				// within the body matches that of the header... then we
				// can start processing the guts of the body.

				if($save){
					// Download Body
					$body = $this->_get_body($header['Group'],
						$header['Message-ID']);
					if($body === false){
						continue;
					}

					// Decode Body
					$body = $this->decodePost($body, $hash['key']);
					if($body === false)
						continue; // Decode failed

					// Verify Body
					if(!is_array($body))
						continue; // not any array

					if(!(bool)count(array_filter(array_keys($body), 'is_string')))
						continue; // not an associative array

					if((!array_key_exists('server', $body)) ||
						(!array_key_exists('postdate_utc', $body)))
						continue; // base structure missing

					// Compare postdate_utc and ensure it matches header
					// timestamp
					if(preg_replace('/[^0-9]/', '',
							$body['postdate_utc']) != $refdate)
						continue;

					// Comment Handling
					if(array_key_exists('comments',$body) &&
						is_array($body['comments'])){
						$rc = new ReleaseComments();

						foreach($body['comments'] as $comment){

							// Verify Comment is parseable
							if(!is_array($comment))
								continue; // not an array
							if(!count(array_filter(array_keys($comment))))
								continue; // not an associative array

							// Store isvisible flag
							$is_visible = 1;
							if(array_key_exists('is_visible', $comment))
								$is_visible = (intval($comment['is_visible'])>0)?1:0;

							// Check that comment doesn't already exist
							$res = $this->_pdo->queryOneRow(sprintf($sql_fnd_cmt,
								$hash['id'],
								$this->_pdo->escapeString($comment['gid']),
								$this->_pdo->escapeString($comment['cid'])));

							// Store Results in DB
							if($res && intval($res['cnt'])>0){
								// Make some noise
								echo '.';
								$updates += ($this->_pdo->queryExec(sprintf($sql_upd_cmt,
										$is_visible,
										$this->_pdo->escapeString($comment['comment']),
										$hash['id'],
										$this->_pdo->escapeString($comment['gid']),
										$this->_pdo->escapeString($comment['cid']),
										$this->_pdo->escapeString($comment['gid'])
									))>0)?1:0;
							}else{
								// Make some noise
								echo '+';
								// Perform Insert
								$res = $this->_pdo->queryInsert(sprintf($sql_new_cmt,
									$hash['id'],
									$this->_pdo->escapeString($comment['username']),
									$this->_pdo->escapeString($comment['gid']),
									$this->_pdo->escapeString($comment['cid']),
									$is_visible,
									$this->_pdo->escapeString($comment['comment']),
									// Convert createddate to Local
									$this->_pdo->escapeString($this->utc2local(
										$comment['postdate_utc'])),
									$this->_pdo->escapeString($comment['gid'])
								));
								$inserts += 1;
							}
							$rc->updateReleaseCommentCount($comment['gid']);
						}
					}

					// Update spotnabsources table, set lastupdate to the
					// timestamp parsed from the header.
					$this->_pdo->queryExec(sprintf($sql_sync,
							$this->_pdo->escapeString(
								$this->utc2local($body['postdate_utc'])),
							$hash['id']
						)
					);
				}else{
					// Debug non/save mode; mark update
					$updates += 1;
				}

				// always break if we made it this far... no mater how many
				// other groups are being processed, we've already matched
				// for this article, so we don't need to process it for
				// other sources.
				break;
			}
		}
		return [$inserts, $updates];
	}

	// ***********************************************************************
	public function process_discovery_headers($headers, $save = true){
		/*
		*	We iterate over the provided headers (generated by
		*	$this->_get_headers() to a structure that is at the very
		*	minimum looking like this:
		*
		*	array (
		*		[0] => array (
		*				'Number': <int>
		*				'Subject': <string>
		*				'From': <string>
		*				'Date': <string>
		*				'Message-ID': <string>
		*				'Bytes': <int>
		*				'Lines': <int>
		*				'Epoch': <int>
		*			),
		*			...
		*		)
		*/

		//
		// Prepare some general SQL Commands for saving later if all goes well
		//

		// Auto Enable Flag (used for inserts only)
		$auto_enable = ($this->_auto_enable)?"1":"0";

		// Spotnab Sources
		$sql_new_cmt = "INSERT INTO spotnabsources (".
			"id, username, useremail, usenetgroup, publickey, ".
			"active, description, lastupdate, lastbroadcast, dateadded) VALUES (".
			"NULL, %s, %s, %s, %s, $auto_enable, %s, NULL, %s, NOW())";
		$sql_upd_cmt = "UPDATE spotnabsources SET ".
			"lastbroadcast = %s ".
			"WHERE username = %s AND useremail = %s AND usenetgroup = %s";
		$sql_fnd_cmt = "SELECT count(id) as cnt FROM spotnabsources ".
			"WHERE username = %s AND useremail = %s AND usenetgroup = %s";

		$matches = NULL;
		$processed = 0;
		$inserts = 0;
		$updates = 0;
		foreach ($headers as $header){
			// Preform some general scanning the header to determine
			// if it could possibly be a valid post.

			// Now we check the subject line; it provides the first part of
			// the key to determining if we should handle the message or not
			if(!preg_match(SpotNab::FETCH_MSGID_REGEX,
				$header['Message-ID'], $matches)){
				continue;
			}
			if($matches['domain'] != SpotNab::SEGID_DOMAIN)
				continue;

			if($matches['type'] != SpotNab::FETCH_DISCOVERY_TYPE)
				continue;

			// Now we check the subject line; it provides the first part of
			// the key to determining if we should handle the message or not
			if(!preg_match(SpotNab::FETCH_DISCOVERY_SUBJECT_REGEX,
				$header['Subject'], $matches)){
				continue;
			}

			// We have a match; So populate potential variables
			$checksum = $matches['checksum'];
			$refdate = $matches['utcref'];
			$refdate_epoch = @strtotime($matches['utcref']. " UTC");
			if($refdate_epoch === false || $refdate_epoch < 0){
				// Bad time specified
				continue;
			}
			// PreKey is used to attempt to run the decode algorithm
			// a head of time.. if we can decrypt this we can probably
			// assume the body will decode too (and won't be a waste of
			// time to download it)

			// Track how many records we handled
			$processed++;

			// Scan header information for supported matches
			if(!preg_match('/^(?P<user>[^<]+)<(?P<email>[^>]+)>$/',
				$header['From'], $matches))
				continue;

			// Match against our sources posts
			if(trim($matches['user']) != SpotNab::AUTODISCOVER_POST_USER)
				continue;
			if(trim($matches['email']) != SpotNab::AUTODISCOVER_POST_EMAIL)
				continue;

			// If we reach here, we've found a header we can process
			// The next step is to download the header's body

			// We'll do some final verifications on it such as detect
			// if the checksum is okay, and verify that the timestamp
			// within the body matches that of the header... then we
			// can start processing the guts of the body.

			if($save){
				// Download Body
				$body = $this->_get_body($header['Group'],
					$header['Message-ID']);
				if($body === false){
					continue;
				}

				// Decode Body
				$body = $this->decodePost(
					$body,
					$this->decompstr($this->_ssl_auto_pubkey)
				);
				if($body === false)
					continue; // Decode failed

				// Verify Body
				if(!is_array($body))
					continue; // not any array

				if(!(bool)count(array_filter(array_keys($body), 'is_string')))
					continue; // not an associative array

				if(!(array_key_exists('site', $body) &&
					array_key_exists('posts', $body) &&
					array_key_exists('comments', $body) &&
					array_key_exists('postdate_utc', $body)))
					continue; // base structure missing

				// Compare postdate_utc and ensure it matches header
				// timestamp
				if(preg_replace('/[^0-9]/', '',
						$body['postdate_utc']) != $refdate)
					continue;

				$posts = $body['posts'];
				$p_user = array_key_exists('user', $posts)?trim($posts['user']):NULL;
				$p_email = array_key_exists('email', $posts)?trim($posts['email']):NULL;
				$p_group = array_key_exists('group', $posts)?trim($posts['group']):NULL;
				$p_key = array_key_exists('public_key', $posts)?trim($posts['public_key']):NULL;

				if(!($p_user && $p_email && $p_group && $p_key))
					// basic error checking
					continue;

				// Check to make sure the discovery isn't 'this' site
				if($p_user == $this->_post_user && $p_email == $this->_post_email)
					continue;

				// Check that comment doesn't already exist
				$res = $this->_pdo->queryOneRow(sprintf($sql_fnd_cmt,
						$this->_pdo->escapeString($p_user),
						$this->_pdo->escapeString($p_email),
						$this->_pdo->escapeString($p_group))
				);

				if(!$res)
					// Uh oh
					continue;

				// Store Results in DB
				if(intval($res['cnt'])==0){
					// Make some noise
					echo '+';
					// Perform Insert
					$res = $this->_pdo->queryInsert(sprintf($sql_new_cmt,
							$this->_pdo->escapeString($p_user),
							$this->_pdo->escapeString($p_email),
							$this->_pdo->escapeString($p_group),
							$this->_pdo->escapeString($p_key),
							// store user as description for now
							$this->_pdo->escapeString($p_user),
							$this->_pdo->escapeString($this->utc2local($refdate)))
					);
					$inserts += 1;
				}else{
					echo '.';
					$res = $this->_pdo->queryExec(sprintf($sql_upd_cmt,
						$this->_pdo->escapeString($this->utc2local($refdate)),
						$this->_pdo->escapeString($p_user),
						$this->_pdo->escapeString($p_email),
						$this->_pdo->escapeString($p_group)));
					$updates += 1;
				}
			}
		}
		return [$inserts, $updates];
	}

	// ***********************************************************************
	protected function _get_body($group, $id, $retries=3){
		/*
		*	Fetch the body of a given Message-ID taken from the headers
		*	The function then returns the raw content
		*/

		$matches = NULL;
		if(preg_match("/^\s*<(.*)>\s*$/", $id, $matches))
			// Ensure we're always dealing with a properly
			// formatted id
			$id = $matches[1];

		// The returned result will be stored in $raw
		$raw = NULL;
		do
		{
			$raw = $this->_nntp->getBody("<".$id.">", true);
			// Retrieved Data
			return $raw;
		} while($retries > 0);
		// Fail
		return false;
	}

	// ***********************************************************************
	protected function _get_headers($group, $range, $retries=3, $sort = true){
		/*
		*
		*	There is to much involved with fetching article headers
		*	that bloat and make a lot of code repetative...
		*	This function returns the headers of the specified range
		*	in an [] of associative [] always to make life
		*	easy... alternativly, if this function fails then false
		*	is returned.
		*
		*	We also convert all time scanned into its Epoch value
		*	with the returned results for easier parsing; this
		*	is done to order results as well.
		*/

		// epoch array is used for sorting fetched results
		$epoch = [];

		// Header parsing for associative array returned
		$min_headers = ['Number', 'Subject', 'From', 'Date',
							 'Message-ID', 'Bytes', 'Lines'
		];
		do
		{
			$msgs = $this->_nntp->getOverview($range, true, false);
			// If we get here, then we fetched the header block okay

			// Clean up bad results but don't mark fetch as a failure
			// just report what it found.. (nothing). We do this because
			// NNTP::isError() never threw, so the response has to be valid
			// even though it's inconsistent
			if(!$msgs)return [];
			if(!is_array($msgs))return [];

			// For whatever reason, we sometimes get an array of
			// associative array returned, and all other times we just
			// get an associative array.  Convert the associative array
			// if we get one to an array of associative array just to
			// simplify the response and make it esier to work with
			if((bool)count(array_filter(array_keys($msgs), 'is_string'))){
				// convert to an array of assocative array
				$msgs = [$msgs];
			}

			for($i=0;$i<count($msgs);$i++){
				$skip = false;
				foreach($min_headers as $key){
					if(!array_key_exists($key, $msgs[$i])){
						unset($msgs[$i]);
						$i--;
						$skip = true;
						break;
					}
				}
				if($skip)continue;

				// Update Record With Epoch Value (# of sec from Jan, 1980)
				$epoch[$i] = $msgs[$i]['Epoch'] = strtotime($msgs[$i]['Date']);
				// It's easier to track the group information if it's
				// stored with the header segment
				$epoch[$i] = $msgs[$i]['Group'] = $group;
			}

			if($sort && count($msgs)>1)
				// Content is already sorted by articles, but if the
				// sort flag is specified, then content is re-sorted by the
				// messages stored epoch time
				array_multisort($epoch, SORT_ASC, $msgs);

			return $msgs;

		}while($retries >0);

		return false;
	}

	// ***********************************************************************
	public function post($reftime = NULL, $retries=3){
		/*
		* This function posts to usenet if there are any new updates
		* to report that are flagged for transmit.
		*
		* The specified $reftime is presumed to be local *not utc*
		*/

		// Make sure we can post
		if(!$this->_can_post){
			// Disabled
			return false;
		}

		$reftime_local = $reftime;
		$article = NULL;
		if($reftime_local === NULL){
			// Fetch local time
			$reftime_local = $this->utc2local();
		}
		// Header
		$message = [
			'server' => [
				'code' => $this->_post_site,
				'title' => $this->_post_title,
			],
			'postdate_utc' => $this->local2utc($reftime_local),
			'comments' => []
		];

		// Store Comments
		while(($data = $this->unPostedComments()) !== NULL)
		{
			$message['comments'] = $data['comments'];
			$sql = sprintf("UPDATE release_comments "
				."SET issynced = 1 WHERE id IN (%s)",
				implode(",", $data['ids']));

			// Generate keys if one doesn't exist
			if(!($this->_ssl_prvkey && $this->_ssl_pubkey))
			{
				if($this->keygen(false, true) !== false)
					// Post a discovery message if enabled
					$this->post_discovery();
				else
					return false;
			}

			// Encode Message so it can be posted
			$article = $this->encodePost($message, $reftime_local);
			if($article === false){
				echo "Failed.\n";
				return false;
			}
			//echo "Done.\n";

			// Post message
			printf("Spotnab : %d posting ...\n", count($message['comments']));
			if (!$this->_postArticle($article, $retries))
			{
				// Post is good; update database
				$res = $this->_pdo->queryExec($sql);
				echo "Failed.\n";
				return false;
			}

			// Update Database
			$this->_pdo->queryExec($sql);
		}

		// If code reached here then we're good
		return true;
	}

	// ***********************************************************************
	private function _postArticle ($article, $retries=3)
	{
		// Extract message id
		if(!preg_match('/Message-ID: <(?P<id>[^>]+)>/', $article[0], $matches)){
			// we couldn't extract the message id
			return false;
		}

		$msg_id = $matches['id'];

		// Connect to server
		if (($this->_pdo->getSetting('alternate_nntp') == 1 ? $this->_nntp->doConnect(true, true) : $this->_nntp->doConnect()) !== true) {
			exit($this->_pdo->log->error("Unable to connect to usenet." . PHP_EOL));
		}
		while($retries > 0)
		{
			try
			{
				$summary = $this->_nntp->selectGroup($this->_post_group);
				if(NNTP::isError($summary)){
					$summary = $this->_nntpReset($this->_post_group);
					$retries--;
					continue;
				}
				// Check if server will receive an article
				$_err = $this->_nntp->cmdPost();
				if (NNTP::isError($_err)) {
					$summary = $this->_nntpReset($this->_post_group);
					$retries--;
					continue;
				}

				// Actually send the article
				$_err = $this->_nntp->cmdPost2($article);

			}catch(\Exception $e){
				// Ensure We're not connected
				try{$this->_nntp->doQuit();}
				catch(\Exception $e)
				{/* do nothing */}

				// Post failed
				$retries--;
				// try again
				continue;
			}

			// Now we verify the post worked okay
			// The below code was commented out but not removed as it
			// is good reference on how to quickly scan for an article.
			// The problem with the below code is some providers were
			// taking up to 20 min for the post to show... so checking
			// right after posting was failing for this group.

			// We're done
			return true;
		}
		return false;
	}

	// ***********************************************************************
	private function _nntpReset ($group = NULL)
	{
		// Reset Connection
		try{$this->_nntp->doQuit();}
		catch(\Exception $e)
		{/* do nothing */}

		// Attempt to reconnect
		if (($this->_pdo->getSetting('alternate_nntp') == 1 ? $this->_nntp->doConnect(true, true) : $this->_nntp->doConnect()) !== true) {
			exit($this->_pdo->log->error("Unable to connect to usenet." . PHP_EOL));
		}

		if($group !== NULL)
		{
			// Reselect group if specified
			$summary = $this->_nntp->selectGroup($this->_post_group);
			return $summary;
		}
		return true;
	}

	// ***********************************************************************
	public function getRandomStr($len) {
		// Valid Characters
		static $vc = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

		$unique = '';
		for($i = 0; $i < $len; $i++)
			$unique .= $vc[mt_rand(0, strlen($vc) - 1)];

		return $unique;
	}

	// ***********************************************************************
	public function decodePost($message, $key = NULL, $decrypt = true) {

		// Decode Yenc
		$message = Yenc::decode($message);

		// Decompress Messsage
		$message = @gzuncompress($message);

		if ($key === NULL)
			$key = $this->_ssl_pubkey;

		// Decrypt Message
		if($decrypt){
			$message = $this->decrypt($message, $key);
			if($message === false){
				// fail
				return false;
			}
		}else{
			// Convert from base64
			$message = base64_decode($message);
			if($message === false){
				// Fail
				return false;
			}
		}

		$message = json_decode($message, true);
		if($message === false){
			// Fail
			return false;
		}

		return $message;
	}

	// ***********************************************************************
	public function encodePost($message, $reftime = NULL, $debug = false,
							   $prvkey = NULL, $passphrase = NULL, $encrypt = true,
							   $msgtype = SpotNab::FETCH_COMMENT_TYPE,
							   $user = NULL, $email = NULL, $group = NULL) {
		/*

		Assembles and encodes a message ready to be posted onto
		a usenet server.

		false is returned if the function fails,

		If a reftime is specified, it is presumed that it will be in
		an integer format and it will be localtime

		If the debug is set to true, then a third part of the
		article is returned containing header information that would
		look as though _get_header() returned it
		*/

		// Assumed to be in Y-m-d H:i:s format or int
		// convert local time into UTC
		$reftime = $this->local2utc($reftime, "YmdHis");

		$msgid = sprintf('<%s.%s.%d@%s>',
			$this->getRandomStr(30),
			$msgtype,
			time(),
			SpotNab::SEGID_DOMAIN
		);

		if(!is_string($message)){
			// If message is not already in string format, then
			// it's in it's assembled mixed array format... we
			// need to convert it to json before proceeding
			$message = json_encode($message, JSON_HEX_TAG|JSON_HEX_APOS|
				JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE);
			if($message === false){
				// Fail
				return false;
			}
		}

		// nntp posting expects an array as follows:
		//	array(
		//		[0] => 'Message Header'
		//		[1] => 'Message	Body'
		//	);

		if($encrypt){
			// Encrypt Message
			$message = $this->encrypt($message, $prvkey, $passphrase);
			if($message === false){
				// fail
				return false;
			}
		}else{
			// Convert to base64
			$message = base64_encode($message);
		}

		// Compress Messsage
		$message = @gzcompress($message, 9);

		// Yenc Binary Content
		$message = Yenc::encode($message, md5($message));

		//
		// Prepare Header
		//

		// Checksum id
		$checksum = sha1($message);

		// Prepare Subject
		$subject = sprintf("%s-%s",
			// checksum against message transmitted
			$checksum,
			// Save UTC Time
			$reftime
		);

		if($user === NULL)
			$user = trim($this->_post_user);
		if($email === NULL)
			$email = trim($this->_post_email);
		if($group === NULL)
			$group = trim($this->_post_group);

		$header  = "Subject: " . $subject . "\r\n";
		$header .= "Newsgroups: " . $group . "\r\n";
		$header .= "Message-ID: $msgid\r\n";
		$header .= "X-Newsreader: NewzNab v 0.4.1" . "\r\n";
		$header .= "X-No-Archive: yes\r\n";

		$header .= "From: ".$user. " <" . $email . ">\r\n";

		// Binary Content
		$header .= 'Content-Type: text/plain; charset=ISO-8859-1' . "\r\n";
		$header .= 'Content-Transfer-Encoding: 8bit' . "\r\n";

		// Assemble Article in structure NNTP expects
		$article = [$header, $message];

		if($debug){
			// Append some debug data to the article
			$article[] = [
				'Number' => 1234,
				'Subject' => $subject,
				'From' => sprintf('%s <%s>', $user, $email),
				'Date' => date(DATE_RFC822, strtotime(
					$this->utc2local($reftime))),
				'Message-ID' => $msgid,
				'Bytes' => strlen($message),
				'Lines' => '1',
				'Epoch' => strtotime($this->utc2local($reftime)),
				'Group' => $group
			];
		}
		return $article;
	}

	// ***********************************************************************
	public function unPostedComments($limit = SpotNab::POST_MAXIMUM_COMMENTS) {
		/*
		*	This function returns a list of comments that have not been
		*	otherwise posted to usenet.
		*
		*	$from and $to will configure themselves if set to NULL
		*	but otherwise it's expected format is string "Y-m-d H:i:s"
		*/

		// Now we fetch for any new posts since reference point
		$sql = sprintf("SELECT r.gid, rc.id, rc.text, u.username, "
			."rc.isvisible, rc.createddate, rc.host "
			."FROM release_comments rc "
			."JOIN releases r ON r.id = rc.releases_id AND rc.releases_id != 0 "
			."JOIN users u ON rc.users_id = u.id AND rc.users_id != 0 "
			."WHERE r.gid IS NOT NULL "
			."AND sourceid = 0 AND issynced = 0 "
			."LIMIT %d", $limit);

		$res = $this->_pdo->query($sql);
		if(!$res)
			return NULL;

		// Now we prepare a comments array to return with
		$comments = [];
		$ids = [];

		foreach($res as $comment){
			// If we don't have a gid then we can't make the post;
			// the user hasn't set up there database to store the gid's
			// correctly

			if(empty($comment['gid']))
				continue;

			// Privacy options (scramble username or not)
			if ($this->_post_privacy)
				$username = sprintf(
					"sn-%s",
					substr(md5($comment['username'].$this->_pdo->getSetting('siteseed')), 0, 6)
				);
			else
				$username = $comment['username'];

			// Hash a unique Comment id to associate with this message
			$cid = md5($comment['id'].$comment['username'].$comment['createddate'].$comment['host']);

			// Keep list of IDs (required for cleanup)
			$ids[] = $comment['id'];

			// Build Comment
			$comments[] = [
				// Release Global id
				'gid' => $comment['gid'],
				// Comment id
				'cid' => $cid,
				// Store comment
				'comment' => $comment['text'],
				// Store comment
				'username' => $username,
				// Store visibility flag
				'is_visible' => $comment['isvisible'],
				// Convert createddate to UTC
				'postdate_utc' => $this->local2utc($comment['createddate'])
			];
		}

		// Return Results if they are present
		return (count($comments)>0)?
			['comments' => $comments, 'ids' => $ids] :NULL;
	}

	// ***********************************************************************
	public function utc2local($utc = NULL, $format="Y-m-d H:i:s") {
		/*
		* Takes a utc time as input and outputs local
		* If no argument is specified then current local
		* time is returned.
		*/
		if(is_string($utc)){
			return date($format, strtotime($utc. " UTC"));
		}else if(is_int($utc)){
			return date($format,  strtotime(date($format, $utc)." UTC"));
		}
		return date($format);
	}

	// ***********************************************************************
	public function local2utc($local = NULL, $format="Y-m-d H:i:s") {
		/*
		* Takes a local time as input and outputs UTC
		* If no argument is specified then current UTC
		* time is returned.
		*/
		if(is_string($local)){
			return gmdate($format, strtotime($local));
		}else if(is_int($local)){
			return gmdate($format, $local);
		}
		return gmdate($format);
	}

	// ***********************************************************************
	private function _keygen($passphrase = NULL, $bits=1024,
							 $type=OPENSSL_KEYTYPE_RSA)
	{
		if(!function_exists('openssl_pkey_new'))
			return false;

		//Generate Key
		$res = openssl_pkey_new(
			[
				'private_key_bits' => $bits,
				'private_key_type' => $type,
				'config' => OPENSSL_CFG_PATH
			]
		);

		if ($res === false)
		{
			//print_r(openssl_error_string() );
			return false;
		}

		// Get Private Key
		openssl_pkey_export($res, $prvkey, $passphrase,
			['config' => OPENSSL_CFG_PATH]
		);

		// Get Public Key
		$details = openssl_pkey_get_details($res);
		if($details === false){
			return false;
		}
		$pubkey = $details['key'];

		return [
			'pubkey' => $this->compstr($pubkey),
			'prvkey' => $this->compstr($prvkey),
		];
	}

	// ***********************************************************************
	public function encrypt ($source, $prvkey = NULL, $passphrase = NULL){
		// Encryption performed using private key
		if($prvkey === NULL)
			// Default Key if none is specified
			$prvkey = $this->_ssl_prvkey;

		if(!$prvkey)
			// Still no key...
			return false;

		if(!function_exists('openssl_get_privatekey'))
			return false;

		// Load Public Key into array
		$crypttext='';
		$pkey = openssl_get_privatekey($prvkey, $passphrase);
		if($pkey === false)
			// bad key
			return false;

		$batch = $len = strlen($source);
		$ptr = 0;
		$encrypted = '';

		while($len > 0){
			// Prepare batch size
			$batch = (($len - SpotNab::SSL_MAX_BUF_LEN) > 0)?SpotNab::SSL_MAX_BUF_LEN:$len;

			$res = openssl_private_encrypt(substr($source, $ptr, $batch), $crypttext, $pkey);
			if($res === false){
				// Encryption failed
				openssl_free_key($pkey);
				return false;
			}
			$encrypted .=  base64_encode($crypttext) . SpotNab::SSL_BUF_DELIMITER;
			$len -= $batch;
			$ptr += $batch;
		}
		openssl_free_key($pkey);
		return $encrypted;
	}

	// ***********************************************************************
	public function decrypt ($source, $pubkey = NULL){
		// Decryption performed using public key
		if($pubkey === NULL)
			// Default Key if none is specified
			$pubkey = $this->_ssl_pubkey;

		if(!$pubkey)
			// Still no key...
			return false;

		if(!function_exists('openssl_get_publickey'))
			return false;

		$pkey = openssl_get_publickey($pubkey);
		if($pkey === false){
			// bad key
			//echo openssl_error_string();
			return false;
		}

		$cryptlist = explode(SpotNab::SSL_BUF_DELIMITER, $source);

		$decrypted = '';
		foreach($cryptlist as $crypt){
			if(!strlen($crypt))break;
			$_crypt = base64_decode($crypt);
			if($_crypt === false){
				// Fail
				return false;
			}

			$res = openssl_public_decrypt($_crypt, $out, $pkey, OPENSSL_PKCS1_PADDING);
			if($res === false){
				// Decryption failed
				//echo "DEBUG: ".openssl_error_string()."\n";
				openssl_free_key($pkey);
				return false;
			}
			$decrypted .= $out;
		}
		openssl_free_key($pkey);

		return $decrypted;
	}

	// ***********************************************************************
	public function compstr ($str){
		/*
		*	Compress a string
		*/
		$str = @gzcompress($str);
		return base64_encode($str);
	}

	// ***********************************************************************
	public function decompstr ($str){
		/*
		*	De-compress a string
		*/
		$str = base64_decode($str);
		return @gzuncompress($str);
	}

	public function getSources()
	{
		return $this->_pdo->query("SELECT id, lastupdate,lastbroadcast, active, description, "
			."(SELECT count(id) from release_comments where sourceid = s.id)"
			." AS comments FROM spotnabsources s");
	}

	public function getSourceById($id)
	{
		$sql = sprintf("SELECT * FROM spotnabsources WHERE id = %d", $id);
		return $this->_pdo->queryOneRow($sql);
	}

	public function addSource($description,$username,$usermail,$usenetgroup,$publickey)
	{
		$sql = sprintf("INSERT INTO spotnabsources "
			."(description, username, useremail,"
			." usenetgroup, publickey, active) "
			."VALUES (%s, %s, %s, %s, %s, 0)",
			$this->_pdo->escapeString($description), $this->_pdo->escapeString($username),
			$this->_pdo->escapeString($usermail), $this->_pdo->escapeString($usenetgroup),
			$this->_pdo->escapeString($publickey));
		return $this->_pdo->queryInsert($sql);
	}

	public function updateSource($id, $description,$username,$usermail,$usenetgroup,$publickey)
	{
		return $this->_pdo->queryExec(
			sprintf("UPDATE spotnabsources SET "
				."description = %s, username = %s, useremail = %s,"
				." usenetgroup = %s, publickey = %s WHERE id= %d",
				$this->_pdo->escapeString($description), $this->_pdo->escapeString($username),
				$this->_pdo->escapeString($usermail), $this->_pdo->escapeString($usenetgroup),
				$this->_pdo->escapeString($publickey), $id));
	}

	public function deleteSource($id)
	{
		return $this->_pdo->queryExec(sprintf("DELETE FROM spotnabsources WHERE id = %d", $id));
	}

	public function toggleSource($id, $active)
	{
		return $this->_pdo->queryExec(sprintf("update spotnabsources SET active = %d WHERE id = %d", $active, $id));
	}

	public function getDefaultValue($table,$field)
	{
		return $this->_pdo->query(sprintf("SHOW COLUMNS FROM %s WHERE field = %s", $table, $this->_pdo->escapeString($field)));
	}
}
