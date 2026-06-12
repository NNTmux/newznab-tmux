<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\SecondarySearchIndex;
use App\Facades\Search;
use Illuminate\Support\Facades\Log;
use JsonException;
use stdClass;

/**
 * Dispatches compacted search outbox rows to the active search driver.
 *
 * The outbox is written outside PHP (database triggers), so this class accepts a
 * small set of action/entity aliases to keep the consumer tolerant of table and
 * model naming differences while still routing only to the existing Search API.
 */
final class SearchIndexOutboxDispatcher
{
    /**
     * Keep only the latest row for each logical search document in a batch.
     *
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    public static function compactRows(array $rows): array
    {
        usort(
            $rows,
            static fn (object $a, object $b): int => self::rowId($a) <=> self::rowId($b)
        );

        /** @var array<string, object> $compacted */
        $compacted = [];

        foreach ($rows as $row) {
            $compacted[self::documentKey($row)] = $row;
        }

        $latestRows = array_values($compacted);

        usort(
            $latestRows,
            static fn (object $a, object $b): int => self::rowId($a) <=> self::rowId($b)
        );

        return $latestRows;
    }

    /**
     * Dispatch one compacted outbox row.
     */
    public function dispatchRow(object $row): void
    {
        $payload = self::payload($row);
        $entityId = self::entityId($row, $payload);
        $entityType = self::normalizeEntityType($row->entity_type ?? '');
        $action = self::normalizeAction($row->action ?? '');

        if ($entityId <= 0) {
            Log::warning('Search outbox row skipped because it has no entity id', [
                'row_id' => self::rowId($row),
                'entity_type' => $row->entity_type ?? null,
                'action' => $row->action ?? null,
            ]);

            return;
        }

        match ($entityType) {
            'release' => $this->dispatchRelease($action, $entityId, $payload),
            'predb' => $this->dispatchPredb($action, $entityId, $payload),
            'movie' => $this->dispatchMovie($action, $entityId, $payload),
            'tvshow' => $this->dispatchTvShow($action, $entityId, $payload),
            'secondary' => $this->dispatchSecondaryRow($action, $entityId, $payload, $row),
            default => $this->dispatchSecondaryEntity($action, $entityType, $entityId, $payload, $row),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchRelease(string $action, int $entityId, array $payload): void
    {
        match ($action) {
            'delete' => Search::deleteRelease($entityId),
            'insert', 'upsert' => empty($payload)
                ? Search::updateRelease($entityId)
                : Search::insertRelease(self::withId($payload, $entityId)),
            'update' => Search::updateRelease($entityId),
            default => self::logUnknownAction($action, 'release', $entityId),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchPredb(string $action, int $entityId, array $payload): void
    {
        if ($action === 'delete') {
            Search::deletePreDb($entityId);

            return;
        }

        if (empty($payload)) {
            Log::warning('Search outbox predb row skipped because it has no payload', [
                'action' => $action,
                'entity_id' => $entityId,
            ]);

            return;
        }

        match ($action) {
            'insert', 'upsert' => Search::insertPredb(self::withId($payload, $entityId)),
            'update' => Search::updatePreDb(self::withId($payload, $entityId)),
            default => self::logUnknownAction($action, 'predb', $entityId),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchMovie(string $action, int $entityId, array $payload): void
    {
        match ($action) {
            'delete' => Search::deleteMovie($entityId),
            'insert', 'upsert' => empty($payload)
                ? Search::updateMovie($entityId)
                : Search::insertMovie(self::withId($payload, $entityId)),
            'update' => Search::updateMovie($entityId),
            default => self::logUnknownAction($action, 'movie', $entityId),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchTvShow(string $action, int $entityId, array $payload): void
    {
        match ($action) {
            'delete' => Search::deleteTvShow($entityId),
            'insert', 'upsert' => empty($payload)
                ? Search::updateTvShow($entityId)
                : Search::insertTvShow(self::withId($payload, $entityId)),
            'update' => Search::updateTvShow($entityId),
            default => self::logUnknownAction($action, 'tvshow', $entityId),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchSecondaryRow(string $action, int $entityId, array $payload, object $row): void
    {
        $index = self::secondaryIndexFromPayload($payload);

        if ($index === null) {
            Log::warning('Search outbox secondary row skipped because it has no index', [
                'row_id' => self::rowId($row),
                'entity_id' => $entityId,
                'action' => $action,
            ]);

            return;
        }

        $this->dispatchSecondary($action, $index, $entityId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchSecondaryEntity(string $action, string $entityType, int $entityId, array $payload, object $row): void
    {
        $index = self::secondaryIndexFromEntityType($entityType);

        if ($index === null) {
            Log::warning('Search outbox row skipped because it has an unknown entity type', [
                'row_id' => self::rowId($row),
                'entity_type' => $row->entity_type ?? null,
                'normalized_entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
            ]);

            return;
        }

        $this->dispatchSecondary($action, $index, $entityId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchSecondary(string $action, SecondarySearchIndex $index, int $entityId, array $payload): void
    {
        if ($action === 'delete') {
            Search::deleteSecondary($index, $entityId);

            return;
        }

        $document = self::secondaryDocument($payload);

        match ($action) {
            'insert', 'upsert' => empty($document)
                ? Search::updateSecondary($index, $entityId)
                : Search::insertSecondary($index, $entityId, $document),
            'update' => empty($document)
                ? Search::updateSecondary($index, $entityId)
                : Search::insertSecondary($index, $entityId, $document),
            default => self::logUnknownAction($action, $index->value, $entityId),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(object $row): array
    {
        $payload = $row->payload ?? null;

        if ($payload === null || $payload === '') {
            return [];
        }

        if (is_array($payload)) {
            return $payload;
        }

        if ($payload instanceof stdClass) {
            return (array) $payload;
        }

        if (! is_string($payload)) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('Search outbox row has invalid JSON payload', [
                'row_id' => self::rowId($row),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function entityId(object $row, array $payload): int
    {
        $entityId = $row->entity_id ?? ($payload['id'] ?? null);

        return is_numeric($entityId) ? (int) $entityId : 0;
    }

    private static function rowId(object $row): int
    {
        $id = $row->id ?? 0;

        return is_numeric($id) ? (int) $id : 0;
    }

    private static function documentKey(object $row): string
    {
        $payload = self::payload($row);
        $entityType = self::normalizeEntityType($row->entity_type ?? '');
        $entityId = self::entityId($row, $payload);
        $secondaryIndex = $entityType === 'secondary'
            ? self::secondaryIndexFromPayload($payload)
            : self::secondaryIndexFromEntityType($entityType);

        if ($secondaryIndex !== null) {
            return 'secondary:'.$secondaryIndex->value.':'.$entityId;
        }

        return $entityType.':'.$entityId;
    }

    private static function normalizeEntityType(mixed $entityType): string
    {
        $type = strtolower((string) $entityType);
        $type = str_replace('\\', '/', $type);
        $type = basename($type);
        $type = str_replace(['-', ' '], '_', $type);

        return match ($type) {
            'release', 'releases' => 'release',
            'pre', 'pres', 'predb', 'predbs' => 'predb',
            'movie', 'movies', 'movieinfo', 'movie_info' => 'movie',
            'tv', 'video', 'videos', 'tvshow', 'tvshows', 'tv_show', 'tv_shows' => 'tvshow',
            'secondary', 'secondary_search', 'secondary_search_index' => 'secondary',
            default => $type,
        };
    }

    private static function normalizeAction(mixed $action): string
    {
        $value = strtolower((string) $action);
        $value = str_replace(['-', ' '], '_', $value);

        return match ($value) {
            'insert', 'inserted', 'create', 'created' => 'insert',
            'upsert', 'upserted', 'replace', 'replaced', 'save', 'saved', 'index', 'indexed' => 'upsert',
            'update', 'updated' => 'update',
            'delete', 'deleted', 'destroy', 'destroyed', 'remove', 'removed' => 'delete',
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function withId(array $payload, int $id): array
    {
        $payload['id'] ??= $id;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function secondaryIndexFromPayload(array $payload): ?SecondarySearchIndex
    {
        foreach (['index', 'secondary', 'secondary_index', 'secondarySearchIndex', 'type', 'entity', 'entity_type'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                $index = self::secondaryIndexFromEntityType((string) $payload[$key]);

                if ($index !== null) {
                    return $index;
                }
            }
        }

        return null;
    }

    private static function secondaryIndexFromEntityType(string $entityType): ?SecondarySearchIndex
    {
        return match (self::normalizeEntityType($entityType)) {
            'music', 'musicinfo', 'music_info' => SecondarySearchIndex::Music,
            'book', 'books', 'bookinfo', 'book_info' => SecondarySearchIndex::Books,
            'game', 'games', 'gamesinfo', 'games_info' => SecondarySearchIndex::Games,
            'console', 'consoleinfo', 'console_info' => SecondarySearchIndex::Console,
            'steam', 'steamapp', 'steamapps', 'steam_app', 'steam_apps' => SecondarySearchIndex::Steam,
            'anime', 'anidb', 'anidbtitle', 'anidbtitles', 'anidb_title', 'anidb_titles', 'anidbinfo', 'anidb_info' => SecondarySearchIndex::Anime,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function secondaryDocument(array $payload): array
    {
        if (isset($payload['document']) && is_array($payload['document'])) {
            return $payload['document'];
        }

        foreach (['index', 'secondary', 'secondary_index', 'secondarySearchIndex', 'type', 'entity', 'entity_type'] as $metadataKey) {
            unset($payload[$metadataKey]);
        }

        return $payload;
    }

    private static function logUnknownAction(string $action, string $entityType, int $entityId): void
    {
        Log::warning('Search outbox row skipped because it has an unknown action', [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }
}
