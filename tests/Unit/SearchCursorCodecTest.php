<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\DTO\SearchCursor;
use App\Services\Search\SearchCursorCodec;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchCursorCodecTest extends TestCase
{
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

    #[Test]
    public function it_round_trips_with_an_encrypter_that_only_implements_the_contract(): void
    {
        $codec = new SearchCursorCodec($this->contractEncrypter(new Encrypter(str_repeat('k', 32), 'AES-256-CBC')));
        $cursor = new SearchCursor([1710000000, 42], 250, 'query-hash', 'elasticsearch', '7', 4102444800);

        $decoded = $codec->decode($codec->encode($cursor));

        $this->assertSame(get_object_vars($cursor), get_object_vars($decoded));
    }

    #[Test]
    public function it_decodes_cursors_issued_with_string_encryption(): void
    {
        $encrypter = new Encrypter(str_repeat('k', 32), 'AES-256-CBC');
        $codec = new SearchCursorCodec($this->contractEncrypter($encrypter));
        $token = $encrypter->encryptString('{"sort":[1710000000,42],"total":250,"query":"query-hash","driver":"elasticsearch","generation":"7","expires":4102444800}');

        $decoded = $codec->decode($token);

        $this->assertSame([1710000000, 42], $decoded->sortValues);
        $this->assertSame(250, $decoded->total);
        $this->assertSame('query-hash', $decoded->queryHash);
        $this->assertSame('elasticsearch', $decoded->driver);
        $this->assertSame('7', $decoded->indexGeneration);
        $this->assertSame(4102444800, $decoded->expiresAt);
    }

    #[Test]
    public function it_encodes_cursors_in_the_existing_string_encryption_format(): void
    {
        $encrypter = new Encrypter(str_repeat('k', 32), 'AES-256-CBC');
        $codec = new SearchCursorCodec($this->contractEncrypter($encrypter));
        $cursor = new SearchCursor([1710000000, 42], 250, 'query-hash', 'elasticsearch', '7', 4102444800);

        $token = $codec->encode($cursor);

        $this->assertSame('{"sort":[1710000000,42],"total":250,"query":"query-hash","driver":"elasticsearch","generation":"7","expires":4102444800}', $encrypter->decryptString($token));
    }

    private function contractEncrypter(Encrypter $encrypter): EncrypterContract
    {
        return new class($encrypter) implements EncrypterContract
        {
            public function __construct(private readonly Encrypter $encrypter) {}

            public function encrypt(mixed $value, mixed $serialize = true): string
            {
                return $this->encrypter->encrypt($value, $serialize);
            }

            public function decrypt(mixed $payload, mixed $unserialize = true): mixed
            {
                return $this->encrypter->decrypt($payload, $unserialize);
            }

            public function getKey(): string
            {
                return $this->encrypter->getKey();
            }

            /** @return list<string> */
            public function getAllKeys(): array
            {
                return $this->encrypter->getAllKeys();
            }

            /** @return list<string> */
            public function getPreviousKeys(): array
            {
                return $this->encrypter->getPreviousKeys();
            }
        };
    }
}
