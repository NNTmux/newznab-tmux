<?php

//=========================
// Config you must change - updated by installer.
//=========================
define('DB_SYSTEM', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_SOCKET', '');
define('DB_USER', '');
define('DB_PASSWORD', '');
define('DB_NAME', 'newznab');
define('DB_PCONNECT', false);

define('NNTP_USERNAME', '');
define('NNTP_PASSWORD', '');
define('NNTP_SERVER', '');
define('NNTP_PORT', '');
// If you want to use TLS or SSL on the NNTP connection (the NNTP_PORT must be able to support encryption).
define('NNTP_SSLENABLED', false);
// If we lose connection to the NNTP server, this is the time in seconds to wait before giving up.
define('NNTP_SOCKET_TIMEOUT', '120');

define('NNTP_USERNAME_A', '');
define('NNTP_PASSWORD_A', '');
define('NNTP_SERVER_A', '');
define('NNTP_PORT_A', '');
define('NNTP_SSLENABLED_A', false);
define('NNTP_SOCKET_TIMEOUT_A', '120');

// Location to CA bundle file on your system. You can download one here: http://curl.haxx.se/docs/caextract.html
define('NN_SSL_CAFILE', '');
// Path where openssl cert files are stored on your system, this is a fall back if the CAFILE is not found.
define('NN_SSL_CAPATH', '');
// Use the aforementioned CA bundle file to verify remote SSL certificates when connecting to a server using TLS/SSL.
define('NN_SSL_VERIFY_PEER', '');
// Verify the host is who they say they are.
define('NN_SSL_VERIFY_HOST', '');
// Allow self signed certificates. Note this does not work on CURL as CURL does not have this option.
define('NN_SSL_ALLOW_SELF_SIGNED', '1');
