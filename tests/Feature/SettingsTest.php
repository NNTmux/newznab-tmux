<?php
/**
 * Created by PhpStorm.
 * User: darius
 * Date: 24.1.19.
 * Time: 11.41
 */

namespace Tests\Feature;

use App\Models\Settings;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function testSettingValue()
    {
        $name = Settings::settingValue('site.main.title');

        $this->assertEquals('NNTmux', $name);
    }
}
