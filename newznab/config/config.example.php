<?php

//=========================
// Config you must change - updated by installer.
//=========================
define('DB_TYPE', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_SOCKET', '');
define('DB_USER', '');
define('DB_PASSWORD', '');
define('DB_NAME', 'newznab');
define('DB_INNODB', true);
define('DB_PCONNECT', false);

define('NNTP_USERNAME', '');
define('NNTP_PASSWORD', '');
define('NNTP_SERVER', '');
define('NNTP_PORT', '');
define('NNTP_SSLENABLED', false);

define('NNTP_USERNAME_A', '');
define('NNTP_PASSWORD_A', '');
define('NNTP_SERVER_A', '');
define('NNTP_PORT_A', '');
define('NNTP_SSLENABLED_A', false);

// Wether to use memcached or not.
define('MEMCACHE_ENABLED', false);
// To use a socket instead set MEMCACHE_HOST as 'unix:///path/to/memcached.sock' and MEMCACHE_PORT as '0'.
define('MEMCACHE_HOST', '127.0.0.1');
define('MEMCACHE_PORT', '11211');
// Amount of time to keep a query in ram in seconds.
define('MEMCACHE_EXPIRY', '900');
// To compress the queries using zlib or not (more cpu usage and less ram usage if set to true, inverse for false);
define('MEMCACHE_COMPRESSION', true);

/* Location to CA bundle file on your system. You can download one here: http://curl.haxx.se/docs/caextract.html */
define('NN_SSL_CAFILE', '/etc/ssl/certs/cacert.pem');
/* Path where openssl cert files are stored on your system, this is a fall back if the CAFILE is not found. */
define('NN_SSL_CAPATH', '/etc/ssl/certs/');
/* Use the aforementioned CA bundle file to verify remote SSL certificates when connecting to a server using TLS/SSL. */
define('NN_SSL_VERIFY_PEER', '0');
/* Verify the host is who they say they are. */
define('NN_SSL_VERIFY_HOST', '0');
/* Allow self signed certificates. Note this does not work on CURL as CURL does not have this option. */
define('NN_SSL_ALLOW_SELF_SIGNED', '1');