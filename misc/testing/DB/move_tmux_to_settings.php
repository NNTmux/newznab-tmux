<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$tmux = \Illuminate\Support\Facades\DB::table('tmux')->get();

foreach ($tmux as $item) {
    echo 'Inserting '.$item->setting.' into settings table'.PHP_EOL;
    \App\Models\Settings::insertOrIgnore(
        [
            'section' => 'site',
            'subsection' => 'tmux',
            'name' => $item->setting,
            'value' => $item->value,
            'setting' => $item->setting,
        ]
    );
}
