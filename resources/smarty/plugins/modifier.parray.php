<?php

function smarty_modifier_parray($string, $explode, $limit = null)
{
    if ($limit == null) {
        return explode($explode, $string);
    } else {
        return explode($explode, $string, $limit);
    }
}
