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

use nntmux\db\DB;
use nntmux\Users;

class UsersTest extends \PHPUnit_Framework_TestCase
{
	const USERNAME = 'test';
	const PASSWORD = '1234567';
	const EMAIL = 'example@example.com';

	public function testUsersAdd() {
		$pdo = new DB();
		$users = new Users(['Settings' => $pdo]);
		$users->add(self::USERNAME, self::PASSWORD, self::EMAIL, '2', '', '');
		$userTest = $users->getByEmail(self::EMAIL);
		$this->assertEquals(self::EMAIL, $userTest['email']);
	}
}
