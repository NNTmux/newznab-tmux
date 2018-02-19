<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

$tmux = \App\Models\Tmux::all();

foreach ($tmux as $item) {
    echo 'Inserting '.$item->setting.' into settings table'.PHP_EOL;
    \App\Models\Settings::insertIgnore(
            [
                'section' => 'site',
                'subsection' => 'tmux',
                'name' => $item->setting,
                'value' => $item->value,
                'setting'=> $item->setting,
            ]
        );
}
