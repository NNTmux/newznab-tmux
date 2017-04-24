<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2015, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

/**
 * ### Configuring backend database connections
 *
 * Lithium supports a wide variety relational and non-relational databases, and is designed to allow
 * and encourage you to take advantage of multiple database technologies, choosing the most optimal
 * one for each task.
 *
 * As with other `Adaptable`-based configurations, each database configuration is defined by a name,
 * and an array of information detailing what database adapter to use, and how to connect to the
 * database server. Unlike when configuring other classes, `Connections` uses two keys to determine
 * which class to select. First is the `'type'` key, which specifies the type of backend to
 * connect to. For relational databases, the type is set to `'database'`. For HTTP-based backends,
 * like CouchDB, the type is `'http'`. Some backends have no type grouping, like MongoDB, which is
 * unique and connects via a custom PECL extension. In this case, the type is set to `'MongoDb'`,
 * and no `'adapter'` key is specified. In other cases, the `'adapter'` key identifies the unique
 * adapter of the given type, i.e. `'MySql'` for the `'database'` type, or `'CouchDb'` for the
 * `'http'` type. Note that while adapters are always specified in CamelCase form, types are
 * specified either in CamelCase form, or in underscored form, depending on whether an `'adapter'`
 * key is specified. See the examples below for more details.
 *
 * ### Multiple environments
 *
 * As with other `Adaptable` classes, `Connections` supports optionally specifying different
 * configurations per named connection, depending on the current environment. For information on
 * specifying environment-based configurations, see the `Environment` class.
 *
 * @see lithium\core\Adaptable
 * @see lithium\core\Environment
 */
use lithium\data\Connections;
use Dotenv\Dotenv;

$dotenv = new Dotenv(dirname(__DIR__, 3));
$dotenv->load();


/**
 * Uncomment this configuration to use MongoDB as your default database.
 */
// Connections::add('default', array(
// 	'type' => 'MongoDb',
// 	'host' => 'localhost',
// 	'database' => 'my_app'
// ));

/**
 * Uncomment this configuration to use CouchDB as your default database.
 */
// Connections::add('default', array(
// 	'type' => 'http',
// 	'adapter' => 'CouchDb',
// 	'host' => 'localhost',
// 	'database' => 'my_app'
// ));

/**
 * Uncomment this configuration to use MySQL as your default database.
 *
 * Strict mode can be enabled or disabled, older MySQL versions were
 * by default non-strict.
 */
// Connections::add('default', array(
// 	'type' => 'database',
// 	'adapter' => 'MySql',
// 	'host' => 'localhost',
// 	'login' => 'root',
// 	'password' => '',
// 	'database' => 'my_app',
// 	'encoding' => 'UTF-8',
// 	'strict' => false
// ));

$config1 = LITHIUM_APP_PATH . DS . 'config' . DS . 'db-config.php';
$config2 = NN_ROOT . '.env';
$config = file_exists($config1) ? $config1 : $config2;

if (!defined('NN_INSTALLER')) {
	if (!file_exists($config)) {
		throw new \ErrorException(
			"No valid configuration file found at '$config'"
		);
	}
	require_once $config;

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

	if (isset($adapter)) {
		if (empty(getenv('DB_SOCKET'))) {
			$host = empty(getenv('DB_PORT')) ? getenv('DB_HOST') : getenv('DB_HOST') . ':' . getenv('DB_PORT');
		} else {
			$host = getenv('DB_SOCKET');
		}

		Connections::add('default',
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

		\nntmux\utility\Utility::setCoversConstant(
			\app\models\Settings::value('site.main.coverspath')
		);
	}
} else {
	/** throw new ErrorException("Couldn't open NN's configuration file!"); */
	Connections::add('default',
		[
			'type'     => 'database',
			'adapter'  => 'Mock',
			'host'     => 'localhost',
			'port'     => '3306',
			'login'    => 'root',
			'password' => 'root_pass',
			'database' => 'nntmux',
			'encoding' => 'UTF-8'
		]
	);
}

?>
