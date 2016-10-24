<?php
namespace nntmux\processing;

use app\models\Settings;
use dariusiii\rarinfo\Par2Info;
use nntmux\Books;
use nntmux\Category;
use nntmux\Console;
use nntmux\Games;
use nntmux\Groups;
use nntmux\Logger;
use nntmux\Movie;
use nntmux\Music;
use nntmux\NameFixer;
use nntmux\Nfo;
use nntmux\Sharing;
use nntmux\processing\tv\TVDB;
use nntmux\processing\tv\TVMaze;
use nntmux\processing\tv\TMDB;
use nntmux\processing\tv\TraktTv;
use nntmux\XXX;
use nntmux\ReleaseFiles;
use nntmux\db\DB;
use nntmux\processing\post\AniDB;
use nntmux\processing\post\ProcessAdditional;
use nntmux\SpotNab;

class PostProcess
{
	/**
	 * @var \nntmux\db\Settings
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
	 * @var \dariusiii\rarinfo\Par2Info
	 */
	protected $_par2Info;

	/**
	 * @var \dariusiii\rarinfo\SrrInfo
	 */
	protected $_srrInfo;

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
	 * @var \nntmux\Groups
	 */
	private $groups;

	/**
	 * @var \nntmux\Nfo
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

		// Various.
		$this->echooutput = ($options['Echo'] && NN_ECHOCLI);

		// Class instances.
		$this->pdo = (($options['Settings'] instanceof DB) ? $options['Settings'] : new DB());
		$this->groups = (($options['Groups'] instanceof Groups) ? $options['Groups'] : new Groups(['Settings' => $this->pdo]));
		$this->_par2Info = new Par2Info();
		$this->debugging = ($options['Logger'] instanceof Logger ? $options['Logger'] : new Logger(['ColorCLI' => $this->pdo->log]));
		$this->nameFixer = (($options['NameFixer'] instanceof NameFixer) ? $options['NameFixer'] : new NameFixer(['Echo' => $this->echooutput, 'Settings' => $this->pdo, 'Groups' => $this->groups]));
		$this->Nfo = (($options['Nfo'] instanceof Nfo) ? $options['Nfo'] : new Nfo(['Echo' => $this->echooutput, 'Settings' => $this->pdo]));
		$this->releaseFiles = (($options['ReleaseFiles'] instanceof ReleaseFiles) ? $options['ReleaseFiles'] : new ReleaseFiles($this->pdo));

		// Site settings.
		$this->addpar2 = (Settings::value('..addpar2') == 0) ? false : true;
		$this->alternateNNTP = (Settings::value('..alternate_nntp') == 1 ? true : false);
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
		$this->processSharing($nntp);
		$this->processSpotnab();
		$this->processMovies();
		$this->processMusic();
		$this->processConsoles();
		$this->processGames();
		$this->processAnime();
		$this->processTv();
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
		if (Settings::value('..lookupanidb') != 0) {
			(new AniDB(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processAnimeReleases();
		}
	}

	/**
	 * Process books using amazon.com.
	 *
	 * @return void
	 */
	public function processBooks()
	{
		if (Settings::value('..lookupbooks') != 0) {
			(new Books(['Echo' => $this->echooutput, 'Settings' => $this->pdo, ]))->processBookReleases();
		}
	}

	/**
	 * Lookup console games if enabled.
	 *
	 * @return void
	 */
	public function processConsoles()
	{
		if (Settings::value('..lookupgames') != 0) {
			(new Console(['Settings' => $this->pdo, 'Echo' => $this->echooutput]))->processConsoleReleases();
		}
	}

	/**
	 * Lookup games if enabled.
	 *
	 * @return void
	 */
	public function processGames()
	{
		if (Settings::value('..lookupgames') != 0) {
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
		$processMovies = (is_numeric($processMovies) ? $processMovies : Settings::value('..lookupimdb'));
		if ($processMovies > 0) {
			(new Movie(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processMovieReleases($groupID, $guidChar, $processMovies);
		}
	}

	/**
	 * Lookup music if enabled.
	 *
	 * @return void
	 */
	public function processMusic()
	{
		if (Settings::value('..lookupmusic') != 0) {
			(new Music(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processMusicReleases();
		}
	}

	/**
	 * Process nfo files.
	 *
	 * @param \nntmux\NNTP   $nntp
	 * @param string $groupID  (Optional) ID of a group to work on.
	 * @param string $guidChar (Optional) First letter of a release GUID to use to get work.
	 *
	 * @return void
	 */
	public function processNfos(&$nntp, $groupID = '', $guidChar = '')
	{
		if (Settings::value('..lookupnfo') == 1) {
			$this->Nfo->processNfoFiles($nntp, $groupID, $guidChar, (int)Settings::value('..lookupimdb'), (int)Settings::value('..lookuptvrage'));
		}
	}

	/**
	 * Process comments.
	 *
	 * @param \nntmux\NNTP $nntp
	 */
	public function processSharing(&$nntp)
	{
		(new Sharing(['Settings' => $this->pdo, 'NNTP' => $nntp]))->start();
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
		$processTV = (is_numeric($processTV) ? $processTV : Settings::value('..lookuptvrage'));
		if ($processTV > 0) {
			(new TVDB(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processSite($groupID, $guidChar, $processTV);
			(new TVMaze(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processSite($groupID, $guidChar, $processTV);
			(new TMDB(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processSite($groupID, $guidChar, $processTV);
			(new TraktTv(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processSite($groupID, $guidChar, $processTV);
		}
	}

	/**
	 * Process Global IDs
	 */
	public function processSpotnab()
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
	 * Lookup xxx if enabled.
	 */
	public function processXXX()
	{
		if (Settings::value('..lookupxxx') == 1) {
			(new XXX(['Echo' => $this->echooutput, 'Settings' => $this->pdo]))->processXXXReleases();
		}
	}

	/**
	 * Check for passworded releases, RAR/ZIP contents and Sample/Media info.
	 *
	 * @note Called externally by tmux/bin/update_per_group and update/postprocess.php
	 *
	 * @param \nntmux\NNTP       $nntp    Class NNTP
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
	 * @param \nntmux\NNTP   $nntp      Class NNTP
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
				SELECT id, groups_id, categories_id, name, searchname, UNIX_TIMESTAMP(postdate) AS post_date, id AS releases_id
				FROM releases
				WHERE isrenamed = 0
				AND id = %d',
				$relID
			)
		);

		if ($query === false) {
			return false;
		}

		// Only get a new name if the category is OTHER.
		$foundName = true;
		if (in_array(
			(int)$query['categories_id'],
			[
				Category::BOOKS_UNKNOWN,
				Category::GAME_OTHER,
				Category::MOVIE_OTHER,
				Category::MUSIC_OTHER,
				Category::PC_PHONE_OTHER,
				Category::TV_OTHER,
				Category::OTHER_HASHED,
				Category::XXX_OTHER,
				Category::OTHER_MISC
			]
		)
		) {
			$foundName = false;
		}

		// Get the PAR2 file.
		$par2 = $nntp->getMessages($this->groups->getNameByID($groupID), $messageID, $this->alternateNNTP);
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
								SELECT releases_id
								FROM release_files
								WHERE releases_id = %d
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
				$this->debugging->log(get_class(), __FUNCTION__, 'Added ' . $filesAdded . ' release_files from PAR2 for ' . $query['searchname'], Logger::LOG_INFO);

				// Update the file count with the new file count + old file count.
				$this->pdo->queryExec(
					sprintf('
						UPDATE releases
						SET rarinnerfilecount = rarinnerfilecount + %d
						WHERE id = %d',
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
