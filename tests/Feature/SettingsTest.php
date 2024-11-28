<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SettingsTest extends TestCase
{
    public function test_setting_value(): void
    {
        $name = config('app.name');

        $this->assertEquals('NNTmux', $name);
    }
}
