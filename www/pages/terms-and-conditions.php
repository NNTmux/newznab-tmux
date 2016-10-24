<?php

$page->title = "Terms and Conditions";
$page->meta_title = $page->getSettingValue('..title')." - Terms and conditions";
$page->meta_keywords = "terms,conditions";
$page->meta_description = "Terms and Conditions for ".$page->getSettingValue('..title');

$page->content = $page->smarty->fetch('terms.tpl');

$page->render();

