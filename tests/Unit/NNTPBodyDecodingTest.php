<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\YencService;
use DariusIII\NetNntp\Error as NntpError;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\YencArticles;
use Tests\Support\YencNntpHarness;

final class NNTPBodyDecodingTest extends TestCase
{
    public function test_decoder_is_resolved_once_when_a_body_is_read(): void
    {
        $previous = Container::getInstance();
        $container = new Container;
        $resolutions = 0;
        $container->bind(YencService::class, function () use (&$resolutions): YencService {
            $resolutions++;

            return new YencService;
        });
        Container::setInstance($container);
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, YencArticles::article('ABC')."\r\n.\r\n");
        rewind($stream);
        try {
            $harness = new YencNntpHarness($stream);
            $this->assertSame(0, $resolutions);
            $this->assertSame('ABC', $harness->fetch());
            rewind($stream);
            $this->assertSame('ABC', $harness->fetch());
            $this->assertSame(1, $resolutions);
        } finally {
            Container::setInstance($previous);
            fclose($stream);
        }
    }

    public function test_required_decoder_failure_is_not_hidden_when_reading_a_body(): void
    {
        $previous = Container::getInstance();
        $container = new Container;
        $container->bind(YencService::class, function (): never {
            throw new RuntimeException('Native yEnc is unavailable');
        });
        Container::setInstance($container);
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, YencArticles::article('ABC')."\r\n.\r\n");
        rewind($stream);
        try {
            $harness = new YencNntpHarness($stream);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Native yEnc is unavailable');
            $harness->fetch();
        } finally {
            Container::setInstance($previous);
            fclose($stream);
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function entryPaths(): iterable
    {
        yield 'group BODY' => [true];
        yield 'message ID BODY' => [false];
    }

    #[DataProvider('entryPaths')]
    public function test_unstuffs_once_and_leaves_the_next_response_unread(bool $withGroup): void
    {
        $data = str_repeat("\x04", 260).YencArticles::bytes();
        $article = YencArticles::article($data);
        $wire = preg_replace('/^\./m', '..', $article)."\r\n.\r\nNEXT\r\n";
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $wire);
        rewind($stream);
        try {
            $harness = new YencNntpHarness($stream, new YencService);
            $this->assertSame($data, $harness->fetch($withGroup));
            $this->assertSame(['BODY <test@example>'], $harness->commands);
            $this->assertSame("NEXT\r\n", fgets($stream));
        } finally {
            fclose($stream);
        }
    }

    #[DataProvider('entryPaths')]
    public function test_long_fragmented_plain_text_lines_are_not_unstuffed_mid_line(bool $withGroup): void
    {
        $data = str_repeat('x', 8191)."..continued\r\n.leading dot\r\n";
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, str_repeat('x', 8191)."..continued\r\n..leading dot\r\n.\r\n");
        rewind($stream);
        try {
            $this->assertSame($data, (new YencNntpHarness($stream, new YencService))->fetch($withGroup));
        } finally {
            fclose($stream);
        }
    }

    #[DataProvider('entryPaths')]
    public function test_bad_checksum_remains_tolerant(bool $withGroup): void
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, "=ybegin line=128 size=9 name=x\r\nklm=\r\n=yend size=3 crc32=deadbeef\r\n.\r\n");
        rewind($stream);
        try {
            $this->assertSame('ABC', (new YencNntpHarness($stream, new YencService))->fetch($withGroup));
        } finally {
            fclose($stream);
        }
    }

    #[DataProvider('entryPaths')]
    public function test_connection_loss_returns_transport_error(bool $withGroup): void
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, "partial body\r\n");
        rewind($stream);
        try {
            $this->assertInstanceOf(NntpError::class, (new YencNntpHarness($stream, new YencService))->fetch($withGroup));
        } finally {
            fclose($stream);
        }
    }
}
