<?php

/**
 <?php

/**
 * This file is part of NNTmux.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author        DariusIII
 * @copyright (c) 2017, NNTmux
 *
 * @version       0.0.1
 */

namespace Tests\Install;

use Tests\TestCase;

/**
 * Class InstallTest.
 */
final class InstallTest extends TestCase
{
    public function test_full_install(): void
    {
        $this->artisan('migrate:fresh', ['--seed' => true])
            ->assertExitCode(0);

        $this->assertTrue(true, 'NNTmux installation completed successfully');
    }
}
