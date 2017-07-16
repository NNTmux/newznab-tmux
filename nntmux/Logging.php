<?php
namespace nntmux;

use App\models\Settings;
use nntmux\db\DB;

/**
 * Logs/Reports stuff
 */
class Logging
{
	/**
	 * @var string If windows "\r\n" if unix "\n".
	 * @access private
	 */
	private $newLine;

	/**
	 * @var DB Class instance.
	 * @access public
	 */
	public $pdo;

	/**
	 * @var ColorCLI
	 * @access public
	 */
	public $colorCLI;

	/**
	 * Constructor.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = [])
	{
		$defaults = [
			'Settings' => null,
		];
		$options += $defaults;

		$this->pdo = ($options['Settings'] instanceof DB ? $options['Settings'] : new DB());

		$this->newLine = PHP_EOL;
	}

	/**
	 * Get all rows from logging table.
	 *
	 * @return array
	 *
	 * @access public
	 */
	public function get(): array
	{
		return $this->pdo->query('SELECT * FROM logging');
	}

	/**
	 * Log bad login attempts.
	 *
	 * @param string $username
	 * @param string $host
	 *
	 * @return void
	 * @throws \Exception
	 *
	 * @access public
	 */
	public function LogBadPasswd($username = '', $host = ''): void
	{
		// If logggingopt is = 0, then we do nothing, 0 = logging off.
		$loggingOpt = Settings::value('site.main.loggingopt');
		$logFile = Settings::value('site.main.logfile');
		if ((int)$loggingOpt === 1) {
			$this->pdo->queryInsert(sprintf('INSERT INTO logging (time, username, host) VALUES (NOW(), %s, %s)',
				$this->pdo->escapeString($username), $this->pdo->escapeString($host)));
		} else if ((int)$loggingOpt === 2) {
			$this->pdo->queryInsert(sprintf('INSERT INTO logging (time, username, host) VALUES (NOW(), %s, %s)',
				$this->pdo->escapeString($username), $this->pdo->escapeString($host)));
			$logData = date('M d H:i:s ') . 'Login Failed for ' . $username . ' from ' . $host . '.' .
				$this->newLine;
			if ($logFile !== null) {
				file_put_contents($logFile, $logData, FILE_APPEND);
			}
		} else if ((int)$loggingOpt === 3) {
			$logData = date('M d H:i:s ') . 'Login Failed for ' . $username . ' from ' . $host . '.' . $this->newLine;
			if ($logFile !== null) {
				file_put_contents($logFile, $logData, FILE_APPEND);
			}
		}
	}

	/**
	 * @return array
	 *
	 * @access public
	 */
	public function getTopCombined(): array
	{
		return $this->pdo->query('SELECT MAX(time) AS time, username, host, COUNT(host) AS count FROM logging GROUP BY host, username ORDER BY count DESC LIMIT 10');
	}

	/**
	 * @return array
	 *
	 * @access public
	 */
	public function getTopIPs(): array
	{
		return $this->pdo->query('SELECT MAX(time) AS time, host, COUNT(host) AS count FROM logging GROUP BY host ORDER BY count DESC LIMIT 10');
	}
}
