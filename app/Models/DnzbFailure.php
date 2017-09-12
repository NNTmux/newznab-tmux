<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnzbFailure extends Model
{
    /**
     * @var string
     */
    protected $table = 'dnzb_failures';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['release_id', 'userid', 'failed'];

    /**
     * Solution taken from https://stackoverflow.com/a/40371760.
     *
     * @param array $attributes
     * @return static
     */
    public static function insertIgnore(array $attributes = [])
    {
        $model = new static($attributes);

        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }

        $attributes = $model->getAttributes();

        $query = $model->newBaseQueryBuilder();
        $processor = $query->getProcessor();
        $grammar = $query->getGrammar();

        $table = $grammar->wrapTable($model->getTable());
        $keyName = $model->getKeyName();
        $columns = $grammar->columnize(array_keys($attributes));
        $values = $grammar->parameterize($attributes);

        $sql = "insert ignore into {$table} ({$columns}) values ({$values})";

        $id = $processor->processInsertGetId($query, $sql, array_values($attributes));

        $model->setAttribute($keyName, $id);

        return $model;
    }
}
