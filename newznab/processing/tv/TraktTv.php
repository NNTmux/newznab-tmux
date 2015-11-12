<?php
namespace newznab\processing\tv;

use newznab\libraries\TraktAPI;
use newznab\ReleaseImage;

/**
 * Class TraktTv
 *
 * Process information retrieved from the Trakt API.
 */
class TraktTv extends TV
{
	const MATCH_PROBABILITY = 75;

	/**
	 * Client for Trakt API
	 *
	 * @var \newznab\libraries\TraktAPI
	 */
	public $client;

	/**
	 * @string DateTimeZone Object - UTC
	 */
	private $timeZone;

	/**
	 * @string MySQL DATETIME Format
	 */
	private $timeFormat;

	/**
	 * @var string The URL for the image for poster
	 */
	private $posterUrl;

	/**
	 * Construct. Set up API key.
	 *
	 * @param array $options Class instances.
	 *
	 * @access public
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);
		$this->client = new TraktAPI(['clientId' => $this->pdo->getSetting('trakttvclientkey')]);
		$this->timeZone = new \DateTimeZone('UTC');
		$this->timeFormat = 'Y-m-d H:i:s';
	}

	/**
	 * Main processing director function for Trakt
	 * Calls work query function and initiates processing
	 *
	 * @param            $groupID
	 * @param            $guidChar
	 * @param            $processTV
	 * @param bool|false $local
	 */
	public function processTrakt($groupID, $guidChar, $processTV, $local = false)
	{
		$res = $this->getTvReleases($groupID, $guidChar, $processTV, parent::PROCESS_TRAKT);

		$tvcount = $res->rowCount();

		if ($this->echooutput && $tvcount > 0) {
			echo $this->pdo->log->header("Processing TRAKT lookup for " . number_format($tvcount) . " release(s).");
		}

		if ($res instanceof \Traversable) {
			foreach ($res as $row) {

				$this->posterUrl = '';
				$traktid = false;

				// Clean the show name for better match probability
				$release = $this->parseShowInfo($row['searchname']);

				if (is_array($release) && $release['name'] != '') {

					// Find the Video ID if it already exists by checking the title against stored TMDB titles
					$videoId = $this->getByTitle($release['cleanname'], parent::TYPE_TV, parent::SOURCE_TRAKT);

					// Force local lookup only
					if ($local == true) {
						$lookupSetting = false;
					} else {
						$lookupSetting = true;
					}

					// If lookups are allowed lets try to get it.
					if ($videoId === false && $lookupSetting) {
						if ($this->echooutput) {
							echo $this->pdo->log->primaryOver("Checking TRAKT for previously failed title: ") .
									$this->pdo->log->headerOver($release['cleanname']) .
									$this->pdo->log->primary(".");
						}

						// Get the show from TRAKT
						$traktShow = $this->getShowInfo((string)$release['cleanname']);

						if (is_array($traktShow)) {
							// Check if we have the TVDB ID already, if we do use that Video ID
							$dupeCheck = $this->getVideoIDFromSiteID('tvdb', $traktShow['tvdb']);
							if ($dupeCheck === false) {
								$videoId = $this->add($traktShow);
								$traktid = $traktShow['trakt'];
							} else {
								$videoId = $dupeCheck;
								// Update any missing fields and add site IDs
								$this->update($videoId, $traktShow);
								$traktid = $this->getSiteIDFromVideoID('trakt', $videoId);
							}
						}
					} else {
						if ($this->echooutput) {
							echo $this->pdo->log->primaryOver("Found local TRAKT match for: ") .
									$this->pdo->log->headerOver($release['cleanname']) .
									$this->pdo->log->primary(".  Attempting episode lookup!");
						}
						$traktid = $this->getSiteIDFromVideoID('trakt', $videoId);
					}

					if (is_numeric($videoId) && $videoId > 0 && is_numeric($traktid) && $traktid > 0) {
						// Now that we have valid video and trakt ids, try to get the poster
						$this->getPoster($videoId, $traktid);

						$seasonNo = preg_replace('/^S0*/i', '', $release['season']);
						$episodeNo = preg_replace('/^E0*/i', '', $release['episode']);

						if ($episodeNo === 'all') {
							// Set the video ID and leave episode 0
							$this->setVideoIdFound($videoId, $row['id'], 0);
							echo $this->pdo->log->primary("Found TRAKT Match for Full Season!");
							continue;
						}

						// Download all episodes if new show to reduce API usage
						if ($this->countEpsByVideoID($videoId) === false) {
							$this->getEpisodeInfo($traktid, -1, -1, '', $videoId);
						}

						// Check if we have the episode for this video ID
						$episode = $this->getBySeasonEp($videoId, $seasonNo, $episodeNo, $release['airdate']);

						if ($episode === false) {
							// Send the request for the episode to TRAKT
							$traktEpisode = $this->getEpisodeInfo(
									$traktid,
									$seasonNo,
									$episodeNo,
									$release['airdate']
							);

							if ($traktEpisode) {
								$episode = $this->addEpisode($videoId, $traktEpisode);
							}
						}

						if ($episode !== false && is_numeric($episode) && $episode > 0) {
							// Mark the releases video and episode IDs
							$this->setVideoIdFound($videoId, $row['id'], $episode);
							if ($this->echooutput) {
								echo $this->pdo->log->primary("Found TRAKT Match!");
							}
							continue;
						}
					}
				} //Processing failed, set the episode ID to the next processing group
				$this->setVideoNotFound(parent::PROCESS_IMDB, $row['id']);
			}
		}
	}

	/**
	 * Fetch banner from site.
	 *
	 * @param $videoId
	 * @param $siteID
	 *
	 * @return bool
	 */
	public function getBanner($videoId, $siteID)
	{
		return false;
	}

	/**
	 * Gets the specific episode info for the parsed release after match
	 * Returns a formatted array of episode data or false if no match
	 *
	 * @param integer $traktid
	 * @param integer $season
	 * @param integer $episode
	 * @param string  $airdate
	 * @param integer $videoId
	 *
	 * @return array|bool
	 */
	protected function getEpisodeInfo($traktid, $season, $episode, $airdate = '', $videoId = 0)
	{
		$return = $response = false;

		if ($videoId > 0) {
			try {
				$response = $this->client->showSummary($traktid);
			} catch (\Exception $error) {
			}
		} else {
			try {
				$response = $this->client->episodeSummary($traktid, $season, $episode);
			} catch (\Exception $error) {
			}
		}

		sleep(1);

		if (is_object($response)) {
			if ($this->checkRequired($response, 'traktE')) {
				$return = $this->formatEpisodeArr($response);
			}
		} else if (is_array($response) && $videoId > 0) {
			if ($this->checkRequired($response, 'traktE')) {
				$this->addEpisode($videoId, $this->formatEpisodeArr($response));
			}
		}

		return $return;
	}

	public function getMovieInfo()
	{
		return false;
	}

	/**
	 * Retrieves the poster art for the processed show
	 *
	 * @param int $videoId -- the local Video ID
	 * @param int $showId  -- the TRAKT ID
	 *
	 * @return null
	 */
	protected function getPoster($videoId, $showId)
	{
		$ri = new ReleaseImage($this->pdo);

		$poster = $this->client->showSummary($showId, 'images');

		// Try to get the Poster
		$hascover = $ri->saveImage($videoId, $poster['images']['poster']['thumb'], $this->imgSavePath, '', '');

		// Couldn't get poster, try fan art instead
		if ($hascover !== 1) {
			$hascover = $ri->saveImage($videoId, $poster['images']['fanart']['thumb'], $this->imgSavePath, '', '');
		}
		// Mark it retrieved if we saved an image
		if ($hascover == 1) {
			$this->setCoverFound($videoId);
		}
	}

	/**
	 * Calls the API to perform initial show name match to TRAKT title
	 * Returns a formatted array of show data or false if no match
	 *
	 * @param string $cleanName
	 *
	 * @param string $country
	 *
	 * @return array|bool
	 */
	protected function getShowInfo($cleanName, $country = '')
	{
		$return = $response = false;
		$highestMatch = 0;
		try {
			$response = (array)$this->client->showSummary($cleanName, 'full');
		} catch (\Exception $error) {

		}

		sleep(1);

		if (is_array($response)) {
			foreach ($response as $show) {
				if ($this->checkRequired($show, 'traktS')) {
					// Check for exact title match first and then terminate if found
					if ($show->name === $cleanName) {
						$highest = $show;
						break;
					}

					// Check each show title for similarity and then find the highest similar value
					$matchPercent = $this->checkMatch($show->name, $cleanName, self::MATCH_PROBABILITY);

					// If new match has a higher percentage, set as new matched title
					if ($matchPercent > $highestMatch) {
						$highestMatch = $matchPercent;
						$highest = $show;
					}

					// Check for show aliases and try match those too
					if (!empty($show->aliasNames)) {
						foreach ($show->aliasNames as $key => $name) {
							$matchPercent = $this->CheckMatch($name, $cleanName, $matchPercent);
							if ($matchPercent > $highestMatch) {
								$highestMatch = $matchPercent;
								$highest = $show;
							}
						}
					}
				}
			}
			if (isset($highest)) {
				$return = $this->formatShowArr($highest);
			}
		}

		return $return;
	}

	/**
	 * Assigns API show response values to a formatted array for insertion
	 * Returns the formatted array
	 *
	 * @param $show
	 *
	 * @return array
	 */
	private function formatShowArr($show)
	{
		$show->firstAired->setTimezone($this->timeZone);
		preg_match('/tt(?P<imdbid>\d{6,7})$/i', $show->ids->imdb, $imdb);

		return [
				'type'      => (int)parent::TYPE_TV,
				'title'     => (string)$show->title,
				'summary'   => (string)$show->overview,
				'started'   => (string)$show->first_aired->format($this->timeFormat),
				'publisher' => (string)$show->network,
				'source'    => (int)parent::SOURCE_TRAKT,
				'imdb'      => (int)(isset($show->ids->imdb) ? $show->ids->imdb : 0),
				'tvdb'      => 0,
				'trakt'     => (int)$show->ids->trakt,
				'tvrage'    => (int)(isset($show->ids->tvrage) ? $show->ids->tvrage : 0),
				'tvmaze'    => 0,
				'tmdb'      => (int)(isset($show->ids->tmdb) ? $show->ids->tmdb : 0),
				'aliases'   => (!empty($show->aliasNames) ? (array)$show->aliasNames : '')
		];
	}

	/**
	 * Assigns API episode response values to a formatted array for insertion
	 * Returns the formatted array
	 *
	 * @param $episode
	 *
	 * @return array
	 */
	private function formatEpisodeArr($episode)
	{
		$episode->firstAired->setTimezone($this->timeZone);

		return [
				'title'       => (string)$episode->title,
				'series'      => (int)$episode->season,
				'episode'     => (int)$episode->number,
				'se_complete' => (string)'S' . sprintf('%02d', $episode->season) . 'E' . sprintf('%02d', $episode->number),
				'firstaired'  => (string)$episode->first_aired->format($this->timeFormat),
				'summary'     => (string)$episode->overview
		];
	}
}
