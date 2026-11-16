<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Services\Admin\PaymentStatsReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentStatsReportTest extends TestCase
{
    private const string NOW = '2026-11-16 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
    }

    #[Test]
    public function revenue_counts_settled_and_legacy_payments_but_not_unsettled_ones(): void
    {
        Payment::factory()->plan('Supporter 1 year', '15')->paidAt('2026-11-10 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-11-11 10:00:00')->create();
        Payment::factory()->legacy()->plan('Supporter 1 year', '10')->create();
        Payment::factory()->unsettled()->plan('Supporter ++ 2 years', '50')->paidAt('2026-11-12 10:00:00')->create();

        $summary = $this->report()['summary'];

        $this->assertSame(50.0, $summary['revenue']);
        $this->assertSame(4, $summary['payments']);
        $this->assertSame(3, $summary['paid']);
        $this->assertSame(16.67, $summary['average']);
        $this->assertSame(1, $summary['unsettled']);
        $this->assertSame(50.0, $summary['unsettled_amount']);
        $this->assertSame(1, $summary['legacy']);
        $this->assertNull($summary['previous'], 'An open date range has no previous period');
    }

    #[Test]
    public function coins_are_merged_across_method_naming_schemes(): void
    {
        Payment::factory()->method('BTC', '0.001')->plan('Supporter 1 year', '10')->create();
        Payment::factory()->method('BTC-CHAIN', '0.002')->plan('Supporter 2 years', '20')->create();
        Payment::factory()->method('LTC-CHAIN', '0.5')->plan('Supporter 1 year', '10')->create();

        $report = $this->report();

        $this->assertSame(['BTC', 'LTC'], array_column($report['coins'], 'label'));
        $this->assertSame([2, 1], array_column($report['coins'], 'payments'));
        $this->assertSame([30.0, 10.0], array_column($report['coins'], 'revenue'));
        $this->assertSame([75.0, 25.0], array_column($report['coins'], 'share'));
        $this->assertEqualsWithDelta(0.003, $report['coins'][0]['received'], 0.0000001);
        $this->assertSame(['Supporter 1 year', 'Supporter 2 years'], array_column($report['plans'], 'label'));
        $this->assertSame([20.0, 20.0], array_column($report['plans'], 'revenue'));
    }

    #[Test]
    public function a_payment_is_returning_when_the_payer_paid_before_even_outside_the_filter(): void
    {
        Payment::factory()->paidAt('2025-03-01 10:00:00')->create(['username' => 'alice']);
        Payment::factory()->paidAt('2026-11-05 10:00:00')->create(['username' => 'Alice']);
        Payment::factory()->paidAt('2026-11-06 10:00:00')->create(['username' => 'bob']);

        $summary = $this->report(['start_date' => '2026-11-01', 'end_date' => '2026-11-16'])['summary'];

        $this->assertSame(2, $summary['payments']);
        $this->assertSame(2, $summary['payers']);
        $this->assertSame(1, $summary['returning']);
    }

    #[Test]
    public function a_bounded_range_is_compared_with_the_period_of_equal_length_before_it(): void
    {
        Payment::factory()->plan('Supporter 1 year', '10')->paidAt('2026-10-25 10:00:00')->create();
        Payment::factory()->plan('Supporter 1 year', '10')->paidAt('2026-11-02 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-11-09 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-10-10 10:00:00')->create();

        $previous = $this->report(['start_date' => '2026-11-01', 'end_date' => '2026-11-10'])['summary']['previous'];

        $this->assertSame(['2026-10-22', '2026-10-31'], [$previous['start'], $previous['end']]);
        $this->assertSame(10.0, $previous['revenue']);
        $this->assertSame(1, $previous['payments']);
        $this->assertSame(250.0, $previous['revenue_change']);
        $this->assertSame(100.0, $previous['payments_change']);
    }

    #[Test]
    public function the_trend_is_monthly_and_fills_months_without_payments_with_zero(): void
    {
        Payment::factory()->plan('Supporter 1 year', '15')->paidAt('2026-08-03 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-08-20 10:00:00')->create();
        Payment::factory()->unsettled()->plan('Supporter 1 year', '15')->paidAt('2026-10-02 10:00:00')->create();

        $trend = $this->report()['trend'];

        $this->assertSame('month', $trend['unit']);
        $this->assertSame(['Aug 2026', 'Sep 2026', 'Oct 2026', 'Nov 2026'], $trend['labels']);
        $this->assertSame([40.0, 0.0, 0.0, 0.0], $trend['revenue']);
        $this->assertSame([2, 0, 1, 0], $trend['payments']);
    }

    #[Test]
    public function the_trend_is_daily_for_a_short_bounded_range(): void
    {
        Payment::factory()->plan('Supporter 1 year', '15')->paidAt('2026-11-02 23:30:00')->create();

        $trend = $this->report(['start_date' => '2026-11-01', 'end_date' => '2026-11-03'])['trend'];

        $this->assertSame('day', $trend['unit']);
        $this->assertSame(['1 Nov', '2 Nov', '3 Nov'], $trend['labels']);
        $this->assertSame([0.0, 15.0, 0.0], $trend['revenue']);
        $this->assertSame([0, 1, 0], $trend['payments']);
    }

    #[Test]
    public function an_empty_table_reports_zeroes_and_an_empty_trend(): void
    {
        $report = $this->report();

        $this->assertSame(0.0, $report['summary']['revenue']);
        $this->assertSame(0, $report['summary']['payments']);
        $this->assertSame([], $report['plans']);
        $this->assertSame([], $report['trend']['labels']);
    }

    #[Test]
    public function dashboard_stats_cover_the_last_thirty_days_and_the_current_year(): void
    {
        Payment::factory()->plan('Supporter 1 year', '15')->paidAt('2026-11-01 10:00:00')->create();
        Payment::factory()->unsettled()->plan('Supporter 1 year', '15')->paidAt('2026-11-02 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-02-01 10:00:00')->create();
        Payment::factory()->plan('Supporter 2 years', '19')->paidAt('2025-12-31 10:00:00')->create();

        $stats = app(PaymentStatsReport::class)->dashboardStats(CarbonImmutable::parse(self::NOW));

        $this->assertSame(
            ['revenue_30d' => 15.0, 'count_30d' => 2, 'revenue_year' => 40.0, 'count_year' => 3],
            $stats,
        );
    }

    #[Test]
    public function a_payer_summary_ignores_other_payers_and_links_the_user_account(): void
    {
        DB::table('roles')->insert(['id' => 5, 'name' => 'Supporter', 'guard_name' => 'web']);
        DB::table('users')->insert(['id' => 7, 'username' => 'alice', 'email' => 'alice@example.test', 'roles_id' => 5]);
        Payment::factory()->plan('Supporter 1 year', '10')->paidAt('2025-03-01 10:00:00')->create(['username' => 'alice']);
        Payment::factory()->plan('Supporter 2 years', '25')->paidAt('2026-11-05 10:00:00')->create(['username' => 'alice']);
        Payment::factory()->create(['username' => 'bob']);

        $payer = app(PaymentStatsReport::class)->payer('alice');

        $this->assertSame(2, $payer['payments']);
        $this->assertSame(35.0, $payer['revenue']);
        $this->assertSame('2025-03-01', $payer['first_at']?->toDateString());
        $this->assertSame('2026-11-05', $payer['last_at']?->toDateString());
        $this->assertSame(7, $payer['user']?->id);
        $this->assertSame('Supporter', $payer['user']?->role?->name);
        $this->assertNull(app(PaymentStatsReport::class)->payer('nobody'));
    }

    #[Test]
    public function filter_options_list_the_plans_coins_and_statuses_present(): void
    {
        Payment::factory()->method('BTC')->plan('Supporter 2 years', '25')->create();
        Payment::factory()->method('BTC-CHAIN')->plan('Supporter 1 year', '15')->create();
        Payment::factory()->method('XMR-CHAIN')->plan('Supporter 1 year', '15')->create();

        $options = app(PaymentStatsReport::class)->filterOptions();

        $this->assertSame(['Supporter 1 year', 'Supporter 2 years'], $options['plans']);
        $this->assertSame(['BTC', 'XMR'], $options['coins']);
        $this->assertSame(['Settled'], $options['payment_statuses']);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function report(array $filters = []): array
    {
        return app(PaymentStatsReport::class)->build($filters, CarbonImmutable::parse(self::NOW));
    }

    private function createSchema(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
            $table->string('email');
            $table->string('username');
            $table->string('item_description');
            $table->string('order_id');
            $table->string('payment_id');
            $table->string('payment_status');
            $table->string('invoice_status')->nullable()->default('Pending');
            $table->string('invoice_amount');
            $table->string('payment_method');
            $table->string('payment_value');
            $table->string('webhook_id');
            $table->string('invoice_id');
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email');
            $table->unsignedInteger('roles_id')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
