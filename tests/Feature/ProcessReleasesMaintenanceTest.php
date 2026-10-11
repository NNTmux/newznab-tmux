<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ForkingService;
use App\Services\ReleaseProcessingService;
use App\Services\Runners\BackfillRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConfigurationTestBuilder;
use Tests\TestCase;

class ProcessReleasesMaintenanceTest extends TestCase
{
    public function test_end_of_cycle_requests_maintenance_without_a_group_marker(): void
    {
        $service = new class extends ForkingService
        {
            /** @var string|list<string> */
            public string|array $command = [];

            public function __construct()
            {
                $this->backfillRunner = new BackfillRunner;
            }

            public function finishCycle(): void
            {
                $this->processReleasesEndWork();
            }

            protected function getReleaseWorkCount(): int
            {
                return 137;
            }

            protected function executeCommand(string|array $command): string
            {
                $this->command = $command;

                return '';
            }
        };

        $service->finishCycle();

        $this->assertSame([PHP_BINARY, base_path('artisan'), 'releases:process', '--maintenance-only'], $service->command);
    }

    public function test_maintenance_only_never_reprocesses_collections(): void
    {
        $this->prepareMaintenanceDatabase();

        $this->artisan('releases:process', ['--maintenance-only' => true])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(Schema::hasTable('collections'));
        $queries = array_column(DB::getQueryLog(), 'query');
        $this->assertTrue(collect($queries)->contains(static fn (string $query): bool => str_contains($query, '"releases"') && str_contains($query, '"iscategorized"')));
    }

    #[DataProvider('invalidGroupIds')]
    public function test_malformed_group_marker_cannot_trigger_global_processing(string $groupId): void
    {
        $this->prepareMaintenanceDatabase();
        $this->app->instance(ReleaseProcessingService::class, $this->app->make(ReleaseProcessingService::class));
        DB::flushQueryLog();

        $this->artisan('releases:process', ['groupId' => $groupId])->assertExitCode(Command::FAILURE);

        $this->assertSame([], DB::getQueryLog());
    }

    /** @return array<string, array{string}> */
    public static function invalidGroupIds(): array
    {
        return [
            'legacy end marker' => ['137_'],
            'group name' => ['alt.binaries.test'],
            'negative' => ['-1'],
            'zero' => ['0'],
            'fraction' => ['1.5'],
            'scientific notation' => ['1e2'],
        ];
    }

    public function test_maintenance_only_rejects_a_group_id(): void
    {
        $this->prepareMaintenanceDatabase();
        $this->app->instance(ReleaseProcessingService::class, $this->app->make(ReleaseProcessingService::class));
        DB::flushQueryLog();

        $this->artisan('releases:process', ['groupId' => '137', '--maintenance-only' => true])->assertExitCode(Command::FAILURE);

        $this->assertSame([], DB::getQueryLog());
    }

    private function prepareMaintenanceDatabase(): void
    {
        config(['nntmux.echocli' => false]);
        ConfigurationTestBuilder::installDefaults();
        foreach (['usenet_groups', 'releases', 'categories', 'genres'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->increments('id');
                if ($name === 'usenet_groups') {
                    $table->string('name');
                    $table->boolean('active')->default(false);
                } elseif ($name === 'releases') {
                    $table->string('guid');
                    $table->string('name');
                    $table->string('fromname');
                    $table->string('searchname');
                    $table->unsignedInteger('groups_id');
                    $table->unsignedInteger('categories_id');
                    $table->boolean('iscategorized')->default(false);
                    $table->dateTime('adddate');
                    $table->dateTime('postdate');
                    $table->integer('completion')->default(0);
                } else {
                    $table->integer('status')->default(1);
                    $table->boolean('disabled')->default(false);
                    $table->bigInteger('minsizetoformrelease')->default(0);
                }
            });
        }
        DB::enableQueryLog();
    }
}
