<?php
namespace nntmux;

use nntmux\db\DB;

/**
 * Class RequestID
 */
abstract class RequestID
{
	// Request id.
	const REQID_OLD    = -4; // We rechecked the web a second time and didn't find a title so don't process it again.
	const REQID_NONE   = -3; // The Request id was not found locally or via web lookup.
	const REQID_ZERO   = -2; // The Request id was 0.
	const REQID_NOLL   = -1; // Request id was not found via local lookup.
	const REQID_UPROC  =  0; // Release has not been processed.
	const REQID_FOUND  =  1; // Request id found and release was updated.

	const IS_REQID_TRUE = 1; // releases.isrequestid is 1
	const IS_REQID_FALSE = 0; // releases.isrequestid is 0

	/**
	 * @var Groups
	 */
	public $groups;

	/**
	 * @param array $options Class instances / Echo to cli?
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Echo'         => true,
			'Categorize'   => null,
			'ConsoleTools' => null,
			'Groups'       => null,
			'Settings'     => null,
			'SphinxSearch' => null,
		];
		$options += $defaults;

		$this->echoOutput = ($options['Echo'] && NN_ECHOCLI);
		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());
		$this->category = ($options['Categorize'] instanceof Categorize ? $options['Categorize'] : new Categorize(['Settings' => $this->pdo]));
		$this->groups = ($options['Groups'] instanceof Groups ? $options['Groups'] : new Groups(['Settings' => $this->pdo]));
		$this->consoleTools = ($options['ConsoleTools'] instanceof ConsoleTools ? $options['ConsoleTools'] : new ConsoleTools(['ColorCLI' => $this->pdo->log]));
		$this->sphinx = ($options['SphinxSearch'] instanceof SphinxSearch ? $options['SphinxSearch'] : new SphinxSearch());
	}

	/**
	 * Look up request id's for releases.
	 *
	 * @param array $options
	 *
	 * @return int Quantity of releases matched to a request id.
	 */
	public function lookupRequestIDs(array $options = [])
	{
		$curOptions = [
			'charGUID'      => '',
			'GroupID'       => '',
			'limit'         => '',
			'show'          => 1,
			'time'          => 0,
		];
		$curOptions = array_replace($curOptions, $options);

		$startTime = time();
		$renamed = 0;

		$this->_charGUID = $curOptions['charGUID'];
		$this->_groupID = $curOptions['GroupID'];
		$this->_show = $curOptions['show'];
		$this->_maxTime = $curOptions['time'];
		$this->_limit = $curOptions['limit'];

		$this->_getReleases();

		if ($this->_releases !== false && $this->_releases->rowCount() > 0) {
			$this->_totalReleases = $this->_releases->rowCount();
			$this->pdo->log->doEcho($this->pdo->log->primary('Processing ' . $this->_totalReleases . " releases for RequestID's."));
			$renamed = $this->_processReleases();
			if ($this->echoOutput) {
				echo $this->pdo->log->header(
					"\nRenamed " . number_format($renamed) . " releases in " . $this->consoleTools->convertTime(time() - $startTime) . "."
				);
			}
		} elseif ($this->echoOutput) {
			$this->pdo->log->doEcho($this->pdo->log->primary("No RequestID's to process."));
		}

		return $renamed;
	}

	/**
	 * Fetch releases with requestid's from MySQL.
	 */
	protected function _getReleases() { }

	/**
	 * Process releases for requestid's.
	 *
	 * @return int How many did we rename?
	 */
	protected function _processReleases() { }

	/**
	 * No request id was found, update the release.
	 *
	 * @param int $releaseID
	 * @param int $status
	 */
	protected function _requestIdNotFound($releaseID, $status)
	{
		if ($releaseID == 0) {
			return;
		}

		$this->pdo->queryExec(
			sprintf('
				UPDATE releases SET reqidstatus = %d WHERE id = %d',
				$status, $releaseID
			)
		);
	}

	/**
	 * Get a new title / pre id for a release.
	 *
	 * @return array|bool
	 */
	protected function _getNewTitle() { }

	/**
	 * Find a RequestID in a usenet subject.
	 *
	 * @return int
	 */
	protected function _siftReqId()
	{
		$requestID = [];
		switch (true) {
			case preg_match('/\[\s*#?scnzb@?efnet\s*\]\[(\d+)\]/', $this->_release['name'], $requestID):
			case preg_match('/\[\s*(\d+)\s*\]/', $this->_release['name'], $requestID):
			case preg_match('/^REQ\s*(\d{4,6})/i', $this->_release['name'], $requestID):
			case preg_match('/^(\d{4,6})-\d{1}\[/', $this->_release['name'], $requestID):
			case preg_match('/(\d{4,6}) -/',$this->_release['name'], $requestID):
				if ((int) $requestID[1] > 0) {
					return (int) $requestID[1];
				}
		}
		return self::REQID_ZERO;
	}

	/**
	 * @var bool Echo to CLI?
	 */
	protected $echoOutput;

	/**
	 * @var Categorize
	 */
	protected $category;

	/**
	 * @var \nntmux\db\Settings
	 */
	protected $pdo;

	/**
	 * @var ConsoleTools
	 */
	protected $consoleTools;

	/**
	 * @var ColorCLI
	 */
	protected $colorCLI;

	/**
	 * The found request id for the release.
	 * @var int
	 */
	protected $_requestID = self::REQID_ZERO;

	/**
	 * The title found from a request id lookup.
	 * @var bool|string|array
	 */
	protected $_newTitle = false;

	/**
	 * Releases with potential Request id's we can work on.
	 * @var \PDOStatement|bool
	 */
	protected $_releases;

	/**
	 * Total amount of releases we will be working on.
	 * @var int
	 */
	protected $_totalReleases;

	/**
	 * Release we are currently working on.
	 * @var array
	 */
	protected $_release;

	/**
	 * @var int To show the result or not.
	 */
	protected $_show = 0;

	/**
	 * GroupID, which is optional, to limit query results.
	 * @var string
	 */
	protected $_groupID;

	/**
	 * First character of a release GUID, which is optional, to limit query results.
	 * @var string
	 */
	protected $_charGUID;

	protected $_limit;

	protected $_maxTime;
}
