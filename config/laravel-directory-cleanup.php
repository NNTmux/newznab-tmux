<?php

return [

    'directories' => [

        /*
         * Here you can specify which directories need to be cleanup. All files older than
         * the specified amount of minutes will be deleted.
         */

        /*
        'path/to/a/directory' => [
            'deleteAllOlderThanMinutes' => 60 * 24,
        ],
        */
        env('TEMP_UNRAR_PATH', resource_path().'/tmp/unrar') => [
            'deleteAllOlderThanMinutes' => 60 * 4,
        ],
        env('TEMP_UNZIP_PATH', resource_path().'/tmp/unzip') => [
            'deleteAllOlderThanMinutes' => 60 * 4,
        ],
    ],

    /*
     * If a file is older than the amount of minutes specified, a cleanup policy will decide if that file
     * should be deleted. By default every file that is older that the specified amount of minutes
     * will be deleted.
     *
     * You can customize this behaviour by writing your own clean up policy.  A valid policy
     * is any class that implements `Spatie\DirectoryCleanup\Policies\CleanupPolicy`.
     */
    'cleanup_policy' => Spatie\DirectoryCleanup\Policies\DeleteEverything::class,
];
