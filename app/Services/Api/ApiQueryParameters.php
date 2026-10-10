<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Models\Category;
use App\Models\UsenetGroup;
use App\Services\Configuration\ConfigurationProvider;
use Illuminate\Http\Request;

final class ApiQueryParameters
{
    public const int MAX_LIMIT = 100;

    /** @return array<int, string|int> */
    public function categories(Request $request): array
    {
        if (! $request->has('cat')) {
            return [-1];
        }

        $raw = $request->input('cat');
        if (is_array($raw)) {
            $value = implode(',', array_values(array_filter(array_map(
                static fn (mixed $id): string => urldecode(trim((string) $id)),
                $raw
            ), static fn (string $id): bool => $id !== '')));
        } elseif (is_scalar($raw)) {
            $value = urldecode(trim((string) $raw));
        } else {
            return [-1];
        }

        if ($value === '') {
            return [-1];
        }

        if (str_contains($value, (string) Category::TV_HD)
            && ! str_contains($value, (string) Category::TV_WEBDL)
            && (int) app(ConfigurationProvider::class)->ingestion()->categorizeWebDl === 0) {
            $value .= ','.Category::TV_WEBDL;
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $id): bool => $id !== ''
        ));
    }

    public function group(Request $request): string|int|bool
    {
        if (! $request->has('group')) {
            return -1;
        }

        $group = UsenetGroup::isValidGroup($request->input('group'));

        return $group === false ? -1 : $group;
    }

    /**
     * Page size, clamped to 1..MAX_LIMIT (the maximum advertised in capabilities).
     */
    public function limit(Request $request): int
    {
        return $request->has('limit') && is_numeric($request->input('limit'))
            ? max(1, min(self::MAX_LIMIT, (int) $request->input('limit')))
            : self::MAX_LIMIT;
    }

    public function offset(Request $request): int
    {
        return $request->has('offset') && is_numeric($request->input('offset'))
            ? max(0, (int) $request->input('offset'))
            : 0;
    }

    public function minimumSize(Request $request): int
    {
        return $request->has('minsize') && is_numeric($request->input('minsize'))
            ? max(0, (int) $request->input('minsize'))
            : 0;
    }

    public function maximumAge(Request $request): int
    {
        return (int) $request->input('maxage', -1);
    }

    public function sort(Request $request): string
    {
        return strtolower(trim((string) $request->input('sort', 'posted_desc')));
    }

    public function hasValidSort(Request $request): bool
    {
        return ! $request->has('sort')
            || preg_match('/^(cat|name|size|files|stats|posted)_(asc|desc)$/', $this->sort($request)) === 1;
    }
}
