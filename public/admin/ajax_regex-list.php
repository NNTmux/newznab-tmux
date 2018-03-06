<?php

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

use App\Models\ReleaseRegex;


if (request()->has('action') && request()->input('action') === '2') {
    $id = (int) request()->input('regex_id');
    ReleaseRegex::query()->where('releases_id', $id);
    echo "Regex $id deleted.";
}
