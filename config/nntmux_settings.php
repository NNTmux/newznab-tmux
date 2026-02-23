<?php

return [
    'unrar_path' => env('UNRAR_PATH', '/usr/bin/unrar'),
    'unzip_path' => env('UNZIP_PATH', '/usr/bin/unzip'),
    'check_passworded_rars' => env('CHECK_PASSWORDED_RARS', false),
    'delete_passworded_releases' => env('DELETE_PASSWORDED_RELEASES', false),
    'delete_possible_passworded_releases' => env('DELETE_POSSIBLE_PASSWORDED_RELEASES', false),
    'extract_using_rarinfo' => env('EXTRACT_USING_RARINFO', false),
    'fetch_last_file' => env('FETCH_LAST_FILE', true),
    'path_to_nzbs' => env('PATH_TO_NZBS', storage_path('nzb')),
    'private_profiles' => env('PRIVATE_PROFILES', true),
    'store_user_ip' => env('STORE_USER_IP', false),
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    'mediainfo_path' => env('MEDIAINFO_PATH', '/usr/bin/mediainfo'),
    'lame_path' => env('LAME_PATH', '/usr/bin/lame'),
    'timeout_path' => env('TIMEOUT_PATH', '/usr/bin/timeout'),
    'magic_file_path' => env('MAGIC_FILE_PATH', '/usr/share/misc/magic'),
    'covers_path' => env('COVERS_PATH', storage_path('covers')),
    'add_par2' => env('ADD_PAR2', false),
];
