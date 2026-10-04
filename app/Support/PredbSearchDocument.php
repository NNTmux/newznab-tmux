<?php

declare(strict_types=1);

namespace App\Support;

final class PredbSearchDocument
{
    public static function exactValue(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, title: string, filename: string, source: string}
     */
    public static function normalize(array $row, int $id): array
    {
        return [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'filename' => (string) ($row['filename'] ?? ''),
            'source' => (string) ($row['source'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: int, title: string, filename: string, source: string, title_exact: string, filename_exact: string}
     */
    public static function forElasticsearch(array $row): array
    {
        $document = self::normalize($row, (int) ($row['id'] ?? 0));

        return $document + [
            'title_exact' => self::exactValue($document['title']),
            'filename_exact' => self::exactValue($document['filename']),
        ];
    }

    /** @return array<string, array{type: string}> */
    public static function elasticsearchExactMappings(): array
    {
        return [
            'title_exact' => ['type' => 'keyword'],
            'filename_exact' => ['type' => 'keyword'],
        ];
    }
}
