<?php

declare(strict_types=1);

namespace App\Services\IGDB;

use App\Services\IGDB\Models\BaseModel;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @template TModel of BaseModel
 */
class QueryBuilder
{
    /**
     * @var array<int, string>
     */
    protected array $fields = ['*'];

    /**
     * @var array<int, string>
     */
    protected array $whereClauses = [];

    protected ?string $searchTerm = null;

    protected ?string $sort = null;

    protected ?int $limitValue = null;

    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(
        protected string $modelClass,
        protected ?Client $client = null,
    ) {
        $this->client ??= app(Client::class);
    }

    public function search(string $query): self
    {
        $this->searchTerm = $query;

        return $this;
    }

    public function where(string $field, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (! is_string($operator) || $operator === '') {
            throw new InvalidArgumentException('The IGDB where operator must be a non-empty string.');
        }

        $this->whereClauses[] = sprintf('%s %s %s', $field, $operator, $this->formatValue($value));

        return $this;
    }

    /**
     * @param  array<int, int|string>  $values
     */
    public function whereIn(string $field, array $values): self
    {
        $formattedValues = implode(',', array_map($this->formatValue(...), $values));
        $this->whereClauses[] = sprintf('%s = (%s)', $field, $formattedValues);

        return $this;
    }

    /**
     * @param  array<int|string, array<int, string>|string>  $relationships
     */
    public function with(array $relationships): self
    {
        foreach ($relationships as $relationship => $fields) {
            if (is_int($relationship)) {
                $relationship = $fields;
                $fields = ['*'];
            }

            foreach ((array) $fields as $field) {
                $this->fields[] = sprintf('%s.%s', $relationship, $field);
            }
        }

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sort = $field.' '.strtolower($direction);

        return $this;
    }

    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'desc');
    }

    public function limit(int $limit): self
    {
        $this->limitValue = max(1, $limit);

        return $this;
    }

    /**
     * @return Collection<int, TModel>
     */
    public function get(): Collection
    {
        $payload = $this->client?->request($this->modelClass::endpoint(), $this->toQuery()) ?? [];

        return collect($payload)->map(fn (array $attributes) => new $this->modelClass($attributes));
    }

    /**
     * @return TModel|null
     */
    public function first(): ?BaseModel
    {
        $query = clone $this;
        $query->limit(1);

        /** @var TModel|null $result */
        $result = $query->get()->first();

        return $result;
    }

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?BaseModel
    {
        return $this->where('id', (int) $id)->first();
    }

    public function toQuery(): string
    {
        $segments = [
            'fields '.implode(',', array_unique($this->fields)).';',
        ];

        if ($this->searchTerm !== null && $this->searchTerm !== '') {
            $segments[] = 'search '.$this->formatValue($this->searchTerm).';';
        }

        if ($this->whereClauses !== []) {
            $segments[] = 'where '.implode(' & ', array_unique($this->whereClauses)).';';
        }

        if ($this->sort !== null) {
            $segments[] = 'sort '.$this->sort.';';
        }

        if ($this->limitValue !== null) {
            $segments[] = 'limit '.$this->limitValue.';';
        }

        return implode("\n", $segments);
    }

    protected function formatValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            $value === null => 'null',
            default => '"'.addcslashes((string) $value, '\\"').'"',
        };
    }
}
