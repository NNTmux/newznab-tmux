<?php

//
// apt-get install php5-memcache
//

$memcache_enabled = extension_loaded("memcached");

if ($memcache_enabled)
    echo "extension loaded";
else
    echo "extension not loaded";

$mc = new Memcached();
$mc->connect("localhost", 11211) ;

print_r($mc->getStats());
