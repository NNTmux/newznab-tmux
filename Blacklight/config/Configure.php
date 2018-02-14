<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 * @author    niel
 * @copyright 2015 NN
 */

namespace Blacklight\config;

class Configure
{
    private static $environments = [
        'indexer' => [
            '.env'    => true,
            'settings'    => false,
        ],
        'install' => [
            '.env'    => true,
            'settings'    => false,
        ],
        'smarty'  => [
            '.env'    => true,
            'settings'    => false,
        ],
    ];

    /**
     * Configure constructor.
     *
     * @param string $environment
     */
    public function __construct($environment = 'indexer')
    {
        try {
            $this->loadEnvironment($environment);
        } catch (\RuntimeException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @param $environment
     * @throws \RuntimeException
     */
    private function loadEnvironment($environment)
    {
        if (array_key_exists($environment, self::$environments)) {
            foreach (self::$environments[$environment] as $config => $throwException) {
                $this->loadSettings($config, $throwException);
            }
        } else {
            throw new \RuntimeException('Unknown environment passed to Configure class!');
        }
    }

    /**
     * @param      $filename
     * @param bool $throwException
     * @throws \RuntimeException
     */
    public function loadSettings($filename, $throwException = true)
    {
        if ($filename === '.env') {
            $file = NN_ROOT.'.env';
        } else {
            $file = NN_CONFIGS.$filename.'.php';
        }
        if ($throwException && ! file_exists($file)) {
            $errorCode = (int) ($filename === '.env');
            throw new \RuntimeException(
                "Unable to load configuration file '$file'. Make sure it has been created and contains correct settings.",
                $errorCode
            );
        }
        if ($file !== NN_ROOT.'.env' && file_exists($file)) {
            require_once $file;
        }

        switch ($filename) {
            case '.env':
                $this->defaultSSL();
                break;
            case 'settings':
                $settings_file = NN_CONFIGS.'settings.php';
                if (is_file($settings_file)) {
                    require_once $settings_file;
                    if (PHP_SAPI === 'cli') {
                        $current_settings_file_version = 8; // Update this when updating settings.example.php
                        if (! \defined('NN_SETTINGS_FILE_VERSION') ||
                            NN_SETTINGS_FILE_VERSION !== $current_settings_file_version
                        ) {
                            echo "\033[0;31mNotice: Your $settings_file file is either out of date or you have not updated".
                                 " NN_SETTINGS_FILE_VERSION to $current_settings_file_version in that file.\033[0m".
                                 PHP_EOL;
                        }
                        unset($current_settings_file_version);
                    }
                } elseif (! \defined('ITEMS_PER_PAGE')) {
                    \define('ITEMS_PER_PAGE', '50');
                    \define('ITEMS_PER_COVER_PAGE', '20');
                    \define('NN_ECHOCLI', true);
                    \define('NN_QUERY_STRIP_WHITESPACE', false);
                    \define('NN_RENAME_PAR2', true);
                    \define('NN_RENAME_MUSIC_MEDIAINFO', true);
                    \define('NN_CACHE_EXPIRY_SHORT', 300);
                    \define('NN_CACHE_EXPIRY_MEDIUM', 600);
                    \define('NN_CACHE_EXPIRY_LONG', 900);
                    \define('NN_FLOOD_CHECK', false);
                    \define('NN_FLOOD_WAIT_TIME', 5);
                    \define('NN_FLOOD_MAX_REQUESTS_PER_SECOND', 5);
                    \define('NN_USE_SQL_TRANSACTIONS', true);
                    \define('NN_MAX_PAGER_RESULTS', '125000');
                }
                unset($settings_file);
                break;
        }
    }

    private function defaultSSL()
    {
        // Check if they updated config.php for the openssl changes. Only check 1 to save speed.
        if (! \defined('NN_SSL_VERIFY_PEER')) {
            \define('NN_SSL_CAFILE', '');
            \define('NN_SSL_CAPATH', '');
            \define('NN_SSL_VERIFY_PEER', '0');
            \define('NN_SSL_VERIFY_HOST', '0');
            \define('NN_SSL_ALLOW_SELF_SIGNED', '1');
        }
    }
}
