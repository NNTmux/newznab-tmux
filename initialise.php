<?php
require_once 'SPLClassLoader.php';
require_once 'constants.php';

$classLoader = new SplClassLoader('newznab', [__DIR__ . DIRECTORY_SEPARATOR . 'newznab']);
$classLoader->register();

?>
