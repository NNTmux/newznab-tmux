<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\NNTP\NNTPService;
use Exception;
use RuntimeException;

final class NntpArticleSource implements ArticleSource
{
    private bool $connected = false;

    private bool $alternateConnected = false;

    public function __construct(private readonly NNTPService $nntp, private ?NNTPService $alternate = null) {}

    public function fetch(string $messageId): string
    {
        return $this->fetchFromProviders($messageId);
    }

    /** @param callable(VerifiedArticle): void $validate */
    public function fetchValidated(string $messageId, callable $validate): string
    {
        return $this->fetchFromProviders($messageId, $validate);
    }

    /** @param (callable(VerifiedArticle): void)|null $validate */
    private function fetchFromProviders(string $messageId, ?callable $validate = null): string
    {
        $maximumBytes = (int) config('release_verification.article_bytes');
        $messageId = NNTPService::verificationMessageId($messageId, $maximumBytes);
        try {
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
    private function checkIntegrity(string $raw, ?callable $validate = null): string
    {
        try {
            $article = VerifiedArticle::decode($raw);
            if ($validate !== null) {
                $validate($article);
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
        if ($this->connected) {
            $this->disconnect($this->nntp);
        }
        if ($this->alternateConnected && $this->alternate !== null) {
            $this->disconnect($this->alternate);
        }
    }
}
