<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Backfill\BackfillConfig;
use App\Services\Backfill\BackfillService;
use App\Services\Binaries\BinariesService;
use App\Services\NNTP\NNTPService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BackfillServiceBoundedTargetTest extends TestCase
{
    #[Test]
    public function bounded_target_never_crosses_quantity_date_or_server_oldest_limits(): void
    {
        $binaries = $this->createMock(BinariesService::class);
        $binaries->expects(self::exactly(3))
            ->method('daytopost')
            ->willReturnOnConsecutiveCalls('85000', '70000', '85000');
        $service = new BackfillService(
            new BackfillConfig,
            $binaries,
            $this->createStub(NNTPService::class),
        );
        $method = (new ReflectionClass($service))->getMethod('calculateTargetPost');
        $group = ['first_record' => 100_000, 'backfill_target' => 365];

        self::assertSame(85_000, $method->invoke($service, $group, 20_000, ['first' => 5_000], 30));
        self::assertSame(80_000, $method->invoke($service, $group, 20_000, ['first' => 5_000], 30));
        self::assertSame(90_000, $method->invoke($service, $group, 20_000, ['first' => 90_000], 30));
    }
}
