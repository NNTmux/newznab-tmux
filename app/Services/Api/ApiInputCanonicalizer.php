<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Single canonical form for API input, shared by HTTP QUERY normalization,
 * search cursor binding, and the usage audit, so equivalent GET and QUERY
 * filters (e.g. `cat=2000,5030` and `{"cat":[2000,5030]}`) compare equal.
 */
final class ApiInputCanonicalizer
{
    /**
     * Parameters that accept either a list or a comma-separated string.
     */
    public const LIST_PARAMETERS = ['cat'];

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return array<string, string|array<array-key, mixed>|null>
     */
    public function canonicalize(array $parameters): array
    {
        $canonical = [];
        foreach ($parameters as $key => $value) {
            $key = (string) $key;
            $canonical[$key] = in_array($key, self::LIST_PARAMETERS, true)
                ? $this->listValue($value)
                : $this->value($value);
        }

        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    public static function isListParameter(string $key): bool
    {
        return in_array($key, self::LIST_PARAMETERS, true);
    }

    private function listValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_scalar($value)) {
            $items = explode(',', (string) $value);
        } else {
            return null;
        }

        $items = array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $items),
            static fn (string $item): bool => $item !== ''
        ));

        return $items === [] ? null : implode(',', $items);
    }

    /**
     * @return string|array<array-key, mixed>|null
     */
    private function value(mixed $value): string|array|null
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }

        if (is_array($value)) {
            $nested = array_map(fn (mixed $item): string|array|null => $this->value($item), $value);
            ksort($nested, SORT_STRING);

            return $nested;
        }

        return null;
    }
}
