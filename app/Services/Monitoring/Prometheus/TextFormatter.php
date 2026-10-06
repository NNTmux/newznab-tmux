<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Prometheus;

/**
 * Renders metric families in the Prometheus text exposition format 0.0.4.
 */
final class TextFormatter
{
    public const string CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /**
     * @param  iterable<MetricFamily>  $families
     */
    public function format(iterable $families): string
    {
        $lines = [];

        foreach ($families as $family) {
            if ($family->samples() === []) {
                continue;
            }

            $lines[] = '# HELP '.$family->name.' '.$this->escapeHelp($family->help);
            $lines[] = '# TYPE '.$family->name.' '.$family->type;

            foreach ($family->samples() as $sample) {
                $lines[] = $family->name.$this->formatLabels($sample['labels']).' '.$this->formatValue($sample['value']);
            }
        }

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $pairs = [];

        foreach ($labels as $name => $value) {
            $pairs[] = $name.'="'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value).'"';
        }

        return '{'.implode(',', $pairs).'}';
    }

    private function formatValue(float|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_nan($value)) {
            return 'NaN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        $formatted = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function escapeHelp(string $help): string
    {
        return str_replace(['\\', "\n"], ['\\\\', '\\n'], $help);
    }
}
