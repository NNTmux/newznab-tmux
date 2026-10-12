<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

interface ArticleSource
{
    public function fetch(string $messageId): string;
}
