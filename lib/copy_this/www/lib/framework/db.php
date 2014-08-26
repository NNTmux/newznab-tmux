<?php
require_once(WWW_DIR . "/lib/framework/cache.php");
require_once(WWW_DIR . "/lib/util.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/consoletools.php");
require_once(WWW_DIR . "../misc/update_scripts/nix_scripts/tmux/lib/ColorCLI.php");

class DB
{
	/**
	 * The database connection
	 *
	 * @var null|PDO
	 */
	private static $instance = null;

	/**
	 * The database constructor
	 */
	public function __construct()
	{
		$this->cli = Utility::isCLI();
		$this->log = new ColorCLI();

		if (!(self::$instance instanceof PDO)) {
			$dbconnstring = sprintf(
				"%s:host=%s;dbname=%s%s",
				DB_TYPE,
				DB_HOST,
				DB_NAME,
				(defined('DB_PORT') ? ";port=" . DB_PORT : "")
			);
			$errmode = defined('DB_ERRORMODE') ? DB_ERRORMODE : PDO::ERRMODE_SILENT;

			try {
				self::$instance = new PDO(
					$dbconnstring, DB_USER, DB_PASSWORD, array(
						PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
						PDO::ATTR_ERRMODE            => $errmode,
						PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
					)
				);

				if (defined('DB_PCONNECT') && DB_PCONNECT) {
					self::$instance->setAttribute(PDO::ATTR_PERSISTENT, true);
				}
			} catch (PDOException $e) {
				die("fatal error: could not connect to database! Check your config. " . $e);
			}
		}

		$this->ct = new ConsoleTools();
		if (!empty(DB_TYPE)) {
			$this->dbSystem = strtolower(DB_TYPE);
		}
	}

	/**
	 * Init PDO instance.
	 */
	private function initialiseDatabase()
	{


		$dsn = $this->dbSystem . ':host=' . DB_HOST;
		if (!empty(DB_PORT)) {
			$dsn .= ';port=' . DB_PORT;
	}
		$dsn .= ';charset=utf8';

		$options = [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_TIMEOUT => 180,
			\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
			\PDO::MYSQL_ATTR_LOCAL_INFILE => true
		];

		$this->dsn = $dsn;
		// removed try/catch to let the instantiating code handle the problem (Install for
		// instance can output a message that connecting failed.
		self::$instance = new \PDO($dsn, DB_USER, DB_PASSWORD, $options);

		if (DB_NAME != '') {
			self::$instance->query("USE {DB_NAME}");
		}

		// In case PDO is not set to produce exceptions (PHP's default behaviour).
		if (self::$instance === false) {
			$this->echoError(
				"Unable to create connection to the Database!",
				'initialiseDatabase',
				1,
				true
			);
		}

		// For backwards compatibility, no need for a patch.
		self::$instance->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
		self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
	}

	/**
	 * Get the plain PDO connection
	 *
	 * @return PDO
	 */
	public function getPDO()
	{
		return self::$instance;
	}

	/**
	 * Call functions from PDO
	 *
	 * @param $function
	 * @param $args
	 *
	 * @return mixed
	 */
	public function __call($function, $args)
	{
		if (method_exists(self::$instance, $function)) {
			return call_user_func_array(array(self::$instance, $function), $args);
		}
		trigger_error("Unknown PDO Method Called: $function()\n", E_USER_ERROR);
	}

	/**
	 * Escape a string using PDO
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function escapeString($str)
	{
		if (is_null($str)) {
			return 'NULL';
		}

		return self::$instance->quote($str);
	}

	/**
	 * Formats a 'like' string. ex.(LIKE '%chocolate%')
	 *
	 * @param string $str   The string.
	 * @param bool   $left  Add a % to the left.
	 * @param bool   $right Add a % to the right.
	 *
	 * @return string
	 */
	public function likeString($str, $left = true, $right = true)
	{
		return (
			'LIKE ' .
			$this->escapeString(
				($left ? '%' : '') .
				$str .
				($right ? '%' : '')
			)
		);
	}

	/**
	 * Looks up info for index on table.
	 *
	 * @param $table string Table to look at.
	 * @param $index string Index to check.
	 *
	 * @return bool|array False on failure, associative array of SHOW data.
	 */
	public function checkIndex($table, $index)
	{
		$result = $this->query(
			sprintf(
				"SHOW INDEX FROM %s WHERE key_name = '%s'",
				trim($table),
				trim($index)
			)
		);
		if ($result === false) {
			return false;
		}

		return $result->fetch(\PDO::FETCH_ASSOC);
	}

	public function checkColumnIndex($table, $column)
	{
		$result = $this->query(
			sprintf(
				"SHOW INDEXES IN %s WHERE non_unique = 0 AND column_name = '%s'",
				trim($table),
				trim($column)
			)
		);
		if ($result === false) {
			return false;
		}

		return $result->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * PHP interpretation of MySQL's from_unixtime method.
	 *
	 * @param int $utime UnixTime
	 *
	 * @return bool|string
	 */
	public function from_unixtime($utime)
	{
		return 'FROM_UNIXTIME(' . $utime . ')';
	}

	/**
	 * PHP interpretation of mysql's unix_timestamp method.
	 *
	 * @param string $date
	 *
	 * @return int
	 */
	public function unix_timestamp($date)
	{
		return strtotime($date);
	}

	/**
	 * Get a string for MySQL with a column name in between
	 * ie: UNIX_TIMESTAMP(column_name) AS outputName
	 *
	 * @param string $column     The datetime column.
	 * @param string $outputName The name to store the SQL data into. (the word after AS)
	 *
	 * @return string
	 */
	public function unix_timestamp_column($column, $outputName = 'unix_time')
	{
		return ('UNIX_TIMESTAMP(' . $column . ') AS ' . $outputName);
	}

	/**
	 * Reconnect to MySQL when the connection has been lost.
	 *
	 * @see ping(), _checkGoneAway() for checking the connection.
	 *
	 * @return bool
	 */
	protected function _reconnect()
	{
		// Check if we are really connected to MySQL.
		if ($this->ping() === false) {
			// If we are not reconnected, return false.
			return false;
		}
		return true;
	}

	/**
	 * Verify that we've lost a connection to MySQL.
	 *
	 * @param string $errorMessage
	 *
	 * @return bool
	 */
	protected function _checkGoneAway($errorMessage)
	{
		if (stripos($errorMessage, 'MySQL server has gone away') !== false) {
			return true;
		}
		return false;
	}

	/**
	 * Execute a query and return the result or last inserted id
	 *
	 * @param string $query
	 * @param bool   $returnlastid
	 *
	 * @return bool|int
	 */
	public function queryInsert($query, $returnlastid = true)
	{
		if ($query == "")
			return false;

		if (DB_TYPE == "mysql") {
			//$result = $this->exec(utf8_encode($query));
			$result = self::$instance->exec($query);

			return ($returnlastid) ? self::$instance->lastInsertId() : $result;
		} elseif (DB_TYPE == "postgres") {
			$p = self::$instance->prepare($query . ' RETURNING id');
			$p->execute();

			return $p->fetchColumn();
		}
	}

	/**
	 * Perform a query and return the first result
	 *
	 * @param string     $query
	 * @param bool       $useCache
	 * @param string|int $cacheTTL
	 *
	 * @return bool|array
	 */
	public function queryOneRow($query, $useCache = false, $cacheTTL = '')
	{
		if ($query == "")
			return false;

		$rows = $this->query($query, $useCache, $cacheTTL);

		return ($rows ? $rows[0] : false);
	}

	/**
	 * Perform a single query
	 *
	 * @param string     $query
	 * @param bool       $useCache
	 * @param string|int $cacheTTL
	 *
	 * @return bool|array
	 */
	public function query($query, $useCache = false, $cacheTTL = '')
	{
		if ($query == "")
			return false;

		$query = Utility::collapseWhiteSpace($query);

		if ($useCache) {
			$cache = new Cache();
			if ($cache->enabled && $cache->exists($query)) {
				$ret = $cache->fetch($query);
				if ($ret !== false)
					return $ret;
			}
		}

		$result = $this->queryArray($query);

		if ($result === false || $result === true)
			return array();

		if ($useCache)
			if ($cache->enabled)
				$cache->store($query, $result, $cacheTTL);

		return $result;
	}

	/**
	 * Query without returning an empty array like our function query(). http://php.net/manual/en/pdo.query.php
	 *
	 * @param string $query  The query to run.
	 * @param bool   $ignore Ignore errors, do not log them?
	 *
	 * @return bool|\PDOStatement
	 */
	public function queryDirect($query, $ignore = false)
	{
		if (empty($query)) {
			return false;
		}

		$query = Utility::collapseWhiteSpace($query);


		try {
			$result = self::$instance->query($query);
		} catch (\PDOException $e) {

			// Check if we lost connection to MySQL.
			if ($this->_checkGoneAway($e->getMessage()) !== false) {

				// Reconnect to MySQL.
				if ($this->_reconnect() === true) {

					// If we reconnected, retry the query.
					$result = $this->queryDirect($query);

				} else {
					// If we are not reconnected, return false.
					$result = false;
				}

			} else {
				if ($ignore === false) {
					$this->echoError($e->getMessage(), 'queryDirect', 4, false);
				}
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Used for deleting, updating (and inserting without needing the last insert id).
	 *
	 * @param string $query
	 * @param bool   $silent Echo or log errors?
	 *
	 * @return bool|\PDOStatement
	 */
	public function queryExec($query, $silent = false)
	{
		if (empty($query)) {
			return false;
		}

		$query = Utility::collapseWhiteSpace($query);


		$i = 2;
		$error = '';
		while($i < 11) {
			$result = $this->queryExecHelper($query);
			if (is_array($result) && isset($result['deadlock'])) {
				$error = $result['message'];
				if ($result['deadlock'] === true) {
					$this->ct->showsleep($i * ($i/2));
					$i++;
				} else {
					break;
				}
			} elseif ($result === false) {
				$error = 'Unspecified error.';
				break;
			} else {
				return $result;
			}
		}
		if ($silent === false) {
			$this->echoError($error, 'queryExec', 4);
		}
		return false;
	}

	/**
	 * Helper method for queryInsert and queryExec, checks for deadlocks.
	 *
	 * @param string $query
	 * @param bool   $insert
	 *
	 * @return array|\PDOStatement
	 */
	protected function queryExecHelper($query, $insert = false)
	{
		try {
			if ($insert === false ) {
				$run = self::$instance->prepare($query);
				$run->execute();
				return $run;
			} else {
				$ins = self::$instance->prepare($query);
				$ins->execute();
				return self::$instance->lastInsertId();
			}

		} catch (\PDOException $e) {
			// Deadlock or lock wait timeout, try 10 times.
			if (
				$e->errorInfo[1] == 1213 ||
				$e->errorInfo[0] == 40001 ||
				$e->errorInfo[1] == 1205 ||
				$e->getMessage() == 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'
			) {
				return ['deadlock' => true, 'message' => $e->getMessage()];
			}

			// Check if we lost connection to MySQL.
			else if ($this->_checkGoneAway($e->getMessage()) !== false) {

				// Reconnect to MySQL.
				if ($this->_reconnect() === true) {

					// If we reconnected, retry the query.
					return $this->queryExecHelper($query, $insert);

				}
			}

			return ['deadlock' => false, 'message' => $e->getMessage()];
		}
	}

	/**
	 * Get the total number of rows in the result set
	 *
	 * @param PDOStatement $result
	 *
	 * @return int
	 */
	public function getNumRows(PDOStatement $result)
	{
		return $result->rowCount();
	}

	/**
	 * Fetch a assoc row from a result set
	 *
	 * @param PDOStatement $result
	 *
	 * @return array
	 */
	public function getAssocArray(PDOStatement $result)
	{
		return $result->fetch();
	}

	/**
	 * Main method for creating results as an array.
	 *
	 * @param string $query SQL to execute.
	 *
	 * @return array|boolean Array of results on success or false on failure.
	 */
	public function queryArray($query)
	{
		$result = false;
		if (!empty($query)) {
			$result = $this->queryDirect($query);

			if (!empty($result)) {
				$result = $result->fetchAll();
			}
		}

		return $result;
	}

	/**
	 * Returns all results as an associative array.
	 *
	 * Do not use this function for large dat-asets, as it can cripple the Db server and use huge
	 * amounts of RAM. Instead iterate through the data.
	 *
	 * @param string $query The query to execute.
	 *
	 * @return array|boolean Array of results on success, false otherwise.
	 */
	public function queryAssoc($query)
	{
		if ($query == '') {
			return false;
		}
		$mode = self::$instance->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE);
		if ($mode != \PDO::FETCH_ASSOC) {
			self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		}

		$result = $this->queryArray($query);

		if ($mode != \PDO::FETCH_ASSOC) {
			self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		}

		return $result;
	}

	/**
	 * Optimises/repairs tables on mysql.
	 *
	 * @param bool   $admin     If we are on web, don't echo.
	 * @param string $type      'full' | '' Force optimize of all tables.
	 *                          'space'     Optimise tables with 5% or more free space.
	 *                          'analyze'   Analyze tables to rebuild statistics.
	 * @param bool   $local     Only analyze local tables. Good if running replication.
	 * @param array  $tableList (optional) Names of tables to analyze.
	 *
	 * @return int Quantity optimized/analyzed
	 */
	public function optimise($admin = false, $type = '', $local = false, $tableList = [])
	{
		$tableAnd = '';
		if (count($tableList)) {
			foreach ($tableList as $tableName) {
				$tableAnd .= ($this->escapeString($tableName) . ',');
			}
			$tableAnd = (' AND Name IN (' . rtrim($tableAnd, ',') . ')');
		}

		switch ($type) {
			case 'space':
				$tableArray = $this->queryDirect('SHOW TABLE STATUS WHERE Data_free / Data_length > 0.005' . $tableAnd);
				$myIsamTables = $this->queryDirect("SHOW TABLE STATUS WHERE ENGINE LIKE 'myisam' AND Data_free / Data_length > 0.005" . $tableAnd);
				break;
			case 'analyze':
			case '':
			case 'full':
			default:
				$tableArray = $this->queryDirect('SHOW TABLE STATUS WHERE 1=1' . $tableAnd);
				$myIsamTables = $this->queryDirect("SHOW TABLE STATUS WHERE ENGINE LIKE 'myisam'" . $tableAnd);
				break;
		}

		$optimised = 0;
		if ($tableArray instanceof \Traversable && $tableArray->rowCount()) {

			$tableNames = '';
			foreach ($tableArray as $table) {
				$tableNames .= $table['Name'] . ',';
			}
			$tableNames = rtrim($tableNames, ',');

			$local = ($local ? 'LOCAL' : '');
			if ($type === 'analyze') {
				$this->queryExec(sprintf('ANALYZE %s TABLE %s', $local, $tableNames));
				$this->logOptimize($admin, 'ANALYZE', $tableNames);
			} else {

				$this->queryExec(sprintf('OPTIMIZE %s TABLE %s', $local, $tableNames));
				$this->logOptimize($admin, 'OPTIMIZE', $tableNames);

				if ($myIsamTables instanceof \Traversable && $myIsamTables->rowCount()) {
					$tableNames = '';
					foreach ($myIsamTables as $table) {
						$tableNames .= $table['Name'] . ',';
					}
					$tableNames = rtrim($tableNames, ',');
					$this->queryExec(sprintf('REPAIR %s TABLE %s', $local, $tableNames));
					$this->logOptimize($admin, 'REPAIR', $tableNames);
				}
				$this->queryExec(sprintf('FLUSH %s TABLES', $local));
			}
			$optimised = $tableArray->rowCount();
		}

		return $optimised;
	}

	/**
	 * Log/echo repaired/optimized/analyzed tables.
	 *
	 * @param bool   $web    If we are on web, don't echo.
	 * @param string $type   ANALYZE|OPTIMIZE|REPAIR
	 * @param string $tables Table names.
	 *
	 * @access private
	 * @void
	 */
	private function logOptimize($web, $type, $tables)
	{
		$message = $type . ' (' . $tables . ')';
		if ($web === false) {
			echo $this->log->primary($message);

		}
	}

	/**
	 * Checks whether the connection to the server is working. Optionally restart a new connection.
	 * NOTE: Restart does not happen if PDO is not using exceptions (PHP's default configuration).
	 * In this case check the return value === false.
	 *
	 * @param boolean $restart Whether an attempt should be made to reinitialise the Db object on failure.
	 *
	 * @return boolean
	 */
	public function ping($restart = false)
	{
		try {
			return (bool) self::$instance->query('SELECT 1+1');
		} catch (\PDOException $e) {
			if ($restart == true) {
				$this->initialiseDatabase();
			}
			return false;
		}
	}

	/**
	 * Echo error, optionally exit.
	 *
	 * @param string $error The error message.
	 * @param bool   $exit  Exit or not?
	 *
	 * @internal param string $method The method where the error occured.
	 * @internal param int $severity The severity of the error.
	 */
	protected function echoError($error, $exit = false)
	{
			echo(
			($this->cli ? $this->log->error($error) . PHP_EOL : '<div class="error">' . $error . '</div>')
			);
		if ($exit) {
			exit();
		}
	}
}