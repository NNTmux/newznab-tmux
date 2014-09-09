<?php
require_once(WWW_DIR . "/lib/framework/db.php");
require_once(WWW_DIR . "/lib/site.php");
require_once(WWW_DIR . "/lib/Tmux.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "/lib/rarinfo/par2info.php");
require_once(WWW_DIR . "/lib/rarinfo/sfvinfo.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");
require_once("Pprocess.php");
require_once("Film.php");
require_once("TvAnger.php");
require_once("getid3/getid3/getid3.php");

/**
 * Class Nfo
 * Class for handling fetching/storing of NFO files.
 */
class Info
{
	/**
	 * Site settings.
	 *
	 * @var bool|stdClass
	 * @access private
	 */
	private $site;

	/**
	 * How many nfo's to process per run.
	 *
	 * @var int
	 */
	private $nzbs;

	/**
	 * Max NFO size to process.
	 *
	 * @var int
	 */
	private $maxsize;

	/**
	 * Path to temporarily store files.
	 *
	 * @var string
	 */
	private $tmpPath;

	/**
	 * Instance of class ColorCLI
	 *
	 * @var ColorCLI
	 */
	private $c;

	/**
	 * Instance of class DB
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Primary color for console text output.
	 *
	 * @var string
	 */
	private $primary = 'Green';

	/**
	 * Color for warnings on console text output.
	 *
	 * @var string
	 */
	private $warning = 'Red';

	/**
	 * Color for headers(?) on console text output.
	 *
	 * @var string
	 */
	private $header = 'Yellow';

	/**
	 * Echo to cli?
	 *
	 * @var bool
	 */
	protected $echo;

	const NFO_UNPROC = -1; // Release has not been processed yet.
	const NFO_NONFO = 0; // Release has no NFO.
	const NFO_FOUND = 1; // Release has an NFO.

	/**
	 * Default constructor.
	 *
	 * @param bool $echo Echo to cli.
	 *
	 * @access public
	 */
	public function __construct($echo = false)
	{
		$this->echo = $echo;
		$s = new Sites();
		$this->c = new ColorCLI();
		$this->db = new DB();
		$this->site = $s->get();
		$t = new Tmux();
		$this->tmux = $t->get();
		$this->nzbs = (!empty($this->tmux->maxnfoprocessed)) ? $this->tmux->maxnfoprocessed : 100;
		$this->maxsize = (!empty($this->tmux->maxsizetopostprocess)) ? $this->tmux->maxsizetopostprocess : 100;
		$this->tmpPath = $this->site->tmpunrarpath;
		if (!preg_match('/[\/\\\\]$/', $this->tmpPath)) {
			$this->tmpPath .= '/';
		}
		$this->util = new Utility();
	}

	/**
	 * Look for a TvRage ID in a string.
	 *
	 * @param string $str The string with a TvRage ID.
	 *
	 * @return string The TVRage ID on success.
	 * @return bool   False on failure.
	 *
	 * @access public
	 */
	public function parseRageId($str)
	{
		if (preg_match('/tvrage\.com\/shows\/id-(\d{1,6})/i', $str, $matches)) {
			return trim($matches[1]);
		}

		return false;
	}

	/**
	 * Confirm this is an NFO file.
	 *
	 * @param string $possibleNFO The nfo.
	 * @param string $guid        The guid of the release.
	 *
	 * @return bool               True on success, False on failure.
	 *
	 * @access public
	 */
	public function isNFO(&$possibleNFO, $guid)
	{
		if ($possibleNFO === false) {
			return false;
		}

		// Make sure it's not too big or small, size needs to be at least 12 bytes for header checking. Ignore common file types.
		$size = strlen($possibleNFO);
		if ($size < 65535 &&
			$size > 11 &&
			!preg_match(
				'/\A(\s*<\?xml|=newz\[NZB\]=|RIFF|\s*[RP]AR|.{0,10}(JFIF|matroska|ftyp|ID3))|;\s*Generated\s*by.*SF\w/i'
				, $possibleNFO
			)
		) {
			// File/GetId3 work with files, so save to disk.
			$tmpPath = $this->tmpPath . $guid . '.nfo';
			file_put_contents($tmpPath, $possibleNFO);

			// Linux boxes have 'file' (so should Macs)
			if ($this->util->hasCommand('file')) {
				exec('file -b "' . $tmpPath . '"', $result);
				if (is_array($result)) {
					if (count($result) > 1) {
						$result = implode(',', $result[0]);
					} else {
						$result = $result[0];
					}
				}
				// Check if it's text.
				if (preg_match('/(ASCII|ISO-8859|UTF-(8|16|32).*?)\s*text/', $result)) {
					@unlink($tmpPath);

					return true;

					// Or binary.
				} else if (preg_match('/^(JPE?G|Parity|PNG|RAR|XML|(7-)?[Zz]ip)/', $result) ||
					preg_match('/[\x00-\x08\x12-\x1F\x0B\x0E\x0F]/', $possibleNFO)
				) {
					@unlink($tmpPath);

					return false;
				}
			}

			// If above checks couldn't  make a categorical identification, Use GetId3 to check if it's an image/video/rar/zip etc..
			require_once("getid3/getid3/getid3.php");
			$getid3 = new getid3();
			$check = $getid3->analyze($tmpPath);
			@unlink($tmpPath);
			if (isset($check['error'])) {
				// Check if it's a par2.
				$par2info = new Par2Info();
				$par2info->setData($possibleNFO);
				if ($par2info->error) {
					// Check if it's an SFV.
					$sfv = new SfvInfo();
					$sfv->setData($possibleNFO);
					if ($sfv->error) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Add an NFO from alternate sources. ex.: PreDB, rar, zip, etc...
	 *
	 * @param string $nfo     The nfo.
	 * @param array  $release The SQL row for this release.
	 * @param object $nntp    Instance of class NNTP.
	 *
	 * @return bool           True on success, False on failure.
	 *
	 */
	public function addAlternateNfo(&$nfo, $release, $nntp)
	{
		if ($release['ID'] > 0 && $this->isNFO($nfo, $release['guid'])) {

			$check = $this->db->queryOneRow(sprintf('SELECT ID FROM releasenfo WHERE releaseID = %d', $release['ID']));

			if ($check === false) {
				$this->db->queryInsert(
					sprintf('INSERT INTO releasenfo (nfo, releaseID) VALUES (compress(%s), %d)',
						$this->db->escapeString($nfo),
						$release['ID']
					)
				);
			}

			$this->db->exec(sprintf('UPDATE releases SET releasenfoID = 1, nfostatus = %d WHERE ID = %d', self::NFO_FOUND, $release['ID']));

			if (!isset($release['completion'])) {
				$release['completion'] = 0;
			}

			if ($release['completion'] == 0) {
				$nzbContents = new NZBContents(
					array(
						'echo' => $this->echo,
						'nntp' => $nntp,
						'nfo'  => $this,
						'db'   => $this->db,
						'pp'   => new PProcess(true)
					)
				);
				$nzbContents->parseNZB($release['guid'], $release['ID'], $release['groupID']);
			}

			return true;
		}

		return false;
	}

	/**
	 * Attempt to find NFO files inside the NZB's of releases.
	 *
	 * @param string $releaseToWork
	 * @param int    $processImdb   Attempt to find IMDB id's in the NZB?
	 * @param int    $processTvrage Attempt to find TvRage id's in the NZB?
	 * @param string $groupID       (optional) The group ID to work on.
	 * @param object $nntp          Instance of class NNTP.
	 *
	 * @return int                   How many NFO's were processed?
	 *
	 */
	public function processNfoFiles($releaseToWork = '', $processImdb = 1, $processTvrage = 1, $groupID = '', $nntp)
	{
		if (!isset($nntp)) {
			exit($this->c->error("Unable to connect to usenet.\n"));
		}

		$nfoCount = $ret = 0;
		$groupID = ($groupID === '' ? '' : 'AND groupID = ' . $groupID);
		$res = array();

		if ($releaseToWork === '') {
			$i = -1;
			while (($nfoCount != $this->nzbs) && ($i >= -6)) {
				$res += $this->db->query(
					sprintf('
						SELECT ID, guid, groupID, name
						FROM releases
						WHERE nfostatus BETWEEN %d AND %d
						AND size < %s
						%s
						LIMIT %d',
						$i,
						self::NFO_UNPROC,
						$this->maxsize * 1073741824,
						$groupID,
						$this->nzbs
					)
				);
				$nfoCount += count($res);
				$i--;
			}
		} else {
			$pieces = explode('           =+=            ', $releaseToWork);
			$res = array(array('ID' => $pieces[0], 'guid' => $pieces[1], 'groupID' => $pieces[2], 'name' => $pieces[3]));
			$nfoCount = 1;
		}

		if ($nfoCount > 0) {
			if ($releaseToWork === '') {
				$this->c->doEcho(
					$this->c->primary(
						'Processing ' . $nfoCount .
						' NFO(s), starting at ' . $this->nzbs .
						' * = hidden NFO, + = NFO, - = no NFO, f = download failed.'
					)
				);

				// Get count of releases per nfo status
				$outString = 'Available to process';
				for ($i = -1; $i >= -6; $i--) {
					$ns = $this->db->query('SELECT COUNT(*) AS count FROM releases WHERE nfostatus = ' . $i);
					$outString .= ', ' . $i . ' = ' . number_format($ns[0]['count']);
				}
				$this->c->doEcho($this->c->header($outString . '.'));
			}
			$groups = new Groups();
			$nzbContents = new NZBContents(array('echo' => $this->echo, 'nntp' => $nntp, 'nfo' => $this, 'db' => $this->db, 'pp' => new PProcess(true)));
			$movie = new Film($this->echo);
			$tvRage = new TvAnger($this->echo);

			foreach ($res as $arr) {
				$fetchedBinary = $nzbContents->getNFOfromNZB($arr['guid'], $arr['ID'], $arr['groupID'], $groups->getByNameByID($arr['groupID']));
				if ($fetchedBinary !== false) {
					// Insert nfo into database.
					$cp = $nc = null;
					if (DB_TYPE === 'mysql') {
						$cp = 'COMPRESS(%s)';
						$nc = $this->db->escapeString($fetchedBinary);
					} else if (DB_TYPE === 'pgsql') {
						$cp = '%s';
						$nc = $this->db->escapeString(utf8_encode($fetchedBinary));
					}
					$ckreleaseid = $this->db->queryOneRow(sprintf('SELECT ID FROM releasenfo WHERE releaseID = %d', $arr['ID']));
					if (!isset($ckreleaseid['ID'])) {
						$this->db->queryInsert(sprintf('INSERT INTO releasenfo (nfo, releaseID) VALUES (' . $cp . ', %d)', $nc, $arr['ID']));
					}
					$this->db->exec(sprintf('UPDATE releases SET releasenfoID = 1, nfostatus = %d WHERE ID = %d', self::NFO_FOUND, $arr['ID']));
					$ret++;
					$movie->doMovieUpdate($fetchedBinary, 'nfo', $arr['ID'], $processImdb);

					// If set scan for tvrage info.
					if ($processTvrage == 1) {
						$rageId = $this->parseRageId($fetchedBinary);
						if ($rageId !== false) {
							$show = $tvRage->parseNameEpSeason($arr['name']);
							if (is_array($show) && $show['name'] != '') {
								// Update release with season, ep, and air date info (if available) from release title.
								$tvRage->updateEpInfo($show, $arr['ID']);

								$rid = $tvRage->getByRageID($rageId);
								if (!$rid) {
									$tvrShow = $tvRage->getRageInfoFromService($rageId);
									$tvRage->updateRageInfo($rageId, $show, $tvrShow, $arr['ID']);
								}
							}
						}
					}
				}
			}
		}

		// Remove nfo that we cant fetch after 5 attempts.
		if ($releaseToWork === '') {
			$relres = $this->db->query('SELECT ID FROM releases WHERE nfostatus < -6 AND releasenfoID = -1');
			foreach ($relres as $relrow) {
				$this->db->exec(sprintf('DELETE FROM releasenfo WHERE nfo IS NULL AND releaseID = %d', $relrow['ID']));
			}

			if ($this->echo) {
				if ($nfoCount > 0 && $releaseToWork === '') {
					echo "\n";
				}
				if ($ret > 0 && $releaseToWork === '') {
					$this->c->doEcho($ret . ' NFO file(s) found/processed.', true);
				}
			}
		}

		return $ret;
	}

}