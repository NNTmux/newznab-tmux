<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Releases\Verification\ArticleIntegrityFailure;
use App\Services\Releases\Verification\FetchedArticle;
use App\Services\Releases\Verification\VerifiedArticle;
use App\Services\Yenc\PayloadDecoder;
use App\Services\Yenc\PhpPayloadDecoder;
use App\Services\YencService;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\YencArticles;

final class VerifiedArticleDecoderTest extends TestCase
{
    public function test_explicit_decoder_runs_once_and_result_keeps_raw_and_decoded_identity(): void
    {
        $decoder = new class implements PayloadDecoder
        {
            public int $calls = 0;

            public function decode(string $payload): string
            {
                $this->calls++;

                return (new PhpPayloadDecoder)->decode($payload);
            }
        };
        $raw = YencArticles::article('configured decoder payload');
        $article = VerifiedArticle::decode($raw, new YencService($decoder));
        $this->assertSame(1, $decoder->calls);
        $fetched = new FetchedArticle($raw, $article);
        $this->assertSame($raw, $fetched->raw);
        $this->assertSame($article, $fetched->article);
        $this->assertSame('configured decoder payload', $fetched->article->bytes);
        $this->assertEquals(VerifiedArticle::decode($raw), $article);
    }

    public function test_explicit_decoder_still_requires_matching_crc(): void
    {
        $decoder = new class implements PayloadDecoder
        {
            public function decode(string $payload): string
            {
                return 'wrong';
            }
        };
        $this->expectException(ArticleIntegrityFailure::class);
        VerifiedArticle::decode(YencArticles::article('right'), new YencService($decoder));
    }
}
