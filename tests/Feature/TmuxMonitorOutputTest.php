<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tmux\Tmux;
use App\Services\Tmux\TmuxMonitorService;
use App\Services\Tmux\TmuxOutput;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TmuxMonitorOutputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'nntmux_nntp.server' => '127.0.0.1',
            'nntmux_nntp.use_alternate_nntp_server' => false,
        ]);
        DB::purge('sqlite');
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        foreach (['running' => '1', 'post' => '3', 'sequential' => '0', 'delaytime' => '2', 'monitor_delay' => '1', 'backfill_days' => '0'] as $name => $value) {
            DB::table('settings')->insert(['name' => $name, 'value' => $value]);
        }
        Process::fake(['git *' => Process::result('test')]);
    }

    public function test_missing_query_setting_defaults_to_off_and_full_monitor_display_renders(): void
    {
        $this->assertSame(0, (new Tmux)->getMonitorSettings()['show_query']);

        $display = $this->renderMonitor();

        $this->assertStringContainsString('Monitor Running', $display);
        $this->assertStringContainsString('Collections', $display);
        $this->assertStringContainsString('PP Lists', $display);
        $this->assertStringNotContainsString('Query Block', $display);
    }

    public function test_display_accepts_older_monitor_state_without_optional_query_setting(): void
    {
        $this->assertStringContainsString('Monitor Running', $this->renderMonitor(omitQuerySetting: true));
    }

    public function test_lookup_switches_refresh_each_cycle_without_waiting_for_statistics_interval(): void
    {
        DB::table('settings')->where('name', 'monitor_delay')->update(['value' => '300']);
        DB::table('settings')->insert([
            ['name' => 'post_non', 'value' => '0'],
            ['name' => 'post_amazon', 'value' => '0'],
            ['name' => 'lookupimdb', 'value' => '0'],
        ]);
        $monitor = new class extends TmuxMonitorService
        {
            public int $operationalRefreshes = 0;

            public int $slowRefreshes = 0;

            protected function refreshOperationalStatistics(): void
            {
                $this->operationalRefreshes++;
            }

            protected function refreshSlowStatistics(): void
            {
                $this->slowRefreshes++;
            }

            protected function updateConnectionCounts(): void {}
        };
        $monitor->initializeMonitor();
        $this->assertSame(0, $monitor->collectStatistics()['settings']['post_non']);

        DB::table('settings')->whereIn('name', ['post_non', 'post_amazon', 'lookupimdb'])->update(['value' => '1']);
        $updated = $monitor->collectStatistics();

        $this->assertSame(1, $updated['settings']['post_non']);
        $this->assertSame(1, $updated['settings']['post_amazon']);
        $this->assertSame(1, $updated['settings']['processmovies']);
        $this->assertSame(1, $monitor->operationalRefreshes);
        $this->assertSame(1, $monitor->slowRefreshes);

        DB::table('settings')->whereIn('name', ['post_non', 'post_amazon'])->update(['value' => '0']);
        $disabled = $monitor->collectStatistics();
        $this->assertSame(0, $disabled['settings']['post_non']);
        $this->assertSame(0, $disabled['settings']['post_amazon']);
    }

    #[DataProvider('querySettings')]
    public function test_explicit_query_setting_is_respected(string $value, bool $visible): void
    {
        DB::table('settings')->insert(['name' => 'showquery', 'value' => $value]);

        $display = $this->renderMonitor();

        if ($visible) {
            $this->assertStringContainsString('Query Block', $display);
        } else {
            $this->assertStringNotContainsString('Query Block', $display);
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function querySettings(): iterable
    {
        yield 'enabled' => ['1', true];
        yield 'disabled' => ['0', false];
    }

    private function renderMonitor(bool $omitQuerySetting = false): string
    {
        $runVar = (new TmuxMonitorService)->initializeMonitor();
        $runVar['conncounts'] = ['primary' => ['active' => 0, 'total' => 0]];
        if ($omitQuerySetting) {
            unset($runVar['settings']['show_query']);
        }
        $output = new TmuxOutput;
        $pdo = $this->createMock(PDO::class);
        $pdo->method('getAttribute')->with(PDO::ATTR_SERVER_INFO)->willReturn('Threads: 1 Questions: 1 Slow queries: 0 Opens: 1 Open tables: 1 Queries per second avg: 1');
        $output->pdo = $pdo;

        ob_start();
        try {
            $output->updateMonitorPane($runVar);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
