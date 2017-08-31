<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tmux extends Model
{
    /**
     * @var string
     */
    protected $table = 'tmux';

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $fillable = ['setting', 'value'];

    /**
     * @param string $setting
     *
     * @param bool   $returnAlways
     *
     * @return mixed
     * @throws \RuntimeException
     */
    public static function value($setting, $returnAlways = false)
    {
        $result = self::query()->where('setting', $setting)->value('value');

        if ($result !== null) {
            $value = $result;
        } elseif ($returnAlways === false) {
            throw new \RuntimeException('Unable to fetch setting from Tmux table!');
        } else {
            $value = null;
        }

        return $value;
    }
}
