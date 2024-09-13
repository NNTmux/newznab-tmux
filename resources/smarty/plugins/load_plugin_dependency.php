<?php

use Ytake\LaravelSmarty\Smarty;

function load_plugin_dependency($filename)
{
    global $smarty;

    if (! isset($smarty)) {
        $smarty = new Smarty;
    }

    switch (true) {
        case is_string($smarty->getPluginsDir()) && is_dir($smarty->getPluginsDir()):
            $plugins_dir = $smarty->getPluginsDir();
            require_once $plugins_dir.DIRECTORY_SEPARATOR.$filename;
            break;
        case is_array($smarty->getPluginsDir()):
            $plugins_dir = '';
            foreach ($smarty->getPluginsDir() as $dir) {
                if (is_string($dir) && is_dir($dir)) {
                    $file = $dir.DIRECTORY_SEPARATOR.$filename;
                    if (file_exists($file) && is_readable($file)) {
                        $plugins_dir = $dir;
                        require_once $file;
                        break;
                    }
                }
            }
            break;
        default:
            $plugins_dir = '';
    }

    if (! is_dir($plugins_dir)) {
        exit('Fatal: Unable to find smarty plugins directory.'.PHP_EOL);
    }
}
