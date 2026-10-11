<?php

declare(strict_types=1);

namespace App\Services\Predb\Feeds\Sources;

use App\Services\Predb\Feeds\PredbFeedEntry;
use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * Generic RSS 2.0 PreDB feed (e.g. predb.me). Only the item title (and pubDate when present) is used.
 *
 * Opt-in feeds that publish human-readable titles ("Show S01E01 720p WEB h264-GROUP", scnsrc.me, EZTV) set
 * $spacedTitles: whitespace becomes dots and a trailing " EZTV" tag is dropped. That is lossy (the original
 * dots, underscores and casing are unrecoverable), so those sources must stay opt-in. $mapCategory copies the
 * first RSS <category> element into the entry category.
 */
final class RssFeedSource extends HttpFeedSource
{
    public function __construct(
        private readonly string $sourceKey,
        private readonly string $sourceLabel,
        string $endpoint,
        int $pageSize = 100,
        int $timeout = 15,
        string $userAgent = 'NNTmux-PreDB-Importer/1.0',
        private readonly bool $spacedTitles = false,
        private readonly bool $mapCategory = false,
    ) {
        parent::__construct($endpoint, $pageSize, $timeout, $userAgent);
    }

    public function key(): string
    {
        return $this->sourceKey;
    }

    /**
     * RSS feeds expose only their latest items, so every page past the first is empty.
     */
    public function fetch(int $page = 1): array
    {
        if ($page > 1) {
            return [];
        }

        return $this->parseXml($this->http()->get($this->endpoint)->throw()->body());
    }

    /**
     * @return list<PredbFeedEntry>
     *
     * @throws RuntimeException When the body is not an RSS 2.0 document (e.g. an HTML error page).
     */
    public function parseXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document === false || strtolower($document->getName()) !== 'rss' || ! isset($document->channel)) {
            throw new RuntimeException($this->sourceLabel.' returned an invalid RSS document.');
        }

        if (! isset($document->channel->item)) {
            return [];
        }

        $entries = [];

        foreach ($document->channel->item as $item) {
            $title = trim((string) $item->title);

            if ($this->spacedTitles) {
                $title = $this->dotted($title);
            }

            if ($title === '' || str_contains($title, ' ')) {
                continue;
            }

            $category = $this->mapCategory ? trim((string) $item->category) : '';

            $entries[] = new PredbFeedEntry(
                title: $title,
                source: $this->sourceLabel,
                category: $category !== '' ? $category : null,
                predate: $this->parseDate((string) $item->pubDate),
            );

            if (count($entries) >= $this->pageSize) {
                break;
            }
        }

        return $entries;
    }

    private function dotted(string $title): string
    {
        $title = (string) preg_replace('/\s+EZTV$/i', '', trim($title));

        return (string) preg_replace('/\s+/', '.', $title);
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
