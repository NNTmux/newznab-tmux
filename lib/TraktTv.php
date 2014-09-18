<?php
require_once(WWW_DIR . '/lib/framework/db.php');
require_once(WWW_DIR . '/lib/Tmux.php');
require_once(WWW_DIR . '/lib/util.php');

/**
 * Class TraktTv
 * Lookup information from trakt.tv using their API.
 */
Class TraktTv
{
	private $APIKEY;

	/**
	 * Construct. Set up API key.
	 *
	 * @param array $options Class instances.
	 *
	 * @access public
	 */
	public function __construct(array $options = array())
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$settings = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
		$s = new Sites();
		$site = $s->get();
		$this->APIKEY = $site->trakttvkey;
	}

	/**
	 * Fetches summary from trakt.tv for the TV show using the title/season/episode.
	 *
	 * @param string $title
	 * @param string $season
	 * @param string $ep
	 *
	 * @return bool|mixed
	 *
	 * @access public
	 */
	public function traktTVSEsummary($title = '', $season = '', $ep = '')
	{
		if (!empty($this->APIKEY)) {
			$TVjson = getUrl([
					'url' =>
						'http://api.trakt.tv/show/episode/summary.json/' .
						$this->APIKEY . '/' .
						str_replace([' ', '_', '.'], '-', $title) . '/' .
						str_replace(['S', 's'], '', $season) . '/' .
						str_replace(['E', 'e'], '', $ep)
				]
			);

			if ($TVjson !== false) {
				return json_decode($TVjson, true);
			}
		}
		return false;
	}

	/**
	 * Fetches summary from trakt.tv for the movie.
	 * Accept a title (the-big-lebowski-1998), a IMDB id, or a TMDB id.
	 *
	 * @param string $movie Title or IMDB id.
	 * @param bool $array   Return the full array or just the IMDB id.
	 *
	 * @return bool|mixed
	 *
	 * @access public
	 */
	public function traktMoviesummary($movie = '', $array=false)
	{
		if (!empty($this->APIKEY)) {
			$MovieJson = getUrl([
					'url' =>
						'http://api.trakt.tv/movie/summary.json/' .
						$this->APIKEY .
						'/' .
						str_replace([' ', '_', '.'], '-',  str_replace(['(', ')'], '', $movie))
				]
			);

			if ($MovieJson !== false) {
				$MovieJson = json_decode($MovieJson, true);
				if (isset($MovieJson['status']) && $MovieJson['status'] === 'failure') {
					return false;
				}

				if ($array) {
					return $MovieJson;
				} elseif (isset($MovieJson["imdb_id"])) {
					return $MovieJson["imdb_id"];
				}
			}
		}
		return false;
	}
}