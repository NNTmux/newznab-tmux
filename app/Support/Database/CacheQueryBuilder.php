<?php

namespace App\Support\Database;

trait CacheQueryBuilder
{
    /**
     * Taken from https://laracasts.com/discuss/channels/guides/never-execute-a-duplicate-query-again
     *
     * Get a new query builder instance for the connection.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new Builder($conn, $grammar, $conn->getPostProcessor());
    }
}