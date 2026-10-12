<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NNTP\NNTPService;
use App\Services\Releases\Verification\ArticleIntegrityFailure;
use App\Services\Releases\Verification\NntpArticleSource;
use App\Services\Releases\Verification\PayloadVerifier;
use App\Services\Releases\Verification\VerifiedArticle;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\YencArticles;

final class NntpVerificationArticleSourceTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $container = new Container;
        $container->instance('config', new Repository(['nntmux_nntp' => ['alternate_article_fallback' => true],
            'release_verification' => ['article_bytes' => 1048576]]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
    }

    public function test_primary_connect_failure_fetches_raw_body_from_alternate_without_group_selection(): void
    {
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness("..leading dot\r\n.\r\nNEXT\r\n");
        $source = new NntpArticleSource($primary, $alternate);
        $this->assertSame(".leading dot\r\n", $source->fetch('test@example'));
        $this->assertSame([[false, false]], $primary->connections);
        $this->assertSame([[false, true]], $alternate->connections);
        $this->assertSame([], $primary->commands);
        $this->assertSame(['BODY <test@example>'], $alternate->commands);
        $this->assertSame("NEXT\r\n", $alternate->nextLine());
    }

    public function test_primary_article_missing_falls_back_but_valid_primary_articles_remain_preferred(): void
    {
        $primary = new VerificationNntpHarness("primary\r\n.\r\n", [true], [430, 222]);
        $alternate = new VerificationNntpHarness("alternate\r\n.\r\n");
        $source = new NntpArticleSource($primary, $alternate);
        $this->assertSame("alternate\r\n", $source->fetch('missing@example'));
        $this->assertSame("primary\r\n", $source->fetch('present@example'));
        $this->assertSame(['BODY <missing@example>', 'BODY <present@example>'], $primary->commands);
        $this->assertSame(['BODY <missing@example>'], $alternate->commands);
        $this->assertSame([[false, false]], $primary->connections);
    }

    public function test_enabled_alternate_is_never_called_when_primary_body_succeeds(): void
    {
        $primary = new VerificationNntpHarness("primary\r\n.\r\n");
        $alternate = new VerificationNntpHarness("unused\r\n.\r\n");
        $this->assertSame("primary\r\n", (new NntpArticleSource($primary, $alternate))->fetch('present@example'));
        $this->assertSame([], $alternate->connections);
        $this->assertSame([], $alternate->commands);
    }

    /** @return iterable<string, array{bool}> */
    public static function primaryFailures(): iterable
    {
        yield 'connect failure' => [false];
        yield 'missing article' => [true];
    }

    #[DataProvider('primaryFailures')]
    public function test_disabled_fallback_never_connects_or_reads_alternate(bool $connects): void
    {
        config(['nntmux_nntp.alternate_article_fallback' => false, 'nntmux_nntp.use_alternate_nntp_server' => true]);
        $primary = new VerificationNntpHarness('', [$connects], [430]);
        $alternate = new VerificationNntpHarness("unused\r\n.\r\n");
        try {
            (new NntpArticleSource($primary, $alternate))->fetch('missing@example');
            $this->fail('Primary failure must remain unavailable when fallback is disabled.');
        } catch (RuntimeException) {
            $this->assertSame([], $alternate->connections);
            $this->assertSame([], $alternate->commands);
        }
    }

    #[DataProvider('primaryFailures')]
    public function test_all_provider_failures_remain_unavailable(bool $alternateConnects): void
    {
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness('', [$alternateConnects], [430]);
        try {
            (new NntpArticleSource($primary, $alternate))->fetch('missing@example');
            $this->fail('Neither provider can supply this article.');
        } catch (RuntimeException) {
            $this->assertSame([[false, true]], $alternate->connections);
            $this->assertSame($alternateConnects ? ['BODY <missing@example>'] : [], $alternate->commands);
        }
    }

    public function test_alternate_body_uses_the_same_exact_byte_limit_and_dot_unstuffing(): void
    {
        config(['release_verification.article_bytes' => 6]);
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness("..abc\r\n.\r\n");
        $this->assertSame(".abc\r\n", (new NntpArticleSource($primary, $alternate))->fetch('test@example'));
    }

    /** @return iterable<string, array{string, int}> */
    public static function rejectedAlternateBodies(): iterable
    {
        yield 'over byte limit' => ["123456\r\n.\r\n", 7];
        yield 'missing line terminator' => ['truncated', 128];
        yield 'missing BODY terminator' => ["truncated\r\n", 128];
        yield 'over line limit' => [str_repeat('x', 65536)."\r\n.\r\n", 1048576];
    }

    #[DataProvider('rejectedAlternateBodies')]
    public function test_alternate_cannot_bypass_body_limits_or_truncation_checks(string $wire, int $limit): void
    {
        config(['release_verification.article_bytes' => $limit]);
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness($wire);
        try {
            (new NntpArticleSource($primary, $alternate))->fetch('test@example');
            $this->fail('An unsafe alternate BODY must not be returned.');
        } catch (RuntimeException) {
            $this->assertSame(['BODY <test@example>'], $alternate->commands);
            $this->assertGreaterThanOrEqual(1, $alternate->quits);
        }
    }

    public function test_corrupt_primary_checksum_uses_valid_alternate_without_group_selection(): void
    {
        $good = YencArticles::article('payload');
        $bad = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', $good);
        $primary = new VerificationNntpHarness($bad."\r\n.\r\n");
        $alternate = new VerificationNntpHarness($good."\r\n.\r\n");
        $raw = (new NntpArticleSource($primary, $alternate))->fetch('test@example');
        $this->assertSame('payload', VerifiedArticle::decode($raw)->bytes);
        $this->assertSame(['BODY <test@example>'], $primary->commands);
        $this->assertSame(['BODY <test@example>'], $alternate->commands);
    }

    /** @return iterable<string, array{bool, bool, bool}> */
    public static function semanticProviderCopies(): iterable
    {
        yield 'wrong part then corrected alternate' => [false, true, true];
        yield 'wrong total then corrected alternate' => [true, true, true];
        yield 'wrong part with disabled alternate' => [false, false, true];
        yield 'wrong total with disabled alternate' => [true, false, true];
        yield 'both providers have wrong part' => [false, true, false];
        yield 'both providers have wrong total' => [true, true, false];
    }

    #[DataProvider('semanticProviderCopies')]
    public function test_checksum_valid_wrong_identity_can_use_only_a_semantically_valid_configured_alternate(bool $wrongTotal, bool $enabled, bool $corrected): void
    {
        config(['nntmux_nntp.alternate_article_fallback' => $enabled]);
        $first = YencArticles::part('hell', 'movie.mkv', 8, 1, 1, 2);
        $good = YencArticles::part('tail', 'movie.mkv', 8, 2, 5, 2);
        $bad = YencArticles::part('tail', 'movie.mkv', 8, $wrongTotal ? 2 : 1, 5, $wrongTotal ? 3 : 2);
        $this->assertSame('tail', VerifiedArticle::decode($bad)->bytes);
        $primary = new VerificationNntpHarness($first."\r\n.\r\n".$bad."\r\n.\r\n");
        $alternate = new VerificationNntpHarness(($corrected ? $good : $bad)."\r\n.\r\n");
        $verifier = new PayloadVerifier(new NntpArticleSource($primary, $alternate),
            ['scratch_bytes' => 1048576, 'free_bytes' => 0, 'article_bytes' => 1048576, 'bytes_per_second' => 0, 'par2_binary' => 'par2']);
        $scratch = sys_get_temp_dir().'/semantic-copy-'.bin2hex(random_bytes(8)).'/candidate';
        $manifest = ['expected_files' => 1, 'files' => [['key' => 'source', 'subject' => 'movie.mkv', 'totalparts' => 2,
            'segments' => [['number' => 1, 'messageid' => 'first@example', 'bytes' => 4],
                ['number' => 2, 'messageid' => 'tail@example', 'bytes' => 4]]]]];
        try {
            $result = $verifier->verify($manifest, $scratch, static function (): void {});
            $this->assertSame($enabled && $corrected, $result->passed(), $result->reason);
            $this->assertSame(['BODY <first@example>', 'BODY <tail@example>'], $primary->commands);
            $this->assertSame($enabled ? ['BODY <tail@example>'] : [], $alternate->commands);
            $cachePath = $scratch.'/articles/'.hash('sha256', 'tail@example');
            if ($result->passed()) {
                $this->assertSame('helltail', file_get_contents($scratch.'/data/movie.mkv'));
                $this->assertSame(2, VerifiedArticle::decode(file_get_contents($cachePath))->part);
                $this->assertSame(2, VerifiedArticle::decode(file_get_contents($cachePath))->totalParts);
            } else {
                $this->assertSame('unknown', $result->verdict);
                $this->assertFileDoesNotExist($cachePath);
            }
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scratch, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($scratch);
            rmdir(dirname($scratch));
        }
    }

    public function test_both_corrupt_provider_checksums_remain_retryable_and_never_return_raw(): void
    {
        $bad = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', YencArticles::article('payload'));
        $primary = new VerificationNntpHarness($bad."\r\n.\r\n");
        $alternate = new VerificationNntpHarness($bad."\r\n.\r\n");
        try {
            (new NntpArticleSource($primary, $alternate))->fetch('test@example');
            $this->fail('Neither corrupt copy can establish article integrity.');
        } catch (ArticleIntegrityFailure) {
            $this->assertSame(['BODY <test@example>'], $primary->commands);
            $this->assertSame(['BODY <test@example>'], $alternate->commands);
        }
    }

    public function test_corrupt_primary_checksum_does_not_use_disabled_alternate(): void
    {
        config(['nntmux_nntp.alternate_article_fallback' => false]);
        $bad = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', YencArticles::article('payload'));
        $alternate = new VerificationNntpHarness(YencArticles::article('payload')."\r\n.\r\n");
        try {
            (new NntpArticleSource(new VerificationNntpHarness($bad."\r\n.\r\n"), $alternate))->fetch('test@example');
            $this->fail('A corrupt primary article must not be returned.');
        } catch (ArticleIntegrityFailure) {
            $this->assertSame([], $alternate->connections);
            $this->assertSame([], $alternate->commands);
        }
    }

    public function test_corrupt_alternate_checksum_is_still_rejected_by_integrity_decoder(): void
    {
        $raw = preg_replace('/crc32=[0-9a-f]+/', 'crc32=00000000', YencArticles::article('payload'));
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness($raw."\r\n.\r\n");
        $this->expectException(ArticleIntegrityFailure::class);
        (new NntpArticleSource($primary, $alternate))->fetch('test@example');
    }

    public function test_successful_alternate_keeps_valid_yenc_framing_for_decoder(): void
    {
        $raw = YencArticles::article('payload');
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness($raw."\r\n.\r\n");
        $article = (new NntpArticleSource($primary, $alternate))->fetch('test@example');
        $this->assertStringStartsWith('=ybegin', $article);
        $this->assertSame('payload', VerifiedArticle::decode($article)->bytes);
    }

    public function test_closed_primary_reader_is_not_reused_and_alternate_connection_is_reused(): void
    {
        config(['release_verification.article_bytes' => 7]);
        $primary = new VerificationNntpHarness("oversized-body\r\n.\r\n", [true, false]);
        $alternate = new VerificationNntpHarness("ok\r\n.\r\nagain\r\n.\r\n");
        $source = new NntpArticleSource($primary, $alternate);
        $this->assertSame("ok\r\n", $source->fetch('first@example'));
        $this->assertSame("again\r\n", $source->fetch('second@example'));
        $this->assertSame([[false, false], [false, false]], $primary->connections);
        $this->assertSame([[false, true]], $alternate->connections);
        $this->assertSame(['BODY <first@example>'], $primary->commands);
        $this->assertSame(['BODY <first@example>', 'BODY <second@example>'], $alternate->commands);
    }

    public function test_transport_exceptions_on_primary_connect_or_body_can_use_alternate(): void
    {
        foreach ([true, false] as $duringConnect) {
            $error = new \ErrorException('Fixture transport failed.');
            $primary = new VerificationNntpHarness('', $duringConnect ? [$error] : [true], $duringConnect ? [222] : [$error]);
            $alternate = new VerificationNntpHarness("ok\r\n.\r\n");
            $this->assertSame("ok\r\n", (new NntpArticleSource($primary, $alternate))->fetch('test@example'));
            $this->assertGreaterThanOrEqual(1, $primary->quits);
        }
    }

    public function test_alternate_transport_exception_is_a_retryable_source_failure(): void
    {
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness('', [new \ErrorException('Fixture transport failed.')]);
        $this->expectException(RuntimeException::class);
        (new NntpArticleSource($primary, $alternate))->fetch('test@example');
    }

    public function test_primary_cleanup_failure_does_not_prevent_alternate_fetch(): void
    {
        $primary = new VerificationNntpHarness('', [false]);
        $primary->quitFailure = new \ErrorException('Fixture cleanup failed.');
        $alternate = new VerificationNntpHarness("ok\r\n.\r\n");
        $this->assertSame("ok\r\n", (new NntpArticleSource($primary, $alternate))->fetch('test@example'));
        $this->assertSame([[false, true]], $alternate->connections);
    }

    public function test_provider_connections_are_closed_when_the_source_is_released(): void
    {
        $primary = new VerificationNntpHarness('', [true], [430]);
        $alternate = new VerificationNntpHarness("ok\r\n.\r\n");
        $source = new NntpArticleSource($primary, $alternate);
        $this->assertSame("ok\r\n", $source->fetch('test@example'));
        unset($source);
        $this->assertSame(1, $primary->quits);
        $this->assertSame(1, $alternate->quits);
    }

    public function test_tiny_byte_limit_can_read_empty_body_terminator_without_returning_payload(): void
    {
        config(['release_verification.article_bytes' => 1]);
        $primary = new VerificationNntpHarness('', [false]);
        $alternate = new VerificationNntpHarness(".\r\n");
        $this->assertSame('', (new NntpArticleSource($primary, $alternate))->fetch('test@example'));
    }

    public function test_nonpositive_article_limit_is_rejected_before_any_provider_connection(): void
    {
        config(['release_verification.article_bytes' => 0]);
        $primary = new VerificationNntpHarness('');
        $alternate = new VerificationNntpHarness('');
        try {
            (new NntpArticleSource($primary, $alternate))->fetch('test@example');
            $this->fail('A nonpositive byte limit must be rejected.');
        } catch (RuntimeException) {
            $this->assertSame([], $primary->connections);
            $this->assertSame([], $alternate->connections);
        }
    }

    public function test_invalid_verification_request_does_not_connect_to_either_provider(): void
    {
        $primary = new VerificationNntpHarness('');
        $alternate = new VerificationNntpHarness('');
        try {
            (new NntpArticleSource($primary, $alternate))->fetch("bad\r\nGROUP alt.test");
            $this->fail('Invalid message IDs must be rejected.');
        } catch (RuntimeException) {
            $this->assertSame([], $primary->connections);
            $this->assertSame([], $alternate->connections);
            $this->assertSame([], $primary->commands);
            $this->assertSame([], $alternate->commands);
        }
    }
}

/** Real bounded BODY reader with in-memory transport and fixture connection outcomes. */
final class VerificationNntpHarness extends NNTPService
{
    /** @var list<array{bool, bool}> */
    public array $connections = [];

    /** @var list<string> */
    public array $commands = [];

    public int $quits = 0;

    public ?\Exception $quitFailure = null;

    /** @param list<bool|\Exception> $connectResults
     * @param  list<int|\Exception>  $responses
     */
    public function __construct(string $wire, private array $connectResults = [true], private array $responses = [222])
    {
        $this->_socket = fopen('php://memory', 'w+');
        fwrite($this->_socket, $wire);
        rewind($this->_socket);
    }

    public function doConnect(bool $compression = true, bool $alternate = false): mixed
    {
        $this->connections[] = [$compression, $alternate];

        $result = array_shift($this->connectResults) ?? true;
        if ($result instanceof \Exception) {
            throw $result;
        }

        return $result;
    }

    protected function _sendCommand(string $cmd): mixed
    {
        $this->commands[] = $cmd;

        $result = array_shift($this->responses) ?? 222;
        if ($result instanceof \Exception) {
            throw $result;
        }

        return $result;
    }

    public function doQuit(bool $force = false): mixed
    {
        $this->quits++;
        if (is_resource($this->_socket)) {
            fclose($this->_socket);
        }
        $this->_socket = null;
        if ($this->quitFailure !== null) {
            $error = $this->quitFailure;
            $this->quitFailure = null;
            throw $error;
        }

        return true;
    }

    public function nextLine(): string|false
    {
        return fgets($this->_socket);
    }
}
