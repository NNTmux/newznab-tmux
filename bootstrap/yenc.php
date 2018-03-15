<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:.
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    niel
 * @copyright 2016 nZEDb
 */
if ((defined('NN_INSTALLER') && NN_INSTALLER !== false) || ! file_exists(NN_ROOT.'_install/install.lock')) {
    $adapter = 'Php';
} else {
    if (extension_loaded('yenc') === true) {
        if (method_exists('yenc\yEnc', 'version') && version_compare(yenc\yEnc::version(), '1.3.0', '>=')) {
            $adapter = 'NzedbYenc';
        }
        trigger_error('Your version of the php-yenc extension is out of date and will be
			ignored. Please update it to use the extension.', E_USER_WARNING);
        $adapter = 'Php';
    } else {
        $adapter = 'Php';
    }
}

\App\Providers\YencServiceProvider::config(
    [
        ['name' => [
                 'default' => $adapter,
             ],
        ],
    ]
);
