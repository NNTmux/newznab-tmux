<?php
/**
 * This file is part of NNTmux.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package NNTmux
 * @author DariusIII
 * @copyright (c) 2017, NNTmux
 * @version 0.0.1
 */

namespace tests;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bootstrap.php';
include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'nntmux' . DIRECTORY_SEPARATOR . 'constants.php';

use app\extensions\util\Versions;
use nntmux\config\Configure;
use nntmux\db\DB;
use nntmux\db\DbUpdate;
use nntmux\ColorCLI;
use nntmux\Users;

/**
 * Class InstallTest
 *
 * @package tests
 */
class InstallTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Configure
	 */
	public $config;


	public function testFullInstall()
	{
		if (!defined('NN_INSTALLER')) {
			define('NN_INSTALLER', true);
		}

		$this->config = new Configure('install');

		$pdo = new DB();
		$error = false;

		if (file_exists(NN_ROOT . '_install/install.lock')) {
			ColorCLI::doEcho(ColorCLI::notice('Installation is locked. If you want to reinstall NNTmux, please remove install.lock file from _install folder. ' . PHP_EOL . ColorCLI::warning('This will wipe your database!')));
			exit();
		}

		// Check if user selected right DB type.
		if (getenv('DB_SYSTEM') !== 'mysql') {
			ColorCLI::doEcho(ColorCLI::error('Invalid database system. Must be: mysql ; Not: ' . getenv('DB_SYSTEM')));
			$error = true;
		} else {
			// Connect to the SQL server.
			try {
				// HAS to be DB because settings table does not exist yet.
				$pdo = new DB(
					[
						'checkVersion' => true,
						'createDb'     => true,
						'dbhost'       => getenv('DB_HOST'),
						'dbname'       => getenv('DB_NAME'),
						'dbpass'       => getenv('DB_PASSWORD'),
						'dbport'       => getenv('DB_PORT'),
						'dbsock'       => getenv('DB_SOCKET'),
						'dbtype'       => getenv('DB_SYSTEM'),
						'dbuser'       => getenv('DB_USER'),
					]
				);
				$dbConnCheck = true;
			} catch (\PDOException $e) {
				ColorCLI::doEcho(ColorCLI::error('Unable to connect to MySQL server.'));
				$error = true;
				$dbConnCheck = false;
			} catch (\RuntimeException $e) {
				switch ($e->getCode()) {
					case 1:
					case 2:
					case 3:
						$error = true;
						ColorCLI::doEcho(ColorCLI::alternate($e->getMessage()));
						break;
					default:
						throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
				}
			}

			// Check if the MySQL version is correct.
			$goodVersion = false;
			if (!$error) {
				try {
					$goodVersion = $pdo->isDbVersionAtLeast(NN_MINIMUM_MYSQL_VERSION);
				} catch (\PDOException $e) {
					$goodVersion = false;
					$error = true;
					ColorCLI::doEcho(ColorCLI::error('Could not get version from MySQL server.'));
				}

				if ($goodVersion === false) {
					$error = true;
					ColorCLI::doEcho(ColorCLI::error(
						'You are using an unsupported version of ' .
						getenv('DB_SYSTEM') .
						' the minimum allowed version is ' .
						NN_MINIMUM_MYSQL_VERSION
					)
					);
				}
			}
		}

// Start inserting data into the DB.
		if (!$error) {
			$DbSetup = new DbUpdate(
				[
					'backup' => false,
					'db'     => $pdo,
				]
			);

			try {
				$DbSetup->processSQLFile(); // Setup default schema
				$DbSetup->processSQLFile( // Process any custom stuff.
					[
						'filepath' => NN_RES . 'db' . DS . 'schema' . DS . 'mysql-data.sql'
					]
				);
				$DbSetup->loadTables(); // Load default data files
			} catch (\PDOException $err) {
				$error = true;
				ColorCLI::doEcho(ColorCLI::error('Error inserting: (' . $err->getMessage() . ')'));
			}

			if (!$error) {
				// Check one of the standard tables was created and has data.
				$dbInstallWorked = false;
				$reschk = $pdo->query('SELECT COUNT(id) AS num FROM tmux');
				if ($reschk === false) {
					$dbCreateCheck = false;
					$error = true;
					ColorCLI::doEcho(ColorCLI::warningOver('Could not select data from your database, check that tables and data are properly created/inserted.'));
				} else {
					foreach ($reschk as $row) {
						if ($row['num'] > 0) {
							$dbInstallWorked = true;
							break;
						}
					}
				}
				$ver = new Versions();
				$patch = $ver->getSQLPatchFromFile();
				if ($dbInstallWorked) {
					$updateSettings = false;
					if ($patch > 0) {
						$updateSettings = $pdo->exec(
							"UPDATE settings SET value = '$patch' WHERE section = '' AND subsection = '' AND name = 'sqlpatch'"
						);
					}
					// If it all worked, continue the install process.
					if ($updateSettings) {
						$message = 'Database updated successfully';
						echo $message . PHP_EOL;
					} else {
						$error = true;
						$message = 'Could not update sqlpatch to ' . $patch . ' for your database.';
						echo $message . PHP_EOL;
					}
				} else {
					$dbCreateCheck = false;
					$error = true;
					ColorCLI::doEcho(ColorCLI::warning('Could not select data from your database.'));
				}
			}
		}
//Insert admin user into database
		if (getenv('ADMIN_USER') === '' || getenv('ADMIN_PASS') === '' || getenv('ADMIN_EMAIL') === '') {
			$error = true;
			ColorCLI::doEcho(ColorCLI::error('Admin user data cannot be empty! Please edit .env file and fill in admin user details and run this script again!'));
			exit();
		}

		switch (getenv('DB_SYSTEM')) {
			case 'mysql':
				$adapter = 'MySql';
				break;
			case 'pgsql':
				$adapter = 'PostgreSql';
				break;
			default:
				break;
		}

		if ($adapter !== null) {
			if (empty(getenv('DB_SOCKET'))) {
				$host = empty(getenv('DB_PORT')) ? getenv('DB_HOST') : getenv('DB_HOST') . ':' . getenv('DB_PORT');
			} else {
				$host = getenv('DB_SOCKET');
			}

			\lithium\data\Connections::add('default',
				[
					'type'       => 'database',
					'adapter'    => $adapter,
					'host'       => $host,
					'login'      => getenv('DB_USER'),
					'password'   => getenv('DB_PASSWORD'),
					'database'   => getenv('DB_NAME'),
					'encoding'   => 'UTF-8',
					'persistent' => false,
				]
			);
		}

		$user = new Users();
		if (!$user->isValidUsername(getenv('ADMIN_USER'))) {
			$error = true;
		} else {
			$usrCheck = $user->getByUsername(getenv('ADMIN_USER'));
			if ($usrCheck) {
				$error = true;
			}
		}
		if (!$user->isValidEmail(getenv('ADMIN_EMAIL'))) {
			$error = true;
		}

		if (!$error) {
			$adminCheck = $user->add(getenv('ADMIN_USER'), getenv('ADMIN_PASS'), getenv('ADMIN_EMAIL'), 2, '', '');
			if (!is_numeric($adminCheck)) {
				$error = true;
			}
		}

		if (!$error) {
			$doCheck = true;

			$covers_path = NN_RES . 'covers' . DS;
			$nzb_path = NN_RES . 'nzb' . DS;
			$tmp_path = NN_RES . 'tmp' . DS;
			$unrar_path = $tmp_path . 'unrar' . DS;


			$nzbPathCheck = is_writable($nzb_path);
			if ($nzbPathCheck === false) {
				$error = true;
				$message = $nzb_path . ' is not writable. Please fix folder permissions';
				echo $message . PHP_EOL;
			}

			$lastchar = substr($nzb_path, strlen($nzb_path) - 1);
			if ($lastchar !== '/') {
				$nzb_path .= '/';
			}

			if (!file_exists($unrar_path)) {
				ColorCLI::doEcho(ColorCLI::primary('Creating missing ' . $unrar_path . ' folder'));
				if (!@mkdir($unrar_path) && !is_dir($unrar_path)) {
					throw new \RuntimeException('Unable to create ' . $unrar_path . ' folder');
				}
				$message = 'Folder ' . $unrar_path . ' successfully created';
				echo $message;
			}
			$unrarPathCheck = is_writable($unrar_path);
			if ($unrarPathCheck === false) {
				$error = true;
				$message = $unrar_path . ' is not writable. Please fix folder permissions';
				echo $message . PHP_EOL;
			}

			$lastchar = substr($unrar_path, strlen($unrar_path) - 1);
			if ($lastchar !== '/') {
				$unrar_path .= '/';
			}

			$coversPathCheck = is_writable($covers_path);
			if ($coversPathCheck === false) {
				$error = true;
				$message = $covers_path . ' is not writable. Please fix folder permissions';
				echo $message . PHP_EOL;
			}

			$lastchar = substr($covers_path, strlen($covers_path) - 1);
			if ($lastchar !== '/') {
				$covers_path .= '/';
			}

			if (!$error) {

				$sql1 = sprintf("UPDATE settings SET value = %s WHERE setting = 'nzbpath'", $pdo->escapeString($nzb_path));
				$sql2 = sprintf("UPDATE settings SET value = %s WHERE setting = 'tmpunrarpath'", $pdo->escapeString($unrar_path));
				$sql3 = sprintf("UPDATE settings SET value = %s WHERE setting = 'coverspath'", $pdo->escapeString($covers_path));
				if ($pdo->queryExec($sql1) === false || $pdo->queryExec($sql2) === false || $pdo->queryExec($sql3) === false) {
					$error = true;
				} else {
					$message = 'Settings table updated successfully';
					echo $message . PHP_EOL;
				}
			}
		}

		if (!$error) {
			@file_put_contents(NN_ROOT . '_install/install.lock', '');
			$message = 'NNTmux installation completed successfully';
			echo $message . PHP_EOL;
		} else {
			$message = 'NNTmux installation failed. Please fix reported problems and run this script again';
			echo $message . PHP_EOL;
		}
		$this->assertEquals('NNTmux installation completed successfully', $message, 'Test Failed');
	}
}
