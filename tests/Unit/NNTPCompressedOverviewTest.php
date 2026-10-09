<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NNTP\NNTPService;
use DariusIII\NetNntp\Error;
use PHPUnit\Framework\TestCase;

final class NNTPCompressedOverviewTest extends TestCase
{
    private function reader(string $wire): NNTPService
    {
        $nntp = new class extends NNTPService
        {
            public function __construct()
            {
                $this->_echo = false;
            }

            protected function throwError(?string $message, ?int $code = null, mixed $userInfo = null): Error
            {
                return new Error((string) $message, $code, $userInfo);
            }

            public function read(): mixed
            {
                return $this->_getXFeatureTextResponse();
            }
        };
        $socket = fopen('php://memory', 'r+b');
        fwrite($socket, $wire);
        rewind($socket);
        (new \ReflectionProperty(NNTPService::class, '_socket'))->setValue($nntp, $socket);

        return $nntp;
    }

    /** @return array{0: list<string>, 1: string} */
    private function overviewWithTerminatorInsideCompressedData(): array
    {
        for ($seed = 1; ; $seed++) {
            mt_srand($seed);
            $lines = [];
            for ($i = 0; $i < 2000; $i++) {
                $lines[] = ($i + 1)."\t".bin2hex(random_bytes(8)).' yEnc (1/'.mt_rand(1, 99).")\tp@x\tFri, 09 Oct 2026 15:13:49 +0000\t<".mt_rand().">\t\t740112\t5690\tXref: x a.b:".($i + 1);
            }
            $compressed = gzcompress(implode("\r\n", $lines)."\r\n");
            if (str_contains(substr($compressed, 0, -3), ".\r\n")) {
                return [$lines, $compressed];
            }
        }
    }

    public function test_reads_whole_stream_when_terminator_bytes_appear_inside_it(): void
    {
        [$lines, $compressed] = $this->overviewWithTerminatorInsideCompressedData();

        $this->assertSame($lines, $this->reader($compressed.".\r\n")->read());
    }

    public function test_corrupt_stream_returns_error(): void
    {
        $result = $this->reader("not zlib data\r\n.\r\n")->read();

        $this->assertFalse(\is_array($result));
    }

    public function test_truncated_stream_returns_error(): void
    {
        [, $compressed] = $this->overviewWithTerminatorInsideCompressedData();

        $result = $this->reader(substr($compressed, 0, 1000))->read();

        $this->assertFalse(\is_array($result));
    }
}
