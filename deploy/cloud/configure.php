<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$configurationDirectory = $argv[2] ?? '/configuration';
try {
    $contents = file_get_contents($configurationDirectory.'/application.env');
    if ($contents === false) {
        throw new RuntimeException('Missing configuration.');
    }
    $values = Dotenv::parse($contents);
    $required = ['APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_ROOTPASSWORD', 'ADMIN_USER', 'ADMIN_PASS', 'ADMIN_EMAIL', 'NNTP_SERVER', 'NNTP_USERNAME', 'NNTP_PASSWORD', 'NNTP_PORT'];
    foreach ($required as $name) {
        if (! isset($values[$name]) || trim($values[$name]) === '' || str_contains($values[$name], "\n") || str_contains($values[$name], "\r")) {
            throw new RuntimeException('Missing or multiline deployment setting: '.$name);
        }
    }
    $expected = [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.($argv[1] ?? ''),
        'DB_CONNECTION' => 'mariadb', 'DB_HOST' => 'mariadb', 'DB_PORT' => '3306',
        'REDIS_HOST' => 'redis', 'REDIS_PORT' => '6379', 'REDIS_PASSWORD' => 'null',
        'CACHE_STORE' => 'redis', 'QUEUE_CONNECTION' => 'redis', 'SESSION_DRIVER' => 'redis',
        'SEARCH_DRIVER' => 'manticore', 'MANTICORESEARCH_HOST' => 'manticore', 'MANTICORESEARCH_PORT' => '9308',
        'COVERS_PATH' => '/app/storage/covers', 'PATH_TO_NZBS' => '/app/storage/nzb',
        'TEMP_UNRAR_PATH' => '/app/storage/tmp/unrar', 'TEMP_UNZIP_PATH' => '/app/storage/tmp/unzip',
        'TRUSTED_PROXIES' => '172.30.42.0/24',
    ];
    foreach ($expected as $name => $value) {
        if (($values[$name] ?? null) !== $value) {
            throw new RuntimeException('Unexpected deployment setting: '.$name);
        }
    }
    $key = str_starts_with($values['APP_KEY'], 'base64:') ? base64_decode(substr($values['APP_KEY'], 7), true) : $values['APP_KEY'];
    if ($key === false || ! Encrypter::supported($key, 'AES-256-CBC') || strlen($values['ADMIN_PASS']) < 12 || ! filter_var($values['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid application key or administrator credentials.');
    }
    if (($values['MANTICORESEARCH_HOSTS'] ?? '') !== '' || ($values['USE_ALTERNATE_NNTP_SERVER'] ?? 'false') !== 'false') {
        throw new RuntimeException('This deployment uses the primary NNTP server and local Manticore.');
    }
    $database = '';
    foreach (['MYSQL_DATABASE' => 'DB_DATABASE', 'MYSQL_USER' => 'DB_USERNAME', 'MYSQL_PASSWORD' => 'DB_PASSWORD', 'MYSQL_ROOT_PASSWORD' => 'DB_ROOTPASSWORD'] as $target => $source) {
        $database .= $target.'='.$values[$source]."\n";
    }
    if (file_put_contents($configurationDirectory.'/database.env', $database) === false) {
        throw new RuntimeException('Unable to write database configuration.');
    }
    $identity = [];
    foreach (['APP_KEY', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_ROOTPASSWORD'] as $name) {
        $identity[$name] = $values[$name];
    }
    if (file_put_contents($configurationDirectory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR)) === false
        || ! chmod($configurationDirectory.'/database.env', 0600)
        || ! chmod($configurationDirectory.'/identity.json', 0600)) {
        throw new RuntimeException('Unable to secure deployment configuration.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception instanceof RuntimeException ? $exception->getMessage()."\n" : "Invalid deployment configuration.\n");
    exit(1);
}
