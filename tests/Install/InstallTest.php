<?php
/**
 * This file is part of NNTmux.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package       NNTmux
 * @author        DariusIII
 * @copyright (c) 2017, NNTmux
 * @version       0.0.1
 */

namespace tests;

require_once \dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bootstrap/autoload.php';

use App\Extensions\util\Versions;
use App\Models\Settings;

/**
 * Class InstallTest
 *
 * @package tests
 */
class InstallTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @throws \Cz\Git\GitException
     */
	public function testFullInstall()
	{
        $error = false;

        passthru('php '.NN_ROOT.'artisan migrate:fresh --seed');

        // Check one of the standard tables was created and has data.
        $patch = (new Versions())->getSQLPatchFromFile();
        $updateSettings = false;
        if ($patch > 0) {
            $updateSettings = Settings::query()->where(['section' => '', 'subsection' => '', 'name' => 'sqlpatch'])->update(['value' => $patch]);
        }
        // If it all worked, continue the install process.
        if ($updateSettings !== false) {
            $message = 'Database updated successfully';
        } else {
            $error = true;
            $message = 'Could not update sqlpatch to '.$patch.' for your database.';
        }

        if (! $error) {
            $message = 'NNTmux installation completed successfully';
        }
		$this->assertEquals('NNTmux installation completed successfully', $message, 'Test Failed');
	}
}
