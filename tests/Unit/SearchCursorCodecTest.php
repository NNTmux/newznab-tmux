<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\DTO\SearchCursor;
use App\Services\Search\SearchCursorCodec;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

final class SearchCursorCodecTest extends TestCase
{
    #[Test]
    public function it_accepts_an_implementation_of_the_string_encrypter_contract(): void
    {
        $codec = new SearchCursorCodec(new class implements StringEncrypter
        {
            public function encryptString(#[SensitiveParameter] mixed $value): string
            {
                return strrev((string) $value);
            }

            public function decryptString(mixed $payload): string
            {
                return strrev((string) $payload);
            }
        });
        $cursor = new SearchCursor([1710000000, 42], 250, 'query-hash', 'elasticsearch', '7', time() + 60);

        $decoded = $codec->decode($codec->encode($cursor));

        $this->assertSame($cursor->sortValues, $decoded->sortValues);
        $this->assertSame($cursor->queryHash, $decoded->queryHash);
    }

    #[Test]
    public function it_round_trips_an_opaque_signed_cursor(): void
    {
        $codec = new SearchCursorCodec(new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $cursor = new SearchCursor([1710000000, 42], 250, 'query-hash', 'elasticsearch', '7', time() + 60);

        $decoded = $codec->decode($codec->encode($cursor));

        $this->assertSame($cursor->sortValues, $decoded->sortValues);
        $this->assertSame(250, $decoded->total);
        $this->assertSame('query-hash', $decoded->queryHash);
        $this->assertSame('elasticsearch', $decoded->driver);
        $this->assertSame('7', $decoded->indexGeneration);
    }

    #[Test]
    public function it_rejects_tampered_and_expired_cursors(): void
    {
        $codec = new SearchCursorCodec(new Encrypter(random_bytes(32), 'AES-256-CBC'));

        $this->expectException(InvalidArgumentException::class);
        $codec->decode('tampered');
    }

    #[Test]
    public function it_rejects_an_expired_cursor(): void
    {
        $codec = new SearchCursorCodec(new Encrypter(random_bytes(32), 'AES-256-CBC'));
        $token = $codec->encode(new SearchCursor([100], 10, 'hash', 'manticore', '1', time() - 1));

        $this->expectException(InvalidArgumentException::class);
        $codec->decode($token);
    }
}
