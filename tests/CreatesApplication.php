<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Boots the Laravel application for tests.
     */
    public function createApplication()
    {
        // Require the configured application instance.
        $app = require __DIR__.'/../bootstrap/app.php';

        // Bootstrap the console kernel so facades and providers are ready.
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
