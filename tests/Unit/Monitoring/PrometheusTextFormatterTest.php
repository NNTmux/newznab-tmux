<?php

declare(strict_types=1);

namespace Tests\Unit\Monitoring;

use App\Services\Monitoring\Prometheus\MetricFamily;
use App\Services\Monitoring\Prometheus\TextFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PrometheusTextFormatterTest extends TestCase
{
    public function test_formats_help_type_and_labelled_samples(): void
    {
        $family = MetricFamily::gauge('nntmux_processing_backlog', 'Releases waiting.')
            ->add(12, ['type' => 'nfo'])
            ->add(3, ['type' => 'tv']);

        $output = (new TextFormatter)->format([$family]);

        $this->assertSame(
            "# HELP nntmux_processing_backlog Releases waiting.\n"
            ."# TYPE nntmux_processing_backlog gauge\n"
            ."nntmux_processing_backlog{type=\"nfo\"} 12\n"
            ."nntmux_processing_backlog{type=\"tv\"} 3\n",
            $output,
        );
    }

    public function test_escapes_label_values_and_help_text(): void
    {
        $family = MetricFamily::gauge('nntmux_test', "Line one\nline \\ two")
            ->add(1, ['server' => "news \"primary\"\\eu\nwest"]);

        $output = (new TextFormatter)->format([$family]);

        $this->assertStringContainsString('# HELP nntmux_test Line one\nline \\\\ two', $output);
        $this->assertStringContainsString('nntmux_test{server="news \"primary\"\\\\eu\nwest"} 1', $output);
    }

    public function test_formats_special_and_fractional_float_values(): void
    {
        $family = MetricFamily::gauge('nntmux_value', 'Values.')
            ->add(NAN, ['case' => 'nan'])
            ->add(INF, ['case' => 'inf'])
            ->add(-INF, ['case' => 'neg_inf'])
            ->add(0.25, ['case' => 'fraction'])
            ->add(2.0, ['case' => 'whole']);

        $output = (new TextFormatter)->format([$family]);

        $this->assertStringContainsString('nntmux_value{case="nan"} NaN', $output);
        $this->assertStringContainsString('nntmux_value{case="inf"} +Inf', $output);
        $this->assertStringContainsString('nntmux_value{case="neg_inf"} -Inf', $output);
        $this->assertStringContainsString('nntmux_value{case="fraction"} 0.25', $output);
        $this->assertStringContainsString('nntmux_value{case="whole"} 2', $output);
    }

    public function test_families_without_samples_are_omitted(): void
    {
        $this->assertSame('', (new TextFormatter)->format([MetricFamily::gauge('nntmux_empty', 'Nothing.')]));
    }

    public function test_rejects_invalid_metric_and_label_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MetricFamily::gauge('nntmux-bad-name', 'Bad.');
    }

    public function test_rejects_invalid_label_names(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MetricFamily::gauge('nntmux_ok', 'Ok.')->add(1, ['bad-label' => 'x']);
    }
}
