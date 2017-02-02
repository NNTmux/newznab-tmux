<?php
namespace nntmux;

use b3rs3rk\steamfront\Main;
use nntmux\db\DB;

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
	 * @return \b3rs3rk\steamfront\data\App
	 */
	public function getAll($appID)
	{
		return $this->steamClient->getAppDetails($appID);

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
