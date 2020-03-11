<?php

namespace Tests\Feature;

use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function testSettingValue()
    {
        $name = config('app.name');

        $this->assertEquals('NNTmux', $name);
    }
}
