<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Settings;

class SettingsTest extends TestCase
{
    public function testSettingValue()
    {
        $name = Settings::settingValue('site.main.title');

        $this->assertEquals('NNTmux', $name);
    }
}
