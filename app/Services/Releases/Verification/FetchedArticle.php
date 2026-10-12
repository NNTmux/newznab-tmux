<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

/** A validated network body and its decoded bytes travel together without decoding twice. */
final readonly class FetchedArticle
{
    public function __construct(public string $raw, public VerifiedArticle $article) {}
}
