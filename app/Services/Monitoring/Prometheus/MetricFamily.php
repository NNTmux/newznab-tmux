<?php

declare(strict_types=1);

namespace App\Services\Monitoring\Prometheus;

use InvalidArgumentException;

/**
 * One Prometheus metric family: a name, type, help text and its samples.
 */
final class MetricFamily
{
    public const string GAUGE = 'gauge';

    public const string COUNTER = 'counter';

    /**
     * @var list<array{labels: array<string, string>, value: float|int}>
     */
    private array $samples = [];

    public function __construct(
        public readonly string $name,
        public readonly string $help,
        public readonly string $type = self::GAUGE,
    ) {
        if (preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid Prometheus metric name [{$name}].");
        }

        if (! in_array($type, [self::GAUGE, self::COUNTER], true)) {
            throw new InvalidArgumentException("Unsupported Prometheus metric type [{$type}].");
        }
    }

    public static function gauge(string $name, string $help): self
    {
        return new self($name, $help, self::GAUGE);
    }

    /**
     * @param  array<string, string|int|float>  $labels
     */
    public function add(float|int $value, array $labels = []): self
    {
        $normalized = [];

        foreach ($labels as $label => $labelValue) {
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $label) !== 1) {
                throw new InvalidArgumentException("Invalid Prometheus label name [{$label}].");
            }

            $normalized[$label] = (string) $labelValue;
        }

        $this->samples[] = ['labels' => $normalized, 'value' => $value];

        return $this;
    }

    /**
     * @return list<array{labels: array<string, string>, value: float|int}>
     */
    public function samples(): array
    {
        return $this->samples;
    }
}
