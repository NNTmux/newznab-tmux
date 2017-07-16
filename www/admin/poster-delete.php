<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';


use App\models\MultigroupPosters;

$page = new AdminPage();

if (isset($_GET['id']))
{
	MultigroupPosters::query()->where('id', '=', $_GET['id'])->delete();
}

if (isset($_GET['from'])) {
	$referrer = $_GET['from'];
} else {
	$referrer = $_SERVER['HTTP_REFERER'];
}
header('Location: ' . $referrer);
