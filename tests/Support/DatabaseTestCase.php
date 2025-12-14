<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Trait DatabaseTestCase
 *
 * A trait for tests that need database access but should use an isolated
 * SQLite in-memory database instead of the real database.
 *
 * This trait:
 * - Ensures the test uses an in-memory SQLite database
 * - Runs migrations for clean test state
 * - Prevents tests from affecting production data
 *
 * Usage:
 *   use Tests\Support\DatabaseTestCase;
 *
 *   class MyTest extends TestCase
 *   {
 *       use DatabaseTestCase;
 *       // ...
 *   }
 */
trait DatabaseTestCase
{
    use RefreshDatabase;

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    protected function setUpDatabaseTestCase(): void
    {
        // Ensure we're using the testing connection (SQLite in-memory)
        if (config('database.default') !== 'testing') {
            config(['database.default' => 'testing']);
        }
    }

    /**
     * Boot the testing trait for this class.
     *
     * @return void
     */
    protected function setUpTraits(): array
    {
        $uses = parent::setUpTraits();

        if (isset($uses[DatabaseTestCase::class])) {
            $this->setUpDatabaseTestCase();
        }

        return $uses;
    }
}

