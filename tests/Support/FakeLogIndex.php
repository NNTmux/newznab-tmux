<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\LogViewer\Index\LogIndex;
use Manticoresearch\Client;
use RuntimeException;

/**
 * In-memory stand-in for the Manticore log index.
 *
 * `search()` only honours the `file_id` / `in` / `byte_offset` range clauses, the sort and the limit; the
 * full-text and attribute clauses are ignored, which is enough because the searcher re-checks every candidate.
 */
class FakeLogIndex extends LogIndex
{
    /**
     * @var array<int, array<string, int|string>>
     */
    public array $documents = [];

    /**
     * @var list<int>
     */
    public array $purged = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $queries = [];

    public int $writes = 0;

    public int $drops = 0;

    public bool $available = true;

    public bool $failing = false;

    public function __construct(bool $enabled = true)
    {
        parent::__construct(new Client(['host' => '127.0.0.1', 'port' => 1]), 'app_logs_rt', $enabled);
    }

    public function isAvailable(): bool
    {
        return $this->isEnabled() && $this->available;
    }

    public function ensureTable(): void {}

    public function drop(): void
    {
        $this->drops++;
        $this->documents = [];
    }

    public function count(): int
    {
        return count($this->documents);
    }

    public function replace(array $documents): void
    {
        $this->guard();

        if ($documents === []) {
            return;
        }

        $this->writes++;

        foreach ($documents as $document) {
            $this->documents[(int) $document['id']] = $document;
        }
    }

    public function purgeFile(int $fileId): void
    {
        $this->guard();
        $this->purged[] = $fileId;
        $this->documents = array_filter($this->documents, static fn (array $document): bool => $document['file_id'] !== $fileId);
    }

    public function search(array $query, array $sort, int $limit): array
    {
        $this->guard();
        $this->queries[] = $query;
        $hits = array_values(array_filter($this->documents, fn (array $document): bool => $this->matches($document, $query['bool']['must'] ?? [])));
        usort($hits, static fn (array $a, array $b): int => $b['byte_offset'] <=> $a['byte_offset']);

        return ['total' => count($hits), 'exact' => true, 'hits' => array_slice($hits, 0, $limit)];
    }

    public function facet(array $query, string $field, int $size): array
    {
        $this->guard();
        $counts = [];

        foreach ($this->documents as $document) {
            if ($document[$field] !== '' && $this->matches($document, $query['bool']['must'] ?? [])) {
                $counts[(string) $document[$field]] = ($counts[(string) $document[$field]] ?? 0) + 1;
            }
        }

        arsort($counts);

        return array_slice($counts, 0, $size, true);
    }

    /**
     * Documents of one file, oldest first.
     *
     * @return list<array<string, int|string>>
     */
    public function documentsFor(int $fileId): array
    {
        $documents = array_values(array_filter($this->documents, static fn (array $document): bool => $document['file_id'] === $fileId));
        usort($documents, static fn (array $a, array $b): int => $a['byte_offset'] <=> $b['byte_offset']);

        return $documents;
    }

    /**
     * @param  array<string, int|string>  $document
     * @param  list<array<string, mixed>>  $clauses
     */
    private function matches(array $document, array $clauses): bool
    {
        foreach ($clauses as $clause) {
            if (isset($clause['equals']['file_id']) && $document['file_id'] !== $clause['equals']['file_id']) {
                return false;
            }

            if (isset($clause['in']['file_id']) && ! in_array($document['file_id'], $clause['in']['file_id'], true)) {
                return false;
            }

            if (isset($clause['range']['byte_offset']['lt']) && $document['byte_offset'] >= $clause['range']['byte_offset']['lt']) {
                return false;
            }
        }

        return true;
    }

    private function guard(): void
    {
        if ($this->failing) {
            throw new RuntimeException('Manticore is down');
        }
    }
}
