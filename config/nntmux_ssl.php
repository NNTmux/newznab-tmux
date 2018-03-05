<?php

return [
    'ssl_cafile' => env('SSL_CAFILE', ''),
    'ssl_capath' => env('SSL_CAPATH', ''),
    'ssl_verify_peer' => env('SSL_VERIFY_PEER', false),
    'ssl_verify_host' => env('SSL_VERIFY_HOST', false),
    'ssl_allow_self_signed' => env('SSL_ALLOW_SELF_SIGNED', true),
];
