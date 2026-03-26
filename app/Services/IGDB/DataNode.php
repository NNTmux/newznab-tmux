<?php

declare(strict_types=1);

namespace App\Services\IGDB;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;

class DataNode implements Arrayable, ArrayAccess
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $dateFields
     */
    public function __construct(array $attributes = [], protected array $dateFields = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $this->normalizeValue((string) $key, $value);
        }
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->normalizeValue($key, $value);
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[(string) $offset] = $this->normalizeValue((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->normalizeArrayValue($value);
        }

        return $attributes;
    }

    protected function normalizeValue(string $key, mixed $value): mixed
    {
        if (in_array($key, $this->dateFields, true) && is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            return new self($value, $this->dateFields);
        }

        return array_map(function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            return array_is_list($item)
                ? array_map(fn (mixed $nested) => is_array($nested) && ! array_is_list($nested)
                    ? new self($nested, $this->dateFields)
                    : $nested, $item)
                : new self($item, $this->dateFields);
        }, $value);
    }

    protected function normalizeArrayValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        if (! is_array($value)) {
            return $value;
        }

        return array_map(fn (mixed $item) => $this->normalizeArrayValue($item), $value);
    }
}
