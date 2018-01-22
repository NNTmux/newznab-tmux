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
use App\Models\User;
use nntmux\config\Configure;
use nntmux\ColorCLI;

/**
 * Class InstallTest
 *
 * @package tests
 */
class InstallTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @var Configure
	 */
	public $config;


	public function testFullInstall()
	{
        if (! \defined('NN_INSTALLER')) {
            \define('NN_INSTALLER', true);
        }

        $error = false;

        passthru('php '.NN_ROOT.'artisan migrate:fresh --seed');

        // Check one of the standard tables was created and has data.
        $ver = new Versions();
        $patch = $ver->getSQLPatchFromFile();
        $updateSettings = false;
        if ($patch > 0) {
            $updateSettings = Settings::query()->where(['section' => '', 'subsection' => '', 'name' => 'sqlpatch'])->update(['value' => $patch]);
        }
        // If it all worked, continue the install process.
        if ($updateSettings === 0) {
            $message = 'Database updated successfully';
        } else {
            $error = true;
            $message = 'Could not update sqlpatch to '.$patch.' for your database.';
        }

        if (! $error) {

            $covers_path = NN_RES.'covers'.DS;
            $nzb_path = NN_RES.'nzb'.DS;
            $tmp_path = NN_RES.'tmp'.DS;
            $unrar_path = $tmp_path.'unrar'.DS;

            $nzbPathCheck = is_writable($nzb_path);
            if ($nzbPathCheck === false) {
                $error = true;
                $message = $nzb_path.' is not writable. Please fix folder permissions';
            }

            $lastchar = substr($nzb_path, \strlen($nzb_path) - 1);
            if ($lastchar !== '/') {
                $nzb_path .= '/';
            }

            if (! file_exists($unrar_path)) {
                if (! @mkdir($unrar_path) && ! is_dir($unrar_path)) {
                    throw new \RuntimeException('Unable to create '.$unrar_path.' folder');
                }
            }
            $unrarPathCheck = is_writable($unrar_path);
            if ($unrarPathCheck === false) {
                $error = true;
                $message = $unrar_path.' is not writable. Please fix folder permissions';
            }

            $lastchar = substr($unrar_path, \strlen($unrar_path) - 1);
            if ($lastchar !== '/') {
                $unrar_path .= '/';
            }

            $coversPathCheck = is_writable($covers_path);
            if ($coversPathCheck === false) {
                $error = true;
                $message = $covers_path.' is not writable. Please fix folder permissions';
            }

            $lastchar = substr($covers_path, \strlen($covers_path) - 1);
            if ($lastchar !== '/') {
                $covers_path .= '/';
            }

            if (! $error) {
                $sql1 = Settings::query()->where('setting', '=', 'nzbpath')->update(['value' => $nzb_path]);
                $sql2 = Settings::query()->where('setting', '=', 'tmpunrarpath')->update(['value' => $unrar_path]);
                $sql3 = Settings::query()->where('setting', '=', 'coverspath')->update(['value' => $covers_path]);
                if ($sql1 === null || $sql2 === null || $sql3 === null) {
                    $error = true;
                } else {
                    $message = 'Settings table updated successfully';
                }
            }
        }

        if (! $error) {

           User::add(env('ADMIN_USER'), env('ADMIN_PASS'), env('ADMIN_EMAIL'), 2, '', '', '', '');

            @file_put_contents(NN_ROOT.'_install/install.lock', '');
            passthru('php '.NN_ROOT.'artisan key:generate');
            $message = 'NNTmux installation completed successfully';
        }
		$this->assertEquals('NNTmux installation completed successfully', $message, 'Test Failed');
	}
}
