<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Support\HtmlString;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication; // Boot Laravel application for tests.

    protected function setUp(): void
    {
        parent::setUp();

        if (is_file(public_path('build/manifest.json'))) {
            return;
        }

        $this->app->singleton(ViteManager::class, static fn (): ViteManager => new class extends ViteManager
        {
            /**
             * Avoid requiring compiled frontend assets while rendering Blade views in tests.
             */
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }
}
