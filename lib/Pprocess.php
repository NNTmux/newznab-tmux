<?php
require_once(WWW_DIR . "lib/rarinfo/par2info.php");
require_once(WWW_DIR . "lib/rarinfo/archiveinfo.php");
require_once(WWW_DIR . "lib/rarinfo/zipinfo.php");
require_once(WWW_DIR . "lib/framework/db.php");
require_once(WWW_DIR . "lib/category.php");
require_once(WWW_DIR . "lib/releases.php");
require_once(WWW_DIR . "lib/releaseimage.php");
require_once(WWW_DIR . "lib/releaseextra.php");
require_once(WWW_DIR . "lib/groups.php");
require_once(WWW_DIR . 'lib/nntp.php');
require_once(WWW_DIR . 'lib/music.php');
require_once(WWW_DIR . 'lib/Musik.php');
require_once(WWW_DIR . "lib/site.php");
require_once(WWW_DIR . "lib/Tmux.php");
require_once(WWW_DIR . "lib/amazon.php");
require_once(WWW_DIR . "lib/genres.php");
require_once(WWW_DIR . "lib/anidb.php");
require_once(WWW_DIR . "lib/book.php");
require_once(WWW_DIR.  "lib/Books.php");
require_once(WWW_DIR . "lib/Games.php");
require_once(WWW_DIR . "lib/spotnab.php");
require_once(WWW_DIR . "lib/thetvdb.php");
require_once(WWW_DIR . "lib/XXX.php");
require_once(WWW_DIR . "lib/Logger.php");
require_once(WWW_DIR . "/lib/ConsoleTools.php");
require_once(WWW_DIR . "/lib/ColorCLI.php");
require_once(WWW_DIR . "/processing/post/AniDB.php");
require_once("nzbcontents.php");
require_once("namefixer.php");
require_once("Info.php");
require_once("prehash.php");
require_once("Sharing.php");
require_once("TraktTv.php");
require_once("Film.php");
require_once("TvAnger.php");
require_once("Konsole.php");
require_once("ProcessAdditional.php");

class PProcess
{
	/**
	 * @var Settings
	 */
	public $pdo;

	/**
	 * Class instance of debugging.
	 *
	 * @var Logger
	 */
	protected $debugging;

	/**
	 * Instance of NameFixer.
	 * @var NameFixer
	 */
	protected $nameFixer;

	/**
	 * @var Par2Info
	 */
	protected $_par2Info;

	/**
	 * Use alternate NNTP provider when download fails?
	 * @var bool
	 */
	private $alternateNNTP;

	/**
	 * Add par2 info to rar list?
	 * @var bool
	 */
	private $addpar2;

	/**
	 * Should we echo to CLI?
	 * @var bool
	 */
	private $echooutput;

	/**
	 * @var Groups
	 */
	private $groups;

	/**
	 * @var Nfo
	 */
	private $Nfo;

	/**
	 * @var ReleaseFiles
	 */
	private $releaseFiles;

	/**
	 * Constructor.
	 *
	 * @param array $options Pass in class instances.
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Echo'         => true,
			'Logger'       => null,
			'Groups'       => null,
			'NameFixer'    => null,
			'Nfo'          => null,
			'ReleaseFiles' => null,
			'Settings'     => null,
		];
		$options += $defaults;

		//\\ Various.
		$this->echooutput = ($options['Echo'] && NN_ECHOCLI);
		//\\
		$s = new Sites();
		$this->site = $s->get();
		//\\ Class instances.
		$this->pdo = (($options['Settings'] instanceof DB) ? $options['Settings'] : new DB());
		$this->groups = (($options['Groups'] instanceof Groups) ? $options['Groups'] : new Groups());
		$this->_par2Info = new Par2Info();
		$this->debugging = ($options['Logger'] instanceof Logger ? $options['Logger'] : new Logger(['ColorCLI' => $this->pdo->log]));
		$this->nameFixer = (($options['NameFixer'] instanceof NameFixer) ? $options['NameFixer'] : new NameFixer(['Echo' => $this->echooutput, 'Settings' => $this->pdo, 'Groups' => $this->groups]));
		$this->Nfo = (($options['Nfo'] instanceof Info ) ? $options['Nfo'] : new Info(['Echo' => $this->echooutput, 'Settings' => $this->pdo]));
		$this->releaseFiles = (($options['ReleaseFiles'] instanceof ReleaseFiles) ? $options['ReleaseFiles'] : new ReleaseFiles($this->pdo));
		//\\

		//\\ Site settings.
		$this->addpar2 = ($this->site->addpar2 == 0) ? false : true;
		$this->alternateNNTP = ($this->site->alternate_nntp == 1 ? true : false);
		//\\
	}

	/**
	 * Go through every type of post proc.
	 *
	 * @param $nntp
	 *
	 * @return void
	 */
	public function processAll($nntp)
	{
		$this->processAdditional($nntp);
		$this->processNfos($nntp);
		$this->processSpotnab($nntp);
		$this->processSharing($nntp);
		$this->processMovies();
		$this->processMusic();
		$this->processConsoles();
		$this->processGames();
		$this->processAnime();
		$this->processTv();
		$this->processTvDB();
		$this->processXXX();
		$this->processBooks();
	}

	/**
	 * Lookup anidb if enabled - always run before tvrage.
	 *
	 * @return void
	 */
	public function processAnime()
	{
		if ($this->site->lookupanidb != 0) {
			(new \PostAniDB(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processAnimeReleases();
		}
	}

	/**
	 * Process books using amazon.com.
	 *
	 * @return void
	 */
	public function processBooks()
	{
		if ($this->site->lookupbooks != 0) {
			(new \Books(['Echo' => $this->echooutput, 'Settings' => $this->pdo, ]))->processBookReleases();
		}
	}

	/**
	 * Lookup console games if enabled.
	 *
	 * @return void
	 */
	public function processConsoles()
	{
		if ($this->site->lookupgames != 0) {
			(new \Konsole(['Settings' => $this->pdo, 'Echo' => $this->echooutput]))->processConsoleReleases();
		}
	}

	/**
	 * Lookup games if enabled.
	 *
	 * @return void
	 */
	public function processGames()
	{
		if ($this->site->lookupgames != 0) {
			(new Games(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processGamesReleases();
		}
	}

	/**
	 * Lookup imdb if enabled.
	 *
	 * @param string     $groupID       (Optional) ID of a group to work on.
	 * @param string     $guidChar      (Optional) First letter of a release GUID to use to get work.
	 * @param int|string $processMovies (Optional) 0 Don't process, 1 process all releases,
	 *                                             2 process renamed releases only, '' check site setting
	 *
	 * @return void
	 */
	public function processMovies($groupID = '', $guidChar = '', $processMovies = '')
	{
		$processMovies = (is_numeric($processMovies) ? $processMovies : $this->site->lookupimdb);
		if ($processMovies > 0) {
			(new Film(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processMovieReleases($groupID, $guidChar, $processMovies);
		}
	}

	/**
	 * Lookup music if enabled.
	 *
	 * @return void
	 */
	public function processMusic()
	{
		if ($this->site->lookupmusic != 0) {
			(new \Musik(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processMusicReleases();
		}
	}

	/**
	 * Process nfo files.
	 *
	 * @param NNTP   $nntp
	 * @param string $groupID  (Optional) ID of a group to work on.
	 * @param string $guidChar (Optional) First letter of a release GUID to use to get work.
	 *
	 * @return void
	 */
	public function processNfos(&$nntp, $groupID = '', $guidChar = '')
	{
		if ($this->site->lookupnfo == 1) {
			$this->Nfo->processNfoFiles($nntp, $groupID, $guidChar, (int)$this->site->lookupimdb, (int)$this->site->lookuptvrage);
		}
	}

	/**
	 * Process Global IDs
	 */
	public function processSpotnab(&$nntp)
	{
		$spotnab = new SpotNab();
		$processed = $spotnab->processGID(500);
		if ($processed > 0) {
			if ($this->echooutput) {
				$this->pdo->log->doEcho(
					$this->pdo->log->primary('Updating GID in releases table ' . $processed . ' release(s) updated')
				);
			}
		}
		$spotnab->auto_post_discovery();
		$spotnab->fetch_discovery();
		$spotnab->fetch();
		$spotnab->post();
		$spotnab->auto_clean();
	}

	/**
	 * Process comments.
	 *
	 * @param NNTP $nntp
	 */
	public function processSharing(&$nntp)
	{
		(new Sharing())->start();
	}

	/**
	 * Process all TV related releases which will assign their series/episode/rage data.
	 *
	 * @param string     $groupID   (Optional) ID of a group to work on.
	 * @param string     $guidChar  (Optional) First letter of a release GUID to use to get work.
	 * @param string|int $processTV (Optional) 0 Don't process, 1 process all releases,
	 *                                         2 process renamed releases only, '' check site setting
	 *
	 * @return void
	 */
	public function processTv($groupID = '', $guidChar = '', $processTV = '')
	{
		$processTV = (is_numeric($processTV) ? $processTV : $this->site->lookuptvrage);
		if ($processTV > 0) {
			(new TvAnger(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processTvReleases($groupID, $guidChar, $processTV);
		}
	}

	public function processTvDB()
	{
		if ($this->site->lookupthetvdb == 1)
		{
			$thetvdb = new TheTVDB($this->echooutput);
			$thetvdb->processReleases();
		}
	}

	/**
	 * Lookup xxx if enabled.
	 */
	public function processXXX()
	{
		if ($this->site->lookupxxx == 1) {
			(new XXX(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processXXXReleases();
		}
	}

	/**
	 * Check for passworded releases, RAR/ZIP contents and Sample/Media info.
	 *
	 * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
	 *
	 * @param NNTP       $nntp    Class NNTP
	 * @param int|string $groupID  (Optional) ID of a group to work on.
	 * @param string     $guidChar (Optional) First char of release GUID, can be used to select work.
	 *
	 * @return void
	 */
	public function processAdditional(&$nntp, $groupID = '', $guidChar = '')
	{
		(new ProcessAdditional(['Echo' => $this->echooutput, 'NNTP' => $nntp, 'Settings' => $this->pdo, 'Groups' => $this->groups, 'NameFixer' => $this->nameFixer, 'Nfo' => $this->Nfo, 'ReleaseFiles' => $this->releaseFiles]))->start($groupID, $guidChar);
	}

	/**
	 * Attempt to get a better name from a par2 file and categorize the release.
	 *
	 * @note Called from NZBContents.php
	 *
	 * @param string $messageID MessageID from NZB file.
	 * @param int    $relID     ID of the release.
	 * @param int    $groupID   Group ID of the release.
	 * @param NNTP   $nntp      Class NNTP
	 * @param int    $show      Only show result or apply iy.
	 *
	 * @return bool
	 */
	public function parsePAR2($messageID, $relID, $groupID, &$nntp, $show)
	{
		if ($messageID === '') {
			return false;
		}

		$query = $this->pdo->queryOneRow(
			sprintf('
				SELECT ID, groupID, categoryID, name, searchname, UNIX_TIMESTAMP(postdate) AS post_date, ID AS releaseID
				FROM releases
				WHERE isrenamed = 0
				AND ID = %d',
				$relID
			)
		);

		if ($query === false) {
			return false;
		}

		// Only get a new name if the category is OTHER.
		$foundName = true;
		if (!in_array(
			(int)$query['categoryID'],
			array(
				Category::CAT_BOOK_OTHER,
				Category::CAT_GAME_OTHER,
				Category::CAT_MOVIE_OTHER,
				Category::CAT_MUSIC_OTHER,
				Category::CAT_PC_MOBILEOTHER,
				Category::CAT_TV_OTHER,
				Category::CAT_MISC_HASHED,
				Category::CAT_XXX_OTHER,
				Category::CAT_MISC_OTHER
			)
		)
		) {
			$foundName = false;
		}

		// Get the PAR2 file.
		$par2 = $nntp->getMessages($this->groups->getByNameByID($groupID), $messageID, $this->alternateNNTP);
		if ($nntp->isError($par2)) {
			return false;
		}

		// Put the PAR2 into Par2Info, check if there's an error.
		$this->_par2Info->setData($par2);
		if ($this->_par2Info->error) {
			return false;
		}

		// Get the file list from Par2Info.
		$files = $this->_par2Info->getFileList();
		if ($files !== false && count($files) > 0) {

			$filesAdded = 0;

			// Loop through the files.
			foreach ($files as $file) {

				if (!isset($file['name'])) {
					continue;
				}

				// If we found a name and added 10 files, stop.
				if ($foundName === true && $filesAdded > 10) {
					break;
				}

				if ($this->addpar2) {
					// Add to release files.
					if ($filesAdded < 11 &&
						$this->pdo->queryOneRow(
							sprintf('
								SELECT ID
								FROM releasefiles
								WHERE releaseID = %d
								AND name = %s',
								$relID,
								$this->pdo->escapeString($file['name'])
							)
						) === false
					) {

						// Try to add the files to the DB.
						if ($this->releaseFiles->add($relID, $file['name'], $file['size'], $query['post_date'], 0)) {
							$filesAdded++;
						}
					}
				} else {
					$filesAdded++;
				}

				// Try to get a new name.
				if ($foundName === false) {
					$query['textstring'] = $file['name'];
					if ($this->nameFixer->checkName($query, 1, 'PAR2, ', 1, $show) === true) {
						$foundName = true;
					}
				}
			}

			// If we found some files.
			if ($filesAdded > 0) {
				$this->debugging->log('PostProcess', 'parsePAR2', 'Added ' . $filesAdded . ' releasefiles from PAR2 for ' . $query['searchname'], \Logger::LOG_INFO);

				// Update the file count with the new file count + old file count.
				$this->pdo->queryExec(
					sprintf('
						UPDATE releases
						SET rarinnerfilecount = rarinnerfilecount + %d
						WHERE ID = %d',
						$filesAdded,
						$relID
					)
				);
			}
			if ($foundName === true) {
				return true;
			}
		}
		return false;
	}
}