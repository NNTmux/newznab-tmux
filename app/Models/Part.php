<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Part.
 *
 * @property int $binaries_id
 * @property string $messageid
 * @property int $number
 * @property int $partnumber
 * @property int $size
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part whereBinariesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part whereMessageid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part wherePartnumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part whereSize($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Part query()
 */
class Part extends Model
{
    use HasCompositePrimaryKey;

    /**
     * @var string
     */
    protected $primaryKey = 'binaries_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return non-empty-list<string>
     */
    public function compositeKeyColumns(): array
    {
        return ['binaries_id', 'partnumber'];
    }
}
