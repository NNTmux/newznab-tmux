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
namespace nntmux\config;

class Configure
{
	private $environments = [
		'indexer' => [
			'config'	=> true,
			'settings'	=> false
		],
		'install' => [
			'config'	=> false,
			'settings'	=> false
		],
		'smarty'  => [
			'config'	=> true,
			'settings'	=> false
		],
	];

	public function __construct($environment = 'indexer')
	{
		$this->loadEnvironment($environment);
	}

	private function loadEnvironment($environment)
	{
		if (array_key_exists($environment, $this->environments)) {
			foreach ($this->environments[$environment] as $config => $throwException) {
				$this->loadSettings($config, $throwException);
			}
		} else {
			throw new \RuntimeException("Unknown environment passed to Configure class!");
		}
	}

	public function loadSettings($filename, $throwException = true)
	{
		$file = NN_CONFIGS . $filename . '.php';
		if (!file_exists($file)) {
			if ($throwException) {
				$errorCode = (int)($filename === 'config');
				throw new \RuntimeException(
					"Unable to load configuration file '$file'. Make sure it has been created and contains correct settings.",
					$errorCode
				);
			}
		} else {
			require_once $file;
		}

		switch ($filename) {
			case 'config':
				$this->defaultSSL();
				break;
			case 'settings':
				$settings_file = NN_CONFIGS . 'settings.php';
				if (is_file($settings_file)) {
					require_once($settings_file);
					if (php_sapi_name() == 'cli') {
						$current_settings_file_version = 4; // Update this when updating settings.example.php
						if (!defined('NN_SETTINGS_FILE_VERSION') ||
							NN_SETTINGS_FILE_VERSION != $current_settings_file_version
						) {
							echo("\033[0;31mNotice: Your $settings_file file is either out of date or you have not updated" .
								 " NN_SETTINGS_FILE_VERSION to $current_settings_file_version in that file.\033[0m" .
								 PHP_EOL
							);
						}
						unset($current_settings_file_version);
					}
				} else if (!defined('ITEMS_PER_PAGE')) {
					define('ITEMS_PER_PAGE', '50');
					define('ITEMS_PER_COVER_PAGE', '20');
					define('NN_ECHOCLI', true);
					define('NN_DEBUG', false);
					define('NN_LOGGING', false);
					define('NN_LOGINFO', false);
					define('NN_LOGNOTICE', false);
					define('NN_LOGWARNING', false);
					define('NN_LOGERROR', false);
					define('NN_LOGFATAL', false);
					define('NN_LOGQUERIES', false);
					define('NN_LOGAUTOLOADER', false);
					define('NN_QUERY_STRIP_WHITESPACE', false);
					define('NN_RENAME_PAR2', true);
					define('NN_RENAME_MUSIC_MEDIAINFO', true);
					define('NN_CACHE_EXPIRY_SHORT', 300);
					define('NN_CACHE_EXPIRY_MEDIUM', 600);
					define('NN_CACHE_EXPIRY_LONG', 900);
					define('NN_PREINFO_OPEN', false);
					define('NN_FLOOD_CHECK', false);
					define('NN_FLOOD_WAIT_TIME', 5);
					define('NN_FLOOD_MAX_REQUESTS_PER_SECOND', 5);
					define('NN_USE_SQL_TRANSACTIONS', true);
					define('NN_RELEASE_SEARCH_TYPE', 0);
					define('NN_MAX_PAGER_RESULTS', '125000');
				}
				unset($settings_file);
				break;
		}
	}

	private function defaultSSL()
	{
		// Check if they updated config.php for the openssl changes. Only check 1 to save speed.
		if (!defined('NN_SSL_VERIFY_PEER')) {
			define('NN_SSL_CAFILE', '');
			define('NN_SSL_CAPATH', '');
			define('NN_SSL_VERIFY_PEER', '0');
			define('NN_SSL_VERIFY_HOST', '0');
			define('NN_SSL_ALLOW_SELF_SIGNED', '1');
		}
	}
}
