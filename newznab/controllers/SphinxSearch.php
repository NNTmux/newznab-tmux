<?php
use newznab\db\DB;

class SphinxSearch
{
	/**
	 * SphinxQL connection.
	 * @var newznab\db\DB
	 */
	public $sphinxQL = null;

	/**
	 * Establish connection to SphinxQL.
	 */
	public function __construct()
	{
		if (NN_RELEASE_SEARCH_TYPE == \ReleaseSearch::SPHINX) {
			if (!defined('NN_SPHINXQL_HOST_NAME')) {
				define('NN_SPHINXQL_HOST_NAME', '0');
			}
			if (!defined('NN_SPHINXQL_PORT')) {
				define('NN_SPHINXQL_PORT', 9306);
			}
			if (!defined('NN_SPHINXQL_SOCK_FILE')) {
				define('NN_SPHINXQL_SOCK_FILE', '');
			}
			$this->sphinxQL = new DB(
				[
					'dbname' => '',
					'dbport' => NN_SPHINXQL_PORT,
					'dbhost' => NN_SPHINXQL_HOST_NAME,
					'dbsock' => NN_SPHINXQL_SOCK_FILE
				]
			);
		}
	}

	/**
	 * Insert release into Sphinx RT table.
	 * @param $parameters
	 */
	public function insertRelease($parameters)
	{
		if (!is_null($this->sphinxQL) && $parameters['id']) {
			$this->sphinxQL->queryExec(
				sprintf(
					'REPLACE INTO releases_rt (id, guid, name, searchname, fromname) VALUES (%s, %s, %s, %s, %s)',
					$parameters['id'],
					$parameters['guid'],
					$parameters['name'],
					$parameters['searchname'],
					$parameters['fromname']
				)
			);
		}
	}

	/**
	 * Delete release from Sphinx RT table.
	 * @param array $identifiers ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass false)]
	 * @param DB $pdo
	 */
	public function deleteRelease($identifiers, DB $pdo)
	{
		if (!is_null($this->sphinxQL)) {
			if ($identifiers['i'] === false) {
				$identifiers['i'] = $pdo->queryOneRow(
					sprintf('SELECT id FROM releases WHERE guid = %s', $pdo->escapeString($identifiers['g']))
				);
				if ($identifiers['i'] !== false) {
					$identifiers['i'] = $identifiers['i']['id'];
				}
			}
			if ($identifiers['i'] !== false) {
				$this->sphinxQL->queryExec(sprintf('DELETE FROM releases_rt WHERE id = %s', $identifiers['i']));
			}
		}
	}

	public static function escapeString($string)
	{
		$from = array ('\\', '(',')','|','---','--','-','!','@','~','"','&', '/', '^', '$', '=', "'", "\x00", "\n", "\r", "\x1a");
		$to = array ('\\\\\\\\','\\\\\\\\(','\\\\\\\\)','\\\\\\\\|','-','-','\\\\\\\\-','\\\\\\\\!','\\\\\\\\@','\\\\\\\\~',
				'\\\\\\\\"', '\\\\\\\\&', '\\\\\\\\/', '\\\\\\\\^', '\\\\\\\\$', '\\\\\\\\=', "\\'", "\\x00", "\\n", "\\r", "\\x1a");
		return str_replace($from, $to, $string);
	}

	/**
	 * Update the search name of a release.
	 *
	 * @param int $releaseID
	 * @param string $searchName
	 */
	public function updateReleaseSearchName($releaseID, $searchName)
	{
		if (!is_null($this->sphinxQL)) {
			$old = $this->sphinxQL->queryOneRow(sprintf('SELECT * FROM releases_rt WHERE id = %s', $releaseID));
			if ($old !== false) {
				$this->insertRelease(
					[
						'id' => $releaseID,
						'guid' => $this->sphinxQL->escapeString($old['guid']),
						'name' => $this->sphinxQL->escapeString($old['name']),
						'searchname' => $searchName,
						'fromname' => $this->sphinxQL->escapeString($old['fromname'])
					]
				);
			}
		}
	}

	/**
	 * Truncate a RT index.
	 * @param string $indexName
	 */
	public function truncateRTIndex($indexName)
	{
		if (!is_null($this->sphinxQL)) {
			$this->sphinxQL->queryExec(sprintf('TRUNCATE RTINDEX %s', $indexName));
		}
	}

	/**
	 * Optimize a RT index.
	 * @param string $indexName
	 */
	public function optimizeRTIndex($indexName)
	{
		if (!is_null($this->sphinxQL)) {
			$this->sphinxQL->queryExec(sprintf('OPTIMIZE INDEX %s', $indexName));
		}
	}
}
