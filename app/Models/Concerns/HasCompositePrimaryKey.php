<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Scopes instance-level update, delete, refresh, and fresh queries to every
 * column of a table's composite primary key, which Eloquent's single
 * `$primaryKey` cannot express.
 *
 * `$primaryKey` must name the leading key column so relationship defaults and
 * `delete()`'s key-name guard keep working; `getKey()` therefore identifies a
 * row only together with the remaining compositeKeyColumns().
 */
trait HasCompositePrimaryKey
{
    /**
     * Columns that together form the table's primary key, leading column first.
     *
     * @return non-empty-list<string>
     */
    abstract public function compositeKeyColumns(): array;

    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $this->whereCompositeKey($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        return $this->whereCompositeKey($query);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    private function whereCompositeKey(Builder $query): Builder
    {
        foreach ($this->compositeKeyColumns() as $column) {
            $value = $this->original[$column] ?? $this->getAttribute($column);

            if ($value === null) {
                throw new LogicException(sprintf('Composite key column [%s] is missing on [%s].', $column, static::class));
            }

            $query->where($column, '=', $value);
        }

        return $query;
    }
}
