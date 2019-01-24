<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 24.1.19.
 * Time: 11.41
 */

namespace app\Models;

use App\Models\Settings;

class SettingsTest extends \PHPUnit_Framework_TestCase
{
    public function testSettingValue()
    {
        $name = Settings::settingValue('site.main.title');

        $this->assertEquals('NNTmux', $name);
    }
}
