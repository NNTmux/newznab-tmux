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

include_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'nntmux' . DIRECTORY_SEPARATOR . 'constants.php';

use lithium\data\Connections;
use Dotenv\Dotenv;
use nntmux\config\Configure;
use nntmux\db\DB;
use nntmux\Users;

class UsersTest extends \PHPUnit_Framework_TestCase
{
	const USERNAME = 'test';
	const PASSWORD = '1234567';
	const EMAIL = 'example@example.com';

	public function testUsersAdd() {

		if (!defined('NN_INSTALLER')) {
			define('NN_INSTALLER', true);
		}
		$config = new Configure('install');


		$dotenv = new Dotenv(NN_ROOT  . DS . 'tests' . DS , '.env.test');
		$dotenv->load();
		$pdo = new DB(
				[
				'checkVersion' => true,
				'createDb'     => true,
				'dbhost'       => '127.0.0.1',
				'dbname'       => 'NNTMUX',
				'dbpass'       => 'NNTMUX',
				'dbport'       => '3306',
				'dbsock'       => '',
				'dbtype'       => 'mysql',
				'dbuser'       => 'NNTMUX'
				]
		);
		$users = new Users(['Settings' => $pdo]);

		Connections::add('default',
			[
				'type'       => 'database',
				'adapter'    => 'MySql',
				'host'       => '127.0.0.1:3306',
				'login'      => 'NNTMUX',
				'password'   => 'NNTMUX',
				'database'   => 'NNTMUX',
				'encoding'   => 'UTF-8',
				'persistent' => false,
			]
		);
		$users->add(self::USERNAME, self::PASSWORD, self::EMAIL, '2', '', '');
		$userTest = $users->getByEmail(self::EMAIL);
		$this->assertEquals(self::EMAIL, $userTest['email']);
	}
}
