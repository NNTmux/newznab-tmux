<?php
namespace newznab;

use newznab\Releases;
use newznab\utility\Utility;

/**
 * Class SABnzbd
 */
class CouchPotato
{
	/**
	 * URL to the CP server.
	 * @var string|array|bool
	 */
	public $cpurl = '';

	/**
	 * The SAB CP key.
	 * @var string|array|bool
	 */
	public $cpapi = '';

	/**
	 * ID of the current user
	 * @var string
	 */
	protected $uid = '';

	/**
	 * User's newznab API key
	 * @var string
	 */
	protected $rsstoken = '';

	/**
	 * nZEDb Site URL to send to SAB to download the NZB.
	 * @var string
	 */
	protected $serverurl = '';

	/**
	 * Construct.
	 *
	 * @param \BasePage $page
	 */
	public function __construct(&$page)
	{
		$this->uid = $page->userdata['id'];
		$this->rsstoken = $page->userdata['rsstoken'];
		$this->serverurl = $page->serverurl;
		$this->Releases = new Releases();

		$this->cpurl = !empty($page->userdata['cp_url']) ? $page->userdata['cp_url'] : '';
		$this->cpapi = !empty($page->userdata['cp_api']) ? $page->userdata['cp_api'] : '';

	}

	/**
	 * Send a release to CP.
	 *
	 * @param $guid
	 *
	 * @return bool|mixed
	 *
	 */
	public function sendToCouchPotato($guid)
	{
		$relData = $this->Releases->getByGuid($guid);
		$imdbid = $relData['imdbid'];
		$title = $relData['title'];

		return Utility::getUrl([
				'url' => $this->cpurl .
					'/api/' .
					$this->cpapi .
					'movie.add/?identifier=tt' .
					$imdbid .
					'&title=' .
					$title,
				'verifypeer' => false,
			]
		);
	}


}
