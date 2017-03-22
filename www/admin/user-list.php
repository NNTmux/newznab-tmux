<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'smarty.php';

$page = new AdminPage();

$page->title = 'User List';

$roles = [];
foreach ($page->users->getRoles() as $userRole) {
	$roles[$userRole['id']] = $userRole['name'];
}

$offset = $_REQUEST['offset'] ?? 0;
$ordering = $page->users->getBrowseOrdering();
$orderBy = isset($_REQUEST['ob']) && in_array($_REQUEST['ob'], $ordering, true) ? $_REQUEST['ob'] : '';

$variables = ['username' => '', 'email' => '', 'host' => '', 'role' => ''];
$uSearch = '';
foreach ($variables as $key => $variable) {
	checkREQUEST($key);
}

$page->smarty->assign([
		'username'          => $variables['username'],
		'email'             => $variables['email'],
		'host'              => $variables['host'],
		'role'              => $variables['role'],
		'role_ids'          => array_keys($roles),
		'role_names'        => $roles,
		'pagerquerysuffix'  => '#results',
		'pagertotalitems'   => $page->users->getCount($variables['role']),
		'pageroffset'       => $offset,
		'pageritemsperpage' => ITEMS_PER_PAGE,
		'pagerquerybase'    => WWW_TOP . '/user-list.php?ob=' . $orderBy . $uSearch . '&offset=',
		'userlist' => $page->users->getRange(
			$offset, ITEMS_PER_PAGE, $orderBy, $variables['username'],
			$variables['email'], $variables['host'], $variables['role'], true
		)
	]
);

foreach ($ordering as $orderType) {
	$page->smarty->assign('orderby' . $orderType, WWW_TOP . '/user-list.php?ob=' . $orderType . '&offset=0');
}

$page->smarty->assign('pager', $page->smarty->fetch('pager.tpl'));
$page->content = $page->smarty->fetch('user-list.tpl');
$page->render();

function checkREQUEST($param) {
	global $uSearch, $variables;
	if (isset($_REQUEST[$param])) {
		$variables[$param] = $_REQUEST[$param];
		$uSearch .= "&$param=" . $_REQUEST[$param];
	}
}
