<?php
require_once 'SPLClassLoader.php';
require_once 'constants.php';

$classLoader = new SplClassLoader('nntmux', [__DIR__ . DIRECTORY_SEPARATOR . 'nntmux']);
$classLoader->register();

?>