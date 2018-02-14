<?php

namespace Blacklight;

class Sites
{
    /**
     * @var \app\extensions\util\Versions|bool
     */
    protected $_versions = false;

    /**
     * Sites constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $rows
     * @return \stdClass
     */
    public function rows2Object($rows)
    {
        $obj = new \stdClass;
        foreach ($rows as $row) {
            $obj->{$row['setting']} = $row['value'];
        }

        return $obj;
    }

    /**
     * @param $row
     * @return \stdClass
     */
    public function row2Object($row)
    {
        $obj = new \stdClass;
        $rowKeys = array_keys($row);
        foreach ($rowKeys as $key) {
            $obj->{$key} = $row[$key];
        }

        return $obj;
    }
}
