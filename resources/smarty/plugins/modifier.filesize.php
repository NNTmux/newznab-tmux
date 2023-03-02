<?php
/**
 * Smarty plugin
 */

/**
 * Smarty replace modifier plugin
 *
 * Type:     modifier<br>
 * Name:     filesize<br>
 * Purpose:  show the filesize of a file in kb, mb, gb etc...
 *
 * @param string $
 * @return string
 */
function smarty_modifier_filesize($size)
{
    $size = max(0, (int) $size);
    $units = ['b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb', 'Zb', 'Yb'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;

    return number_format($size / (1024 ** $power), 2, '.', ',').$units[$power];
}
