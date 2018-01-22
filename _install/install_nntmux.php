<?php

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use App\Models\User;
use nntmux\ColorCLI;
use App\Models\Settings;
use App\Extensions\util\Versions;

if (! \defined('NN_INSTALLER')) {
    \define('NN_INSTALLER', true);
}

$error = false;

if (file_exists(NN_ROOT.'_install/install.lock')) {
    ColorCLI::doEcho(ColorCLI::notice('Installation is locked. If you want to reinstall NNTmux, please remove install.lock file from _install folder. '.PHP_EOL.ColorCLI::warning('This will wipe your database!')));
    exit();
}

if (! $error) {
// Check if user selected right DB type.
    if (env('DB_SYSTEM') !== 'mysql') {
        ColorCLI::doEcho(ColorCLI::error('Invalid database system. Must be: mysql ; Not: '.env('DB_SYSTEM')));
        $error = true;
    }
}

if (!(new Settings())->isDbVersionAtLeast(NN_MINIMUM_MARIA_VERSION) || !(new Settings())->isDbVersionAtLeast(NN_MINIMUM_MYSQL_VERSION)) {
    ColorCLI::doEcho(ColorCLI::error('Version of MariaDB used is lower than required version: ' . NN_MINIMUM_MARIA_VERSION));
    $error = true;
}
// Start inserting data into the DB.
if (! $error) {
    ColorCLI::doEcho(ColorCLI::header('Migrating tables and populating them'));
    passthru('php '.NN_ROOT.'artisan migrate:fresh --seed');
}
    // Check one of the standard tables was created and has data.
    $ver = new Versions();
    $patch = $ver->getSQLPatchFromFile();
    $updateSettings = false;
    if ($patch > 0) {
        $updateSettings = Settings::query()->where(['section' => '', 'subsection' => '', 'name' => 'sqlpatch'])->update(['value' => $patch]);
    }
    // If it all worked, continue the install process.
    if ($updateSettings === 0) {
        ColorCLI::doEcho(ColorCLI::info('Database updated successfully'));
    } else {
        $error = true;
        ColorCLI::doEcho(ColorCLI::error('Could not update sqlpatch to '.$patch.' for your database.'));
    }

if (! $error) {
    $doCheck = true;

    $covers_path = NN_RES.'covers'.DS;
    $nzb_path = NN_RES.'nzb'.DS;
    $tmp_path = NN_RES.'tmp'.DS;
    $unrar_path = $tmp_path.'unrar'.DS;

    $nzbPathCheck = is_writable($nzb_path);
    if ($nzbPathCheck === false) {
        $error = true;
        ColorCLI::doEcho(ColorCLI::warning($nzb_path.' is not writable. Please fix folder permissions'));
    }

    $lastchar = substr($nzb_path, strlen($nzb_path) - 1);
    if ($lastchar !== '/') {
        $nzb_path .= '/';
    }

    if (! file_exists($unrar_path)) {
        ColorCLI::doEcho(ColorCLI::primary('Creating missing '.$unrar_path.' folder'));
        if (! @mkdir($unrar_path) && ! is_dir($unrar_path)) {
            throw new RuntimeException('Unable to create '.$unrar_path.' folder');
        }
        ColorCLI::doEcho(ColorCLI::primary('Folder '.$unrar_path.' successfully created'));
    }
    $unrarPathCheck = is_writable($unrar_path);
    if ($unrarPathCheck === false) {
        $error = true;
        ColorCLI::doEcho(ColorCLI::warning($unrar_path.' is not writable. Please fix folder permissions'));
    }

    $lastchar = substr($unrar_path, strlen($unrar_path) - 1);
    if ($lastchar !== '/') {
        $unrar_path .= '/';
    }

    $coversPathCheck = is_writable($covers_path);
    if ($coversPathCheck === false) {
        $error = true;
        ColorCLI::doEcho(ColorCLI::warning($covers_path.' is not writable. Please fix folder permissions'));
    }

    $lastchar = substr($covers_path, strlen($covers_path) - 1);
    if ($lastchar !== '/') {
        $covers_path .= '/';
    }

    if (! $error) {
        $sql1 = Settings::query()->where('setting', '=', 'nzbpath')->update(['value' => $nzb_path]);
        $sql2 = Settings::query()->where('setting', '=', 'tmpunrarpath')->update(['value' => $unrar_path]);
        $sql3 = Settings::query()->where('setting', '=', 'coverspath')->update(['value' => $covers_path]);
        if ($sql1 === null || $sql2 === null || $sql3 === null) {
            $error = true;
        } else {
            ColorCLI::doEcho(ColorCLI::info('Settings table updated successfully'));
        }
    }
}

if (! $error) {
    //Insert admin user into database
    if (env('ADMIN_USER') === '' || env('ADMIN_PASS') === '' || env('ADMIN_EMAIL') === '') {
        $error = true;
        ColorCLI::doEcho(ColorCLI::error('Admin user data cannot be empty! Please edit .env file and fill in admin user details and run this script again!'));
        exit();
    }

    ColorCLI::doEcho(ColorCLI::header('Adding admin user to database'));
    try {
        User::add(env('ADMIN_USER'), env('ADMIN_PASS'), env('ADMIN_EMAIL'), 2, '', '', '', '');
    } catch (Exception $e) {
        ColorCLI::doEcho(ColorCLI::error('Unable to add admin user!'));
    }
    @file_put_contents(NN_ROOT.'_install/install.lock', '');
    ColorCLI::doEcho(ColorCLI::header('Generating application key'));
    passthru('php '.NN_ROOT.'artisan key:generate');
    ColorCLI::doEcho(ColorCLI::alternate('NNTmux installation completed successfully'));
    exit();
}

ColorCLI::doEcho(ColorCLI::error('NNTmux installation failed. Fix reported problems and try again'));
