<?php

namespace Blacklight\db;

use Ramsey\Uuid\Uuid;
use App\Models\Settings;
use Blacklight\ColorCLI;
use Blacklight\ConsoleTools;
use Blacklight\libraries\Cache;
use Blacklight\utility\Utility;
use Blacklight\libraries\CacheException;

/**
 * Class for handling connection to MySQL database using PDO.
 *
 * The class extends PDO, thereby exposing all of PDO's functionality directly
 * without the need to wrap each and every method here.
 *
 * Exceptions are caught and displayed to the user.
 * Properties are explicitly created, so IDEs can offer autocompletion for them.
 * @extends \PDO
 */
class DB extends \PDO
{
    /**
     * @var bool
     */
    public $cli;

    /**
     * @var mixed
     */
    public $ct;

    /**
     * @var \Blacklight\ColorCLI	Instance variable for logging object. Currently only ColorCLI supported,
     * but expanding for full logging with agnostic API planned.
     */
    public $log;

    /**
     * @note Setting this static causes issues when creating multiple instances of this class with different
     *       MySQL servers, the next instances re-uses the server of the first instance.
     * @var \PDO Instance of PDO class.
     */
    public $pdo = null;

    /**
     * @var bool
     */
    protected $_debug;

    /**
     * @var \Blacklight\Logger
     */
    private $debugging;

    /**
     * @var string Lower-cased name of DBMS in use.
     */
    private $dbSystem;

    /**
     * @var string Version of the Db server.
     */
    private $dbVersion;

    /**
     * @var string	Stored copy of the dsn used to connect.
     */
    private $dsn;

    /**
     * @var array    Options passed into the constructor or defaulted.
     */
    private $opts;

    /**
     * @var \Blacklight\libraries\Cache
     */
    private $cacheServer;

    /**
     * @var bool Should we cache the results of the query method?
     */
    private $cacheEnabled = false;

    /**
     * @var string MySQL LOW_PRIORITY DELETE option.
     */
    private $DELETE_LOW_PRIORITY = '';

    /**
     * @var string MYSQL QUICK DELETE option.
     */
    private $DELETE_QUICK = '';

    /**
     * Constructor. Sets up all necessary properties. Instantiates a PDO object
     * if needed, otherwise returns the current one.
     *
     * @param array $options
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $this->cli = Utility::isCLI();

        $defaults = [
            'checkVersion'    => false,
            'createDb'        => false, // create dbname if it does not exist?
            'ct'            => new ConsoleTools(),
            'dbhost'        => env('DB_HOST', '127.0.0.1'),
            'dbname'        => env('DB_NAME', 'nntmux'),
            'dbpass'        => env('DB_PASSWORD', 'nntmux'),
            'dbport'        => env('DB_PORT', '3306'),
            'dbsock'        => env('DB_SOCKET', ''),
            'dbtype'        => env('DB_SYSTEM', 'mysql'),
            'dbuser'        => env('DB_USER', 'nntmux'),
            'log'            => new ColorCLI(),
            'persist'        => false,
        ];
        $options += $defaults;

        if (! $this->cli) {
            $options['log'] = null;
        }
        $this->opts = $options;

        if (! empty($this->opts['dbtype'])) {
            $this->dbSystem = strtolower($this->opts['dbtype']);
        }

        if (! ($this->pdo instanceof \PDO)) {
            $this->initialiseDatabase();
        }

        $this->cacheEnabled = \defined('NN_CACHE_TYPE') && (NN_CACHE_TYPE > 0);

        if ($this->cacheEnabled) {
            try {
                $this->cacheServer = new Cache();
            } catch (CacheException $error) {
                $this->cacheEnabled = false;
                $this->echoError($error->getMessage(), '__construct', 4);
            }
        }

        $this->ct = $this->opts['ct'];
        $this->log = $this->opts['log'];

        if ($this->opts['checkVersion']) {
            $this->fetchDbVersion();
        }

        if (\defined('NN_SQL_DELETE_LOW_PRIORITY') && NN_SQL_DELETE_LOW_PRIORITY) {
            $this->DELETE_LOW_PRIORITY = ' LOW_PRIORITY ';
        }

        if (\defined('NN_SQL_DELETE_QUICK') && NN_SQL_DELETE_QUICK) {
            $this->DELETE_QUICK = ' QUICK ';
        }
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    public function checkDbExists($name = null)
    {
        if (empty($name)) {
            $name = $this->opts['dbname'];
        }

        $found = false;
        $tables = $this->getTableList();
        foreach ($tables as $table) {
            if ($table['Database'] === $name) {
                $found = true;
                break;
            }
        }

        return $found;
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
        $result = $this->pdo->query(
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
        $result = $this->pdo->query(
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

    public function getTableList()
    {
        $result = $this->pdo->query('SHOW DATABASES');

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Attempts to determine if the Db is on the local machine.
     *
     * If the method returns true, then the Db is definitely on the local machine. However,
     * returning false only indicates that it could not positively be determined to be local - so
     * assume remote.
     *
     * @return bool Whether the Db is definitely on the local machine.
     */
    public function isLocalDb(): bool
    {
        $local = false;
        if (! empty($this->opts['dbsock']) || $this->opts['dbhost'] === 'localhost') {
            $local = true;
        } else {
            preg_match_all('/inet'.'6?'.' addr: ?([^ ]+)/', `ifconfig`, $ips);

            // Check for dotted quad - if exists compare against local IP number(s)
            if (preg_match('#^\d+\.\d+\.\d+\.\d+$#', $this->opts['dbhost'])) {
                if (\in_array($this->opts['dbhost'], $ips[1], false)) {
                    $local = true;
                }
            }
        }

        return $local;
    }

    /**
     * Init PDO instance.
     *
     * @throws \RuntimeException
     */
    private function initialiseDatabase()
    {
        if (! empty($this->opts['dbsock'])) {
            $dsn = $this->dbSystem.':unix_socket='.$this->opts['dbsock'];
        } else {
            $dsn = $this->dbSystem.':host='.$this->opts['dbhost'];
            if (! empty($this->opts['dbport'])) {
                $dsn .= ';port='.$this->opts['dbport'];
            }
        }
        $dsn .= ';charset=utf8';

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 180,
            \PDO::ATTR_PERSISTENT => $this->opts['persist'],
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ];

        $this->dsn = $dsn;
        // removed try/catch to let the instantiating code handle the problem (Install for
        // instance can output a message that connecting failed.
        $this->pdo = new \PDO($dsn, $this->opts['dbuser'], $this->opts['dbpass'], $options);

        if ($this->opts['dbname'] !== '') {
            if ($this->opts['createDb']) {
                $found = $this->checkDbExists();
                if ($found) {
                    try {
                        $this->pdo->exec('DROP DATABASE '.$this->opts['dbname']);
                    } catch (\Exception $e) {
                        throw new \RuntimeException("Error trying to drop your old database: '{$this->opts['dbname']}'", 2);
                    }
                    $found = $this->checkDbExists();
                }

                if ($found) {
                    throw new \RuntimeException("Could not drop your old database: '{$this->opts['dbname']}'", 2);
                }
                $this->pdo->exec("CREATE DATABASE `{$this->opts['dbname']}`  DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");

                if (! $this->checkDbExists()) {
                    throw new \RuntimeException("Could not create new database: '{$this->opts['dbname']}'", 3);
                }
            }
            $this->pdo->exec("USE {$this->opts['dbname']}");
        }

        // In case PDO is not set to produce exceptions (PHP's default behaviour).
        if ($this->pdo === false) {
            $this->echoError(
                'Unable to create connection to the Database!',
                'initialiseDatabase',
                1,
                true
            );
        }

        // For backwards compatibility, no need for a patch.
        $this->pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * Echo error, optionally exit.
     *
     * @param string     $error    The error message.
     * @param string     $method   The method where the error occured.
     * @param int        $severity The severity of the error.
     * @param bool       $exit     Exit or not?
     */
    protected function echoError($error, $method, $severity, $exit = false)
    {
        if ($this->_debug) {
            $this->debugging->log(__CLASS__, $method, $error, $severity);

            echo
            $this->cli ? ColorCLI::error($error).PHP_EOL : '<div class="error">'.$error.'</div>';
        }

        if ($exit) {
            exit();
        }
    }

    /**
     * @return string mysql.
     */
    public function DbSystem(): string
    {
        return $this->dbSystem;
    }

    /**
     * Returns a string, escaped with single quotes, false on failure. http://www.php.net/manual/en/pdo.quote.php.
     *
     * @param string $str
     *
     * @return string
     */
    public function escapeString($str): string
    {
        if ($str === null) {
            return 'NULL';
        }

        return $this->pdo->quote($str);
    }

    /**
     * Formats a 'like' string. ex.(LIKE '%chocolate%').
     *
     * @param string $str    The string.
     * @param bool   $left   Add a % to the left.
     * @param bool   $right  Add a % to the right.
     *
     * @return string
     */
    public function likeString($str, $left = true, $right = true): string
    {
        return 'LIKE '.$this->escapeString(($left ? '%' : '').$str.($right ? '%' : ''));
    }

    /**
     * Verify if pdo var is instance of PDO class.
     *
     * @return bool
     */
    public function isInitialised(): bool
    {
        return $this->pdo instanceof \PDO;
    }

    /**
     * For inserting a row. Returns last insert ID. queryExec is better if you do not need the id.
     *
     * @param string $query
     *
     * @return int|false|string
     */
    public function queryInsert($query)
    {
        if (! $this->parseQuery($query)) {
            return false;
        }

        $i = 2;
        $error = '';
        while ($i < 11) {
            $result = $this->queryExecHelper($query, true);
            if (\is_array($result) && isset($result['deadlock'])) {
                $error = $result['message'];
                if ($result['deadlock'] === true) {
                    $this->echoError(
                        'A Deadlock or lock wait timeout has occurred, sleeping. ('.
                        ($i - 1).')',
                        'queryInsert',
                        4
                    );
                    $this->ct->showsleep($i * ($i / 2));
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

        return false;
    }

    /**
     * Delete rows from MySQL.
     *
     * @param string $query
     * @param bool   $silent Echo or log errors?
     *
     * @return bool|\PDOStatement
     */
    public function queryDelete($query, $silent = false)
    {
        // Accommodate for chained queries (SELECT 1;DELETE x FROM y)
        if (preg_match('#(.*?[^a-z0-9]|^)DELETE\s+(.+?)$#is', $query, $matches)) {
            $query = $matches[1].'DELETE '.$this->DELETE_LOW_PRIORITY.$this->DELETE_QUICK.$matches[2];
        }

        return $this->queryExec($query, $silent);
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
        if (! $this->parseQuery($query)) {
            return false;
        }

        $i = 2;
        $error = '';
        while ($i < 11) {
            $result = $this->queryExecHelper($query);
            if (\is_array($result) && isset($result['deadlock'])) {
                $error = $result['message'];
                if ($result['deadlock'] === true) {
                    $this->echoError('A Deadlock or lock wait timeout has occurred, sleeping. ('.($i - 1).')', 'queryExec', 4);
                    $this->ct->showsleep($i * ($i / 2));
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

        return false;
    }

    /**
     * Helper method for queryInsert and queryExec, checks for deadlocks.
     *
     * @param $query
     * @param bool $insert
     * @return array|\PDOStatement|string
     * @throws \RuntimeException
     */
    protected function queryExecHelper($query, $insert = false)
    {
        try {
            if ($insert === false) {
                return $this->pdo->query($query);
            }
            $this->pdo->exec($query);

            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            // Deadlock or lock wait timeout, try 10 times.
            if (
                $e->errorInfo[1] === 1213 ||
                $e->errorInfo[0] === 40001 ||
                $e->errorInfo[1] === 1205 ||
                $e->getMessage() === 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'
            ) {
                return ['deadlock' => true, 'message' => $e->getMessage()];
            }

            // Check if we lost connection to MySQL.
            if ($this->_checkGoneAway($e->getMessage()) !== false) {

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
     * Direct query. Return the affected row count. http://www.php.net/manual/en/pdo.exec.php.
     *
     * @note If not "consumed", causes this error:
     *       'SQLSTATE[HY000]: General error: 2014 Cannot execute queries while other unbuffered queries are active.
     *        Consider using PDOStatement::fetchAll(). Alternatively, if your code is only ever going to run against mysql,
     *        you may enable query buffering by setting the PDO::MYSQL_ATTR_USE_BUFFERED_QUERY attribute.'
     *
     * @param string $query
     * @param bool $silent Whether to skip echoing errors to the console.
     *
     * @return bool|int|\PDOStatement
     * @throws \RuntimeException
     */
    public function exec($query, $silent = false)
    {
        if (! $this->parseQuery($query)) {
            return false;
        }

        try {
            return $this->pdo->exec($query);
        } catch (\PDOException $e) {

            // Check if we lost connection to MySQL.
            if ($this->_checkGoneAway($e->getMessage()) !== false) {

                // Reconnect to MySQL.
                if ($this->_reconnect() === true) {

                    // If we reconnected, retry the query.
                    return $this->exec($query, $silent);
                } else {
                    // If we are not reconnected, return false.
                    return false;
                }
            } elseif (! $silent) {
                $this->echoError($e->getMessage(), 'Exec', 4, false);
            }

            return false;
        }
    }

    /**
     * Returns an array of result (empty array if no results or an error occurs)
     * Optional: Pass true to cache the result with a cache server.
     *
     * @param string $query       SQL to execute.
     * @param bool   $cache       Indicates if the query result should be cached.
     * @param int    $cacheExpiry The time in seconds before deleting the query result from the cache server.
     *
     * @return array|bool Array of results (possibly empty) on success, empty array on failure.
     */
    public function query($query, $cache = false, $cacheExpiry = 600): array
    {
        if (! $this->parseQuery($query)) {
            return false;
        }

        if ($cache === true && $this->cacheEnabled === true) {
            try {
                $data = $this->cacheServer->get($this->cacheServer->createKey($query));
                if ($data !== false) {
                    return $data;
                }
            } catch (CacheException $error) {
                $this->echoError($error->getMessage(), 'query', 4);
            }
        }

        $result = $this->queryArray($query);

        if ($result !== false && $cache === true && $this->cacheEnabled === true) {
            $this->cacheServer->set($this->cacheServer->createKey($query), $result, $cacheExpiry);
        }

        return ($result === false) ? [] : $result;
    }

    /**
     * Returns a multidimensional array of result of the query function return and the count of found rows
     * Note: Query passed to this function SHOULD include SQL_CALC_FOUND_ROWS
     * Optional: Pass true to cache the result with a cache server.
     *
     * @param string $query       SQL to execute.
     * @param bool   $cache       Indicates if the query result should be cached.
     * @param int    $cacheExpiry The time in seconds before deleting the query result from the cache server.
     *
     * @return array Array of results (possibly empty) on success, empty array on failure.
     */
    public function queryCalc($query, $cache = false, $cacheExpiry = 600): array
    {
        $data = $this->query($query, $cache, $cacheExpiry);

        if (strpos($query, 'SQL_CALC_FOUND_ROWS') === false) {
            return $data;
        }

        // Remove LIMIT and OFFSET from query to allow queryCalc usage with browse
        $query = preg_replace('#(\s+LIMIT\s+\d+)?\s+OFFSET\s+\d+\s*$#i', '', $query);

        if ($cache === true && $this->cacheEnabled === true) {
            try {
                $count = $this->cacheServer->get($this->cacheServer->createKey($query.'count'));
                if ($count !== false) {
                    return ['total' => $count, 'result' => $data];
                }
            } catch (CacheException $error) {
                $this->echoError($error->getMessage(), 'queryCalc', 4);
            }
        }

        $result = $this->queryOneRow('SELECT FOUND_ROWS() AS total');

        if ($result !== false && $cache === true && $this->cacheEnabled === true) {
            $this->cacheServer->set($this->cacheServer->createKey($query.'count'), $result['total'], $cacheExpiry);
        }

        return
            [
                'total' => $result === false ? 0 : $result['total'],
                'result' => $data,
            ];
    }

    /**
     * Main method for creating results as an array.
     *
     * @param string $query SQL to execute.
     *
     * @return array|bool Array of results on success or false on failure.
     */
    public function queryArray($query)
    {
        $result = false;
        if (! empty($query)) {
            $result = $this->queryDirect($query);

            if (! empty($result)) {
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
     * @return array|bool Array of results on success, false otherwise.
     */
    public function queryAssoc($query)
    {
        if ($query === '') {
            return false;
        }
        $mode = $this->pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE);
        if ($mode !== \PDO::FETCH_ASSOC) {
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }

        $result = $this->queryArray($query);

        if ($mode !== \PDO::FETCH_ASSOC) {
            // Restore old mode
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $mode);
        }

        return $result;
    }

    /**
     * Query without returning an empty array like our function query(). http://php.net/manual/en/pdo.query.php.
     *
     * @param string $query The query to run.
     * @param bool $ignore Ignore errors, do not log them?
     *
     * @return bool|\PDOStatement
     * @throws \RuntimeException
     */
    public function queryDirect($query, $ignore = false)
    {
        if (! $this->parseQuery($query)) {
            return false;
        }

        try {
            $result = $this->pdo->query($query);
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
     * Reconnect to MySQL when the connection has been lost.
     *
     * @see ping(), _checkGoneAway() for checking the connection.
     *
     * @return bool
     * @throws \RuntimeException
     */
    protected function _reconnect(): bool
    {
        $this->initialiseDatabase();

        // Check if we are really connected to MySQL.
        return ! ($this->ping() === false);
    }

    /**
     * Verify that we've lost a connection to MySQL.
     *
     * @param string $errorMessage
     *
     * @return bool
     */
    protected function _checkGoneAway($errorMessage): bool
    {
        return stripos($errorMessage, 'MySQL server has gone away') !== false;
    }

    /**
     * Returns the first row of the query.
     *
     * @param string $query
     * @param bool   $appendLimit
     *
     * @return array|bool
     */
    public function queryOneRow($query, $appendLimit = true)
    {
        // Force the query to only return 1 row, so queryArray doesn't potentially run out of memory on a large data set.
        // First check if query already contains a LIMIT clause.
        if (preg_match('#\s+LIMIT\s+(?P<lower>\d+)(,\s+(?P<upper>\d+))?(;)?$#i', $query, $matches)) {
            if (! isset($matches['upper']) && isset($matches['lower']) && $matches['lower'] == 1) {
                // good it's already correctly set.
            } else {
                // We have a limit, but it's not for a single row
                return false;
            }
        } elseif ($appendLimit) {
            $query .= ' LIMIT 1';
        }

        $rows = $this->query($query);
        if (! $rows || \count($rows) === 0) {
            $rows = false;
        }

        return \is_array($rows) ? $rows[0] : $rows;
    }

    /**
     * Optimises/repairs tables on mysql.
     *
     * @param bool $admin If we are on web, don't echo.
     * @param string $type 'full' | '' Force optimize of all tables.
     *                         'space'     Optimise tables with 5% or more free space.
     *                         'analyze'   Analyze tables to rebuild statistics.
     * @param bool|string $local Only analyze local tables. Good if running replication.
     * @param array $tableList (optional) Names of tables to analyze.
     *
     * @return int Quantity optimized/analyzed
     * @throws \RuntimeException
     */
    public function optimise($admin = false, $type = '', $local = false, $tableList = [])
    {
        $tableAnd = '';
        if (\count($tableList)) {
            foreach ($tableList as $tableName) {
                $tableAnd .= ($this->escapeString($tableName).',');
            }
            $tableAnd = (' AND Name IN ('.rtrim($tableAnd, ',').')');
        }

        switch ($type) {
            case 'space':
                $tableArray = $this->queryDirect('SHOW TABLE STATUS WHERE Data_free / Data_length > 0.005'.$tableAnd);
                $myIsamTables = $this->queryDirect("SHOW TABLE STATUS WHERE ENGINE LIKE 'myisam' AND Data_free / Data_length > 0.005".$tableAnd);
                break;
            case 'analyze':
            case '':
            case 'full':
            default:
                $tableArray = $this->queryDirect('SHOW TABLE STATUS WHERE 1=1'.$tableAnd);
                $myIsamTables = $this->queryDirect("SHOW TABLE STATUS WHERE ENGINE LIKE 'myisam'".$tableAnd);
                break;
        }

        $optimised = 0;
        if ($tableArray instanceof \Traversable && $tableArray->rowCount()) {
            $tableNames = '';
            foreach ($tableArray as $table) {
                $tableNames .= $table['name'].',';
            }
            $tableNames = rtrim($tableNames, ',');

            $local = $local ? 'LOCAL' : '';
            if ($type === 'analyze') {
                $this->queryExec(sprintf('ANALYZE %s TABLE %s', $local, $tableNames));
                $this->logOptimize($admin, 'ANALYZE', $tableNames);
            } else {
                $this->queryExec(sprintf('OPTIMIZE %s TABLE %s', $local, $tableNames));
                $this->logOptimize($admin, 'OPTIMIZE', $tableNames);

                if ($myIsamTables instanceof \Traversable && $myIsamTables->rowCount()) {
                    $tableNames = '';
                    foreach ($myIsamTables as $table) {
                        $tableNames .= $table['name'].',';
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
     * @void
     */
    private function logOptimize($web, $type, $tables)
    {
        $message = $type.' ('.$tables.')';
        if ($web === false) {
            echo ColorCLI::primary($message);
        }
    }

    /**
     * Turns off autocommit until commit() is ran. http://www.php.net/manual/en/pdo.begintransaction.php.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (NN_USE_SQL_TRANSACTIONS) {
            return $this->pdo->beginTransaction();
        }

        return true;
    }

    /**
     * Commits a transaction. http://www.php.net/manual/en/pdo.commit.php.
     *
     * @return bool
     */
    public function Commit(): bool
    {
        if (NN_USE_SQL_TRANSACTIONS) {
            return $this->pdo->commit();
        }

        return true;
    }

    /**
     * Rollback transcations. http://www.php.net/manual/en/pdo.rollback.php.
     *
     * @return bool
     */
    public function Rollback(): bool
    {
        if (NN_USE_SQL_TRANSACTIONS) {
            return $this->pdo->rollBack();
        }

        return true;
    }

    public function setCovers()
    {
        $path = Settings::settingValue([
            'section'    => 'site',
            'subsection' => 'main',
            'name'       => 'coverspath',
        ]);
        Utility::setCoversConstant($path);
    }

    public function rowToArray(array $row)
    {
        $this->settings[$row['setting']] = $row['value'];
    }

    public function rowsToArray(array $rows)
    {
        foreach ($rows as $row) {
            if (\is_array($row)) {
                $this->rowToArray($row);
            }
        }

        return $this->settings;
    }

    public function settingsUpdate($form)
    {
        $error = $this->settingsValidate($form);

        if ($error === null) {
            $sql = $sqlKeys = [];
            foreach ($form as $settingK => $settingV) {
                $sql[] = sprintf(
                    'WHEN %s THEN %s',
                    $this->escapeString(trim($settingK)),
                    $this->escapeString(trim($settingV))
                );
                $sqlKeys[] = $this->escapeString(trim($settingK));
            }

            $this->queryExec(
                sprintf(
                    'UPDATE settings SET value = CASE setting %s END WHERE setting IN (%s)',
                    implode(' ', $sql),
                    implode(', ', $sqlKeys)
                )
            );
        } else {
            $form = $error;
        }

        return $form;
    }

    protected function settingsValidate(array $fields)
    {
        $defaults = [
            'checkpasswordedrar' => false,
            'ffmpegpath'         => '',
            'mediainfopath'      => '',
            'nzbpath'            => '',
            'tmpunrarpath'       => '',
            'unrarpath'          => '',
            'yydecoderpath'      => '',
        ];
        $fields += $defaults;    // Make sure keys exist to avoid error notices.
        ksort($fields);
        // Validate settings
        $fields['nzbpath'] = Utility::trailingSlash($fields['nzbpath']);
        $error = null;
        switch (true) {
            case $fields['mediainfopath'] !== '' && ! is_file($fields['mediainfopath']):
                $error = Settings::ERR_BADMEDIAINFOPATH;
                break;
            case $fields['ffmpegpath'] !== '' && ! is_file($fields['ffmpegpath']):
                $error = Settings::ERR_BADFFMPEGPATH;
                break;
            case $fields['unrarpath'] !== '' && ! is_file($fields['unrarpath']):
                $error = Settings::ERR_BADUNRARPATH;
                break;
            case empty($fields['nzbpath']):
                $error = Settings::ERR_BADNZBPATH_UNSET;
                break;
            case ! file_exists($fields['nzbpath']) || ! is_dir($fields['nzbpath']):
                $error = Settings::ERR_BADNZBPATH;
                break;
            case ! is_readable($fields['nzbpath']):
                $error = Settings::ERR_BADNZBPATH_UNREADABLE;
                break;
            case (int) $fields['checkpasswordedrar'] === 1 && ! is_file($fields['unrarpath']):
                $error = Settings::ERR_DEEPNOUNRAR;
                break;
            case $fields['tmpunrarpath'] !== '' && ! file_exists($fields['tmpunrarpath']):
                $error = Settings::ERR_BADTMPUNRARPATH;
                break;
            case $fields['yydecoderpath'] !== '' &&
                $fields['yydecoderpath'] !== 'simple_php_yenc_decode' &&
                ! file_exists($fields['yydecoderpath']):
                $error = Settings::ERR_BAD_YYDECODER_PATH;
        }

        return $error;
    }

    /**
     * PHP interpretation of MySQL's from_unixtime method.
     * @param int  $utime UnixTime
     *
     * @return string
     */
    public function from_unixtime($utime): string
    {
        return 'FROM_UNIXTIME('.$utime.')';
    }

    /**
     * PHP interpretation of mysql's unix_timestamp method.
     * @param string $date
     *
     * @return int
     */
    public function unix_timestamp($date): int
    {
        return strtotime($date);
    }

    /**
     * Get a string for MySQL with a column name in between
     * ie: UNIX_TIMESTAMP(column_name) AS outputName.
     *
     * @param string $column     The datetime column.
     * @param string $outputName The name to store the SQL data into. (the word after AS)
     *
     * @return string
     */
    public function unix_timestamp_column($column, $outputName = 'unix_time'): string
    {
        return 'UNIX_TIMESTAMP('.$column.') AS '.$outputName;
    }

    /**
     * @return string
     */
    public function uuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Checks whether the connection to the server is working. Optionally restart a new connection.
     * NOTE: Restart does not happen if PDO is not using exceptions (PHP's default configuration).
     * In this case check the return value === false.
     *
     * @param bool $restart Whether an attempt should be made to reinitialise the Db object on failure.
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function ping($restart = false): ?bool
    {
        try {
            return (bool) $this->pdo->query('SELECT 1+1');
        } catch (\PDOException $e) {
            if ($restart === true) {
                $this->initialiseDatabase();
            }

            return false;
        }
    }

    /**
     * Prepares a statement to be run by the Db engine.
     * To run the statement use the returned $statement with ->execute();.
     *
     * Ideally the signature would have array before $options but that causes a strict warning.
     *
     * @param string $query SQL query to run, with optional place holders.
     * @param array $options Driver options.
     *
     * @return false|\PDOstatement on success false on failure.
     *
     * @link http://www.php.net/pdo.prepare.php
     */
    public function Prepare($query, $options = [])
    {
        try {
            $PDOstatement = $this->pdo->prepare($query, $options);
        } catch (\PDOException $e) {
            echo ColorCLI::error("\n".$e->getMessage());
            $PDOstatement = false;
        }

        return $PDOstatement;
    }

    /**
     * Retrieve db attributes http://us3.php.net/manual/en/pdo.getattribute.php.
     *
     * @param int $attribute
     *
     * @return false|mixed
     */
    public function getAttribute($attribute)
    {
        $result = false;
        if ($attribute !== '') {
            try {
                $result = $this->pdo->getAttribute($attribute);
            } catch (\PDOException $e) {
                echo ColorCLI::error("\n".$e->getMessage());
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Returns the stored Db version string.
     *
     * @return string
     */
    public function getDbVersion(): string
    {
        return $this->dbVersion;
    }

    /**
     * @param string $requiredVersion The minimum version to compare against
     *
     * @return bool|null       TRUE if Db version is greater than or eaqual to $requiredVersion,
     * false if not, and null if the version isn't available to check against.
     */
    public function isDbVersionAtLeast($requiredVersion): ?bool
    {
        if (empty($this->dbVersion)) {
            return null;
        }

        return version_compare($requiredVersion, $this->dbVersion, '<=');
    }

    /**
     * Performs the fetch from the Db server and stores the resulting Major.Minor.Version number.
     */
    private function fetchDbVersion()
    {
        $result = $this->queryOneRow('SELECT VERSION() AS version');
        if (! empty($result)) {
            $dummy = explode('-', $result['version'], 2);
            $this->dbVersion = $dummy[0];
        }
    }

    /**
     * Checks if the query is empty. Cleans the query of whitespace if needed.
     *
     * @param string $query
     *
     * @return bool
     */
    private function parseQuery(&$query): bool
    {
        if (empty($query)) {
            return false;
        }

        if (NN_QUERY_STRIP_WHITESPACE) {
            $query = Utility::collapseWhiteSpace($query);
        }

        return true;
    }
}
