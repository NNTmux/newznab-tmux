<?php
namespace nntmux;

use b3rs3rk\steamfront\Main;

class Steam
{

	/**
	 * @var
	 */
	public $searchTerm;


	/**
	 * @var
	 */
	protected $steamGameID;

	public function __construct()
	{
		$this->steamClient = new Main(
			[
				'country_code' => 'us',
				'local_lang' => 'english'
			]
		);
	}


	/**
	 * Gets all Information for the game.
	 *
	 * @param integer $appID
	 *
	 * @return array
	 */
	public function getAll($appID)
	{
		$res = $this->steamClient->getAppDetails($appID);
		$result = [
			'title' => $res->name,
			'description' => $res->description['short'],
			'cover' => $res->images['header'],
			'backdrop' => $res->images['background'],
			'steamid' =>$res->appid,
			'directurl' => Main::STEAM_STORE_ROOT . 'app/' . $res->appid,
			'publisher' => $res->publishers,
			'rating' => $res->metacritic['score'],
			'releasedate' => $res->releasedate['date'],
			'genres' => $res->genres
		];
	   return $result;
	}


	/**
	 * Searches for a game for a 90% match
	 *
	 * @param string $searchTerm
	 *
	 * @return bool
	 */
	public function search($searchTerm)
	{
		if (empty($searchTerm)) {
			return false;
		}

		$steamGames = $this->steamClient->getFullAppList();
		foreach ($steamGames as $gamesArray) {
			if (is_array($gamesArray)) {
				foreach ($gamesArray as $gameArray) {
					foreach ($gameArray as $game) {
						similar_text(strtolower($game['name']), strtolower($searchTerm), $percent);
						if ($percent > 90) {
							return $game['appid'];
						}
					}
				}
			}
		}
		return false;
	}
}
