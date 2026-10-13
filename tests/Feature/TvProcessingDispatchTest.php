<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\Runners\PostProcessRunner;
use App\Services\Tmux\Tmux;
use App\Services\TvProcessing\TvProcessingPipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use PDO;
use Tests\Support\ConfigurationTestBuilder;
use Tests\Support\TvProcessingSchema;
use Tests\TestCase;

class TvProcessingDispatchTest extends TestCase
{
    use TvProcessingSchema;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['nntmux.stream_fork_output' => false]);
        $this->createTvProcessingSchema();
    }

    public function test_runner_dispatches_due_buckets_without_the_legacy_mode_option(): void
    {
        $this->insertTvRelease('Due.S01E01', ['leftguid' => 'a']);
        $this->insertTvRelease('Deferred.S01E01', ['leftguid' => 'b', 'tv_lookup_retry_at' => now()->addHour()]);
        $this->insertTvRelease('Legacy.S01E01', ['leftguid' => 'c', 'tv_episodes_id' => -1]);

        $runner = $this->recordingRunner();
        $runner->processTv(false);

        $this->assertCount(1, $runner->dispatched);
        $this->assertSame(['postprocess:tv-pipeline', 'a', '1'], \array_slice($runner->dispatched[0], 2));
    }

    public function test_runner_passes_the_renamed_flag_when_the_lookup_mode_is_renamed_only(): void
    {
        ConfigurationTestBuilder::update(ConfigurationDomain::Metadata, ['tv_lookup' => 2]);
        $this->insertTvRelease('Renamed.S01E01', ['leftguid' => 'a', 'isrenamed' => 1]);
        $this->insertTvRelease('Not.Renamed.S01E01', ['leftguid' => 'b', 'isrenamed' => 0]);

        $runner = $this->recordingRunner();
        $runner->processTv(false);

        $this->assertSame([['postprocess:tv-pipeline', 'a', '2']], array_map(static fn (array $command): array => \array_slice($command, 2), $runner->dispatched));
    }

    public function test_runner_finds_no_work_when_every_release_is_deferred(): void
    {
        $this->insertTvRelease('Deferred.S01E01', ['tv_lookup_retry_at' => now()->addHour()]);

        $this->assertFalse(app(PostProcessRunner::class)->hasTvWork(false));
    }

    public function test_tmux_ready_count_matches_what_the_workers_pick_up(): void
    {
        $this->insertTvRelease('Due.S01E01');
        $this->insertTvRelease('Deferred.S01E01', ['tv_lookup_retry_at' => now()->addHour()]);
        $this->insertTvRelease('Missing.Episode.S01E01', ['videos_id' => 3, 'tv_episodes_id' => -11]);

        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('IF', static fn (int $condition, int $yes, int $no): int => $condition ? $yes : $no, 3);
        $sql = (string) app(Tmux::class)->proc_query(1, '', '');
        $line = array_values(array_filter(explode("\n", $sql), static fn (string $line): bool => str_contains($line, 'AS processtv')))[0];

        $this->assertSame(1, DB::selectOne('SELECT '.rtrim(trim($line), ',').' FROM releases')->processtv);
    }

    public function test_pipeline_command_ignores_the_deprecated_mode_option(): void
    {
        $this->mock(TvProcessingPipeline::class, function (MockInterface $pipeline): void {
            $pipeline->shouldReceive('process')->once()->with('', 'a', '2');
        });

        $this->artisan('postprocess:tv-pipeline', ['guid' => 'a', 'renamed' => '2', '--mode' => 'parallel'])
            ->expectsOutputToContain('deprecated')
            ->assertSuccessful();
    }

    /**
     * @return PostProcessRunner&object{dispatched: list<list<string>>}
     */
    private function recordingRunner(): PostProcessRunner
    {
        return new class extends PostProcessRunner
        {
            /** @var list<list<string>> */
            public array $dispatched = [];

            protected function headerStart(string $workType, int $count, int $maxProcesses): void {}

            protected function headerNone(): void {}

            protected function runParallelCommands(array $commands, int $maxProcesses, ?int $timeout = null): array
            {
                foreach ($commands as $command) {
                    $this->dispatched[] = array_map('strval', (array) $command);
                }

                return [];
            }
        };
    }
}
