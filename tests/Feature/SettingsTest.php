<?php

namespace Tests\Feature;

use App\Models\Settings;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function testSettingValue()
    {
        $name = config('app.name');

        $this->assertEquals('NNTmux', $name);
    }
}
