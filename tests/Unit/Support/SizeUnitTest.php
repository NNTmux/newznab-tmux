<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SizeUnit;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SizeUnitTest extends TestCase
{
    public function test_to_bytes_converts_mb(): void
    {
        $this->assertSame(1048576, SizeUnit::toBytes(1, 'MB'));
        $this->assertSame(5242880, SizeUnit::toBytes('5', 'MB'));
    }

    public function test_to_bytes_converts_gb(): void
    {
        $this->assertSame(1073741824, SizeUnit::toBytes(1, 'GB'));
        $this->assertSame(107374182400, SizeUnit::toBytes(100, 'GB'));
    }

    public function test_to_bytes_supports_decimal_values(): void
    {
        $this->assertSame(1610612736, SizeUnit::toBytes(1.5, 'GB'));
        $this->assertSame(1572864, SizeUnit::toBytes('1.5', 'MB'));
    }

    public function test_to_bytes_maps_empty_and_non_positive_values_to_zero(): void
    {
        $this->assertSame(0, SizeUnit::toBytes(null, 'MB'));
        $this->assertSame(0, SizeUnit::toBytes('', 'GB'));
        $this->assertSame(0, SizeUnit::toBytes(0, 'GB'));
        $this->assertSame(0, SizeUnit::toBytes('0', 'MB'));
        $this->assertSame(0, SizeUnit::toBytes(-5, 'GB'));
        $this->assertSame(0, SizeUnit::toBytes('not-a-number', 'MB'));
    }

    public function test_to_bytes_rejects_unknown_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SizeUnit::toBytes(1, 'TB');
    }

    public function test_from_bytes_prefers_gb_when_evenly_divisible(): void
    {
        $this->assertSame(['value' => 1, 'unit' => 'GB'], SizeUnit::fromBytes(1073741824));
        $this->assertSame(['value' => 100, 'unit' => 'GB'], SizeUnit::fromBytes(107374182400));
    }

    public function test_from_bytes_uses_mb_when_not_evenly_divisible_by_gb(): void
    {
        $this->assertSame(['value' => 1, 'unit' => 'MB'], SizeUnit::fromBytes(1048576));
        $this->assertSame(['value' => 500, 'unit' => 'MB'], SizeUnit::fromBytes(524288000));
    }

    public function test_from_bytes_rounds_to_two_decimals_for_odd_byte_counts(): void
    {
        $this->assertSame(['value' => 1.5, 'unit' => 'MB'], SizeUnit::fromBytes(1572864));
        $this->assertSame(['value' => 1.43, 'unit' => 'MB'], SizeUnit::fromBytes(1500000));
    }

    public function test_from_bytes_maps_empty_and_non_positive_values_to_zero_mb(): void
    {
        $this->assertSame(['value' => 0, 'unit' => 'MB'], SizeUnit::fromBytes(null));
        $this->assertSame(['value' => 0, 'unit' => 'MB'], SizeUnit::fromBytes(''));
        $this->assertSame(['value' => 0, 'unit' => 'MB'], SizeUnit::fromBytes(0));
        $this->assertSame(['value' => 0, 'unit' => 'MB'], SizeUnit::fromBytes(-1));
    }

    public function test_round_trip_preserves_values(): void
    {
        foreach ([['2', 'GB'], ['250', 'MB'], ['1.5', 'GB'], ['0.5', 'MB']] as [$value, $unit]) {
            $bytes = SizeUnit::toBytes($value, $unit);
            $display = SizeUnit::fromBytes($bytes);

            $this->assertSame($bytes, SizeUnit::toBytes($display['value'], $display['unit']));
        }
    }
}
