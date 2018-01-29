<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fixture source path
    |--------------------------------------------------------------------------
    |
    | The path to where you store your fixtures.
    |
    */
    'location' => realpath(base_path()) . '/database/fixtures',

    /*
    |--------------------------------------------------------------------------
    | Fixture chunk size
    |--------------------------------------------------------------------------
    |
    | The size of each insert chunk columns x rows <= $chunk_size.
    |
    */
   'chunk_size' => 500,
];