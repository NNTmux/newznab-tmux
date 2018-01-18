<?php

if (! defined('NN_INSTALLER')) {
    define('NN_INSTALLER', true);
}
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use nntmux\db\DB;
use App\Models\User;
use nntmux\ColorCLI;
use nntmux\db\DbUpdate;
use nntmux\config\Configure;
use App\Extensions\util\Versions;

$config = new Configure('install');

$pdo = new DB();
$error = false;

if (file_exists(NN_ROOT.'_install/install.lock')) {
    ColorCLI::doEcho(ColorCLI::notice('Installation is locked. If you want to reinstall NNTmux, please remove install.lock file from _install folder. '.PHP_EOL.ColorCLI::warning('This will wipe your database!')));
    exit();
}

// Check if user selected right DB type.
if (env('DB_SYSTEM') !== 'mysql') {
    ColorCLI::doEcho(ColorCLI::error('Invalid database system. Must be: mysql ; Not: '.env('DB_SYSTEM')));
    $error = true;
} else {
    // Connect to the SQL server.
    try {
        // HAS to be DB because settings table does not exist yet.
        $pdo = new DB(
            [
                'checkVersion' => true,
                'createDb'     => true,
                'dbhost'       => env('DB_HOST'),
                'dbname'       => env('DB_NAME'),
                'dbpass'       => env('DB_PASSWORD'),
                'dbport'       => env('PORT'),
                'dbsock'       => env('DB_SOCKET'),
                'dbtype'       => env('DB_SYSTEM'),
                'dbuser'       => env('DB_USER'),
            ]
        );
        $dbConnCheck = true;
    } catch (\PDOException $e) {
        ColorCLI::doEcho(ColorCLI::error('Unable to connect to MySQL server.'));
        $error = true;
        $dbConnCheck = false;
    } catch (\RuntimeException $e) {
        switch ($e->getCode()) {
            case 1:
            case 2:
            case 3:
                $error = true;
                ColorCLI::doEcho(ColorCLI::alternate($e->getMessage()));
                break;
            default:
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // Check if the MySQL version is correct.
    $goodVersion = false;
    if (! $error) {
        try {
            $goodVersion = $pdo->isDbVersionAtLeast(NN_MINIMUM_MYSQL_VERSION);
        } catch (\PDOException $e) {
            $goodVersion = false;
            $error = true;
            ColorCLI::doEcho(ColorCLI::error('Could not get version from MySQL server.'));
        }

        if ($goodVersion === false) {
            $error = true;
            ColorCLI::doEcho(
                ColorCLI::error(
                'You are using an unsupported version of '.
                env('DB_SYSTEM').
                ' the minimum allowed version is '.
                NN_MINIMUM_MYSQL_VERSION
            )
            );
        }
    }
}

// Start inserting data into the DB.
if (! $error) {
    $DbSetup = new DbUpdate(
        [
            'backup' => false,
            'db'     => $pdo,
        ]
    );
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');

    $DbSetup->processSQLFile(); // Setup default schema
    //Insert admin user into database
    if (env('ADMIN_USER') === '' || env('ADMIN_PASS') === '' || env('ADMIN_EMAIL') === '') {
        $error = true;
        ColorCLI::doEcho(ColorCLI::error('Admin user data cannot be empty! Please edit .env file and fill in admin user details and run this script again!'));
        exit();
    }
    $pdo->queryExec(sprintf('INSERT INTO users (username, email, password, user_roles_id, created_at) VALUES (%s, %s, %s, 2, NOW())', $pdo->escapeString(env('ADMIN_USER')), $pdo->escapeString(env('ADMIN_EMAIL')), $pdo->escapeString(User::hashPassword(env('ADMIN_PASS')))));
    ColorCLI::doEcho(ColorCLI::header('Migrating tables and populating them'));
    passthru('php '.NN_ROOT.'artisan migrate');
    passthru('php '.NN_ROOT.'artisan db:seed');

    if (! $error) {
        // Check one of the standard tables was created and has data.
        $dbInstallWorked = false;
        $reschk = $pdo->query('SELECT COUNT(id) AS num FROM tmux');
        if ($reschk === false) {
            $dbCreateCheck = false;
            $error = true;
            ColorCLI::doEcho(ColorCLI::warningOver('Could not select data from your database, check that tables and data are properly created/inserted.'));
        } else {
            foreach ($reschk as $row) {
                if ($row['num'] > 0) {
                    $dbInstallWorked = true;
                    break;
                }
            }
        }
        $ver = new Versions();
        $patch = $ver->getSQLPatchFromFile();
        if ($dbInstallWorked) {
            $updateSettings = false;
            if ($patch > 0) {
                $updateSettings = $pdo->queryExec(
                    "UPDATE settings SET value = '$patch' WHERE section = '' AND subsection = '' AND name = 'sqlpatch'"
                );
            }
            // If it all worked, continue the install process.
            if ($updateSettings) {
                ColorCLI::doEcho(ColorCLI::info('Database updated successfully'));
            } else {
                $error = true;
                ColorCLI::doEcho(ColorCLI::error('Could not update sqlpatch to '.$patch.' for your database.'));
            }
        } else {
            $dbCreateCheck = false;
            $error = true;
            ColorCLI::doEcho(ColorCLI::warning('Could not select data from your database.'));
        }
    }
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
        $sql1 = sprintf("UPDATE settings SET value = %s WHERE setting = 'nzbpath'", $pdo->escapeString($nzb_path));
        $sql2 = sprintf("UPDATE settings SET value = %s WHERE setting = 'tmpunrarpath'", $pdo->escapeString($unrar_path));
        $sql3 = sprintf("UPDATE settings SET value = %s WHERE setting = 'coverspath'", $pdo->escapeString($covers_path));
        if ($pdo->queryExec($sql1) === false || $pdo->queryExec($sql2) === false || $pdo->queryExec($sql3) === false) {
            $error = true;
        } else {
            ColorCLI::doEcho(ColorCLI::info('Settings table updated successfully'));
        }
    }
}

if (! $error) {
    @file_put_contents(NN_ROOT.'_install/install.lock', '');
    ColorCLI::doEcho(ColorCLI::header('Generating application key'));
    passthru('php '.NN_ROOT.'artisan key:generate');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    ColorCLI::doEcho(ColorCLI::alternate('NNTmux installation completed successfully'));
    exit();
}

ColorCLI::doEcho(ColorCLI::error('NNTmux installation failed. Fix reported problems and try again'));
