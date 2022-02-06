<?php

return [
    'server' => env('NNTP_SERVER', ''),
    'alternate_server' => env('NNTP_SERVER_A', ''),
    'port' => env('NNTP_PORT', ''),
    'alternate_server_port' => env('NNTP_PORT_A', ''),
    'username' => env('NNTP_USERNAME', ''),
    'alternate_server_username' => env('NNTP_USERNAME_A', ''),
    'password' => env('NNTP_PASSWORD', ''),
    'alternate_server_password' => env('NNTP_PASSWORD_A', ''),
    'ssl' => env('NNTP_SSLENABLED', false),
    'alternate_server_ssl' => env('NNTP_SSLENABLED_A', false),
    'socket_timeout' => env('NNTP_SOCKET_TIMEOUT', 120),
    'alternate_server_socket_timeout' => env('NNTP_SOCKET_TIMEOUT_A', 120),
    'main_nntp_connections' => env('NNTP_CONNECTIONS', 1),
    'alternate_nntp_connections' => env('NNTP_CONNECTIONS_A', 1),
];
