<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\NNTP\NNTPService;
use App\Services\YencService;
use Exception;
use RuntimeException;

final class NntpArticleSource implements ArticleSource
{
    private bool $connected = false;

    private bool $alternateConnected = false;

    /** @var array<int, NNTPService> */
    private array $connections = [];

    /** @var array<string, string|RuntimeException> */
    private array $prefetched = [];

    public function __construct(private readonly NNTPService $nntp, private ?NNTPService $alternate = null,
        private readonly ?YencService $decoder = null) {}

    public function batchSize(): int
    {
        $frames = max(1, intdiv(max(1, (int) config('release_verification.download_buffer_bytes', 67108864)),
            max(1, (int) config('release_verification.article_bytes'))));
        $connections = min($frames, max(1, min(8, (int) config('release_verification.download_connections', 1))));
        $window = min(max(1, intdiv($frames, $connections)), max(1, min(16, (int) config('release_verification.pipeline_window', 1))));

        return $connections * $window;
    }

    /** @param list<string> $ids */
    public function prefetch(array $ids, callable $heartbeat): void
    {
        $this->prefetched = [];
        if ($ids === [] || $this->batchSize() === 1) {
            return;
        }
        if (count($ids) > $this->batchSize()) {
            throw new RuntimeException('Too many articles in a verification prefetch.');
        }
        $maximumBytes = (int) config('release_verification.article_bytes');
        $connectionCount = min(count($ids), max(1, min(8, (int) config('release_verification.download_connections', 1))));
        $window = (int) ceil(count($ids) / $connectionCount);
        $batches = [];
        if (! $this->connected) {
            try {
                if ($this->nntp->doConnect(false) !== true) {
                    throw new RuntimeException('Primary verification connection is unavailable.');
                }
                $this->connected = true;
            } catch (Exception) {
                foreach ($ids as $id) {
                    $this->prefetched[$id] = new RuntimeException('Primary verification connection is unavailable.');
                }

                return;
            }
        }
        try {
            if (! $this->nntp->verificationPipelineAvailable()) {
                return;
            }
        } catch (Exception) {
            return;
        }
        foreach (array_chunk($ids, $window) as $index => $batchIds) {
            $heartbeat();
            $connection = $index === 0 ? $this->nntp : ($this->connections[$index] ??= new NNTPService);
            try {
                if ($index !== 0 && ! $connection->isConnected() && $connection->doConnect(false) !== true) {
                    throw new RuntimeException('Primary verification connection is unavailable.');
                }
                if (! $connection->verificationPipelineAvailable()) {
                    return;
                }
                if ($index === 0) {
                    $this->connected = true;
                }
                $batches[] = ['connection' => $connection, 'ids' => $batchIds];
            } catch (Exception) {
                foreach ($batchIds as $id) {
                    $this->prefetched[$id] = new RuntimeException('Primary verification connection is unavailable.');
                }
            }
        }
        $this->prefetched += NNTPService::verificationArticleBatch($batches, $maximumBytes,
            max($maximumBytes, (int) config('release_verification.download_buffer_bytes', 67108864)), $heartbeat);
    }

    public function fetch(string $messageId): string
    {
        $fetched = $this->fetchFromProviders($messageId);

        return $fetched instanceof FetchedArticle ? $fetched->raw : $fetched;
    }

    /** @param callable(VerifiedArticle): void $validate */
    public function fetchValidated(string $messageId, callable $validate): string
    {
        return $this->fetchVerified($messageId, $validate)->raw;
    }

    /** @param callable(VerifiedArticle): void $validate */
    public function fetchVerified(string $messageId, callable $validate): FetchedArticle
    {
        $fetched = $this->fetchFromProviders($messageId, $validate);
        if (! $fetched instanceof FetchedArticle) {
            throw new RuntimeException('Verified article result is unavailable.');
        }

        return $fetched;
    }

    /** @param (callable(VerifiedArticle): void)|null $validate */
    private function fetchFromProviders(string $messageId, ?callable $validate = null): string|FetchedArticle
    {
        $maximumBytes = (int) config('release_verification.article_bytes');
        $messageId = NNTPService::verificationMessageId($messageId, $maximumBytes);
        try {
            if (array_key_exists($messageId, $this->prefetched)) {
                $raw = $this->prefetched[$messageId];
                unset($this->prefetched[$messageId]);
                if ($raw instanceof RuntimeException) {
                    throw $raw;
                }

                return $this->checkIntegrity($raw, $validate);
            }
            if (! $this->connected) {
                if ($this->nntp->doConnect(false) !== true) {
                    throw new RuntimeException('NNTP is unavailable.');
                }
                $this->connected = true;
            }

            return $this->checkIntegrity($this->nntp->verificationArticle($messageId, $maximumBytes), $validate);
        } catch (Exception $error) {
            if ($this->connected && $error instanceof RuntimeException) {
                $this->connected = $this->nntp->isConnected();
            } else {
                $this->disconnect($this->nntp);
                $this->connected = false;
            }
            if (! (bool) config('nntmux_nntp.alternate_article_fallback', false)) {
                throw $error instanceof RuntimeException ? $error : new RuntimeException('NNTP is unavailable.', previous: $error);
            }
        }

        $this->alternate ??= new NNTPService;
        try {
            if (! $this->alternateConnected) {
                if ($this->alternate->doConnect(false, true) !== true) {
                    throw new RuntimeException('Alternate NNTP is unavailable.');
                }
                $this->alternateConnected = true;
            }

            return $this->checkIntegrity($this->alternate->verificationArticle($messageId, $maximumBytes), $validate);
        } catch (Exception $error) {
            if ($this->alternateConnected && $error instanceof RuntimeException) {
                $this->alternateConnected = $this->alternate->isConnected();
            } else {
                $this->disconnect($this->alternate);
                $this->alternateConnected = false;
            }
            throw $error instanceof RuntimeException ? $error : new RuntimeException('Alternate NNTP is unavailable.', previous: $error);
        }
    }

    /** @param (callable(VerifiedArticle): void)|null $validate */
    private function checkIntegrity(string $raw, ?callable $validate = null): string|FetchedArticle
    {
        try {
            $article = VerifiedArticle::decode($raw, $this->decoder);
            if ($validate !== null) {
                $validate($article);

                return new FetchedArticle($raw, $article);
            }
        } catch (ArticleIntegrityFailure $error) {
            throw $error;
        } catch (RuntimeException $error) {
            if ($validate !== null) {
                throw $error;
            }
            // Encoding and identity validation remain the verifier's responsibility.
        }

        return $raw;
    }

    private function disconnect(NNTPService $connection): void
    {
        try {
            $connection->doQuit();
        } catch (Exception) {
            // A failed transport cleanup must not prevent trying the alternate provider.
        }
    }

    public function __destruct()
    {
        foreach ($this->connections as $connection) {
            $this->disconnect($connection);
        }
        if ($this->connected) {
            $this->disconnect($this->nntp);
        }
        if ($this->alternateConnected && $this->alternate !== null) {
            $this->disconnect($this->alternate);
        }
    }
}
