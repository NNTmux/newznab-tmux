<?php

use Blacklight\http\AdminPage;

require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources/views/themes/smarty.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$response->send();
$kernel->terminate($request, $response);

$page = new AdminPage();

$page->title = 'Admin Hangout';
$page->content = $page->smarty->fetch('index.tpl');
$page->render();
