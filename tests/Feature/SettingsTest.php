<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SettingsTest extends TestCase
{
    public function testSettingValue(): void
    {
        $name = config('app.name');

        $this->assertEquals('NNTmux', $name);
    }
}
