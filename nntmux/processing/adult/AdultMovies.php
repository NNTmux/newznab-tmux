<?php

namespace nntmux\processing\adult;


use nntmux\ColorCLI;
use nntmux\XXX;

abstract class AdultMovies extends XXX
{
	// Television Sources
	const SOURCE_NONE    = 0;
	const SOURCE_ADE    = 1;
	const SOURCE_ADM  = 2;
	const SOURCE_AEBN    = 3;
	const SOURCE_HOTMOVIES   = 4;
	const SOURCE_IAFD    = 5;
	const SOURCE_POPPORN    = 6;

	// Processing signifiers
	const PROCESS_ADE   =  0;
	const PROCESS_ADM = -1;
	const PROCESS_AEBN   = -2;
	const PROCESS_HOTMOVIES  = -3;
	const PROCESS_IAFD   = -4;
	const PROCESS_POPPORN   = -5;
	const NO_MATCH_FOUND = -6;
	const FAILED_PARSE   = -100;

	/**
	 * AdultMovies constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
	}

	/**
	 * Fetch xxx info for the movie.
	 *
	 * @param $xxxmovie
	 *
	 * @return bool
	 */
	public function updateXXXInfo($xxxmovie): bool
	{

		$res = false;
		$this->whichclass = '';

		$iafd = new IAFD();
		$iafd->searchTerm = $xxxmovie;

		if ($iafd->findme() !== false) {

			switch ($iafd->classUsed) {
				case 'ade':
					$mov = new ADE();
					$mov->directLink = (string)$iafd->directUrl;
					$res = $mov->getDirect();
					$res['title'] = $iafd->title;
					$res['directurl'] = (string)$iafd->directUrl;
					$this->whichclass = $iafd->classUsed;
					ColorCLI::doEcho(ColorCLI::primary('Fetching XXX info from IAFD -> Adult DVD Empire'));
					break;
			}
		}

		if ($res === false) {

			$this->whichclass = 'aebn';
			$mov = new AEBN();
			$mov->cookie = $this->cookie;
			$mov->searchTerm = $xxxmovie;
			$res = $mov->search();

			if ($res === false) {
				$this->whichclass = 'ade';
				$mov = new ADE();
				$mov->searchTerm = $xxxmovie;
				$res = $mov->search();
			}

			if ($res === false) {
				$this->whichclass = 'pop';
				$mov = new Popporn();
				$mov->cookie = $this->cookie;
				$mov->searchTerm = $xxxmovie;
				$res = $mov->search();
			}

			// Last in list as it doesn't have trailers
			if ($res === false) {
				$this->whichclass = 'adm';
				$mov = new ADM();
				$mov->cookie = $this->cookie;
				$mov->searchTerm = $xxxmovie;
				$res = $mov->search();
			}


			// If a result is true getAll information.
			if ($res !== false) {
				if ($this->echooutput) {

					switch ($this->whichclass) {
						case 'aebn':
							$fromstr = 'Adult Entertainment Broadcast Network';
							break;
						case 'ade':
							$fromstr = 'Adult DVD Empire';
							break;
						case 'pop':
							$fromstr = 'PopPorn';
							break;
						case 'adm':
							$fromstr = 'Adult DVD Marketplace';
							break;
						default:
							$fromstr = null;
					}
					ColorCLI::doEcho(ColorCLI::primary('Fetching XXX info from: ' . $fromstr));
				}
				$res = $mov->getAll();
			} else {
				// Nothing was found, go ahead and set to -2
				return -2;
			}
		}

		$res['cast'] = !empty($res['cast']) ? implode(',', $res['cast']) : '';
		$res['genres'] = !empty($res['genres']) ? $this->getGenreID($res['genres']) : '';

		$mov = [
			'trailers'    => !empty($res['trailers']) ? serialize($res['trailers']) : '',
			'extras'      => !empty($res['extras']) ? serialize($res['extras']) : '',
			'productinfo' => !empty($res['productinfo']) ? serialize($res['productinfo']) : '',
			'backdrop'    => !empty($res['backcover']) ? $res['backcover'] : 0,
			'cover'       => !empty($res['boxcover']) ? $res['boxcover'] : 0,
			'title'       => !empty($res['title']) ? html_entity_decode($res['title'], ENT_QUOTES, 'UTF-8') : '',
			'plot'        => !empty($res['sypnosis']) ? html_entity_decode($res['sypnosis'], ENT_QUOTES, 'UTF-8') : '',
			'tagline'     => !empty($res['tagline']) ? html_entity_decode($res['tagline'], ENT_QUOTES, 'UTF-8') : '',
			'genre'       => !empty($res['genres']) ? html_entity_decode($res['genres'], ENT_QUOTES, 'UTF-8') : '',
			'director'    => !empty($res['director']) ? html_entity_decode($res['director'], ENT_QUOTES, 'UTF-8') : '',
			'actors'      => !empty($res['cast']) ? html_entity_decode($res['cast'], ENT_QUOTES, 'UTF-8') : '',
			'directurl'   => !empty($res['directurl']) ? html_entity_decode($res['directurl'], ENT_QUOTES, 'UTF-8') : '',
			'classused'   => $this->whichclass
		];

		$check = $this->pdo->queryOneRow(sprintf('SELECT id FROM xxxinfo WHERE title = %s', $this->pdo->escapeString($mov['title'])));
		$xxxID = 0;
		if (isset($check['id'])) {
			$xxxID = $check['id'];
		}

		// Update Current XXX Information - getXXXCovers.php
		if ($xxxID > 0) {
			$this->update($check['id'], $mov['title'], $mov['tagline'], $mov['plot'], $mov['genre'], $mov['director'], $mov['actors'], $mov['extras'], $mov['productinfo'], $mov['trailers'], $mov['directurl'], $mov['classused']);
			$xxxID = $check['id'];

			// BoxCover.
			if (isset($mov['cover'])) {
				$mov['cover'] = $this->releaseImage->saveImage($xxxID . '-cover', $mov['cover'], $this->imgSavePath);
			}

			// BackCover.
			if (isset($mov['backdrop'])) {
				$mov['backdrop'] = $this->releaseImage->saveImage($xxxID . '-backdrop', $mov['backdrop'], $this->imgSavePath, 1920, 1024);
			}

			$this->pdo->queryExec(sprintf('UPDATE xxxinfo SET cover = %d, backdrop = %d  WHERE id = %d', $mov['cover'], $mov['backdrop'], $xxxID));

		} else {
			$xxxID = -2;
		}

		// Insert New XXX Information
		if ($check === false) {
			$xxxID = $this->pdo->queryInsert(
				sprintf('
					INSERT INTO xxxinfo
						(title, tagline, plot, genre, director, actors, extras, productinfo, trailers, directurl, classused, cover, backdrop, createddate, updateddate)
					VALUES
						(%s, %s, COMPRESS(%s), %s, %s, %s, %s, %s, %s, %s, %s, 0, 0, NOW(), NOW())',
					$this->pdo->escapeString($mov['title']),
					$this->pdo->escapeString($mov['tagline']),
					$this->pdo->escapeString($mov['plot']),
					$this->pdo->escapeString(substr($mov['genre'], 0, 64)),
					$this->pdo->escapeString($mov['director']),
					$this->pdo->escapeString($mov['actors']),
					$this->pdo->escapeString($mov['extras']),
					$this->pdo->escapeString($mov['productinfo']),
					$this->pdo->escapeString($mov['trailers']),
					$this->pdo->escapeString($mov['directurl']),
					$this->pdo->escapeString($mov['classused'])
				)
			);
		}

		if ($this->echooutput) {
			ColorCLI::doEcho(
				ColorCLI::headerOver(($xxxID !== false ? 'Added/updated XXX movie: ' . ColorCLI::primary($mov['title']) : 'Nothing to update for XXX movie: ' . ColorCLI::primary($mov['title'])))
			);
		}

		return $xxxID;
	}

	/**
	 * Process XXX releases where xxxinfo is 0
	 *
	 */
	public function processXXXReleases(): void
	{
		$res = $this->pdo->query(sprintf('
				SELECT r.searchname, r.id
				FROM releases r
				WHERE r.nzbstatus = 1
				AND r.xxxinfo_id = 0
				%s
				LIMIT %d',
				$this->catWhere,
				$this->movieqty
			)
		);
		$movieCount = count($res);

		if ($movieCount > 0) {

			if ($this->echooutput) {
				ColorCLI::doEcho(ColorCLI::header('Processing ' . $movieCount . ' XXX releases.'));
			}

			// Loop over releases.
			foreach ($res as $arr) {

				$idcheck = -2;

				// Try to get a name.
				if ($this->parseXXXSearchName($arr['searchname']) !== false) {
					$check = $this->checkXXXInfoExists($this->currentTitle);
					if ($check === false) {
						$this->currentRelID = $arr['id'];
						$movieName = $this->currentTitle;
						if ($this->debug && $this->echooutput) {
							ColorCLI::doEcho('DB name: ' . $arr['searchname'], true);
						}
						if ($this->echooutput) {
							ColorCLI::doEcho(ColorCLI::primaryOver('Looking up: ') . ColorCLI::headerOver($movieName), true);
						}

						$idcheck = $this->updateXXXInfo($movieName);
					} else {
						$idcheck = (int)$check['id'];
					}
				} else {
					ColorCLI::doEcho('.', true);
				}
				$this->pdo->queryExec(sprintf('UPDATE releases SET xxxinfo_id = %d WHERE id = %d %s', $idcheck, $arr['id'], $this->catWhere));
			}
		} elseif ($this->echooutput) {
			ColorCLI::doEcho(ColorCLI::header('No xxx releases to process.'));
		}
	}

	/**
	 * Checks xxxinfo to make sure releases exist
	 *
	 * @param $releaseName
	 *
	 * @return array|bool
	 */
	protected function checkXXXInfoExists($releaseName)
	{
		return $this->pdo->queryOneRow(sprintf('SELECT id, title FROM xxxinfo WHERE title %s', $this->pdo->likeString($releaseName, false, true)));
	}

	/**
	 * Cleans up a searchname to make it easier to scrape.
	 *
	 * @param string $releaseName
	 *
	 * @return bool
	 */
	protected function parseXXXSearchName($releaseName): bool
	{
		$name = '';
		$followingList = '[^\w]((2160|1080|480|720)(p|i)|AC3D|Directors([^\w]CUT)?|DD5\.1|(DVD|BD|BR)(Rip)?|BluRay|divx|HDTV|iNTERNAL|LiMiTED|(Real\.)?Proper|RE(pack|Rip)|Sub\.?(fix|pack)|Unrated|WEB-DL|(x|H)[-._ ]?264|xvid|[Dd][Ii][Ss][Cc](\d+|\s*\d+|\.\d+)|XXX|BTS|DirFix|Trailer|WEBRiP|NFO|(19|20)\d\d)[^\w]';

		if (preg_match('/([^\w]{2,})?(?P<name>[\w .-]+?)' . $followingList . '/i', $releaseName, $matches)) {
			$name = $matches['name'];
		}

		// Check if we got something.
		if ($name !== '') {

			// If we still have any of the words in $followingList, remove them.
			$name = preg_replace('/' . $followingList . '/i', ' ', $name);
			// Remove periods, underscored, anything between parenthesis.
			$name = preg_replace('/\(.*?\)|[-._]/i', ' ', $name);
			// Finally remove multiple spaces and trim leading spaces.
			$name = trim(preg_replace('/\s{2,}/', ' ', $name));
			// Remove Private Movies {d} from name better matching.
			$name = trim(preg_replace('/^Private\s(Specials|Blockbusters|Blockbuster|Sports|Gold|Lesbian|Movies|Classics|Castings|Fetish|Stars|Pictures|XXX|Private|Black\sLabel|Black)\s\d+/i', '', $name));
			// Remove Foreign Words at the end of the name.
			$name = trim(preg_replace('/(brazilian|chinese|croatian|danish|deutsch|dutch|estonian|flemish|finnish|french|german|greek|hebrew|icelandic|italian|latin|nordic|norwegian|polish|portuguese|japenese|japanese|russian|serbian|slovenian|spanish|spanisch|swedish|thai|turkish)$/i', '', $name));

			// Check if the name is long enough and not just numbers and not file (d) of (d) and does not contain Episodes and any dated 00.00.00 which are site rips..
			if (strlen($name) > 5 && !preg_match('/^\d+$/', $name) && !preg_match('/( File \d+ of \d+|\d+.\d+.\d+)/', $name) && !preg_match('/(E\d+)/', $name) && !preg_match('/\d\d\.\d\d.\d\d/', $name)) {
				$this->currentTitle = $name;

				return true;
			}
			ColorCLI::doEcho('.', false);
		}

		return false;
	}

}