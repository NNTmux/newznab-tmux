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


/**
 * Class InstallTest
 *
 * @package tests
 */
class InstallTest extends \PHPUnit\Framework\TestCase
{
    /**
     *
     */
    public function testFullInstall()
    {
        passthru('php '.NN_ROOT.'artisan migrate:fresh --seed');

        $message = 'NNTmux installation completed successfully';

        $this->assertEquals('NNTmux installation completed successfully', $message, 'Test Failed');
    }
}
