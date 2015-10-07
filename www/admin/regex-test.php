<?php

require_once("config.php");

$page = new AdminPage();
$reg = new ReleaseRegex();
$groups = new Groups();
$cat = new Category();
$id = 0;

// set the current action
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'view';

$numarticlesdefault = 20000;
$groupList = $groups->getAll();
array_unshift($groupList, ['id'=>0, 'name'=>'All Groups']);

$gid = $gnames = [];
$groupname = (isset($_REQUEST['groupname']) && !empty($_REQUEST['groupname'])) ? $_REQUEST['groupname'] : '';
$groupID = isset($_REQUEST['groupid']) ? $_REQUEST['groupid'] : '0';
$regex = (isset($_REQUEST['regex']) && !empty($_REQUEST['regex'])) ? $_REQUEST['regex'] : '/^(?P<name>.*)$/i';
$poster = (isset($_REQUEST['poster']) && !empty($_REQUEST['poster'])) ? $_REQUEST['poster'] : '';
$unreleased = isset($_REQUEST['unreleased']) ? $_REQUEST['unreleased'] : '';
$matchagainstbins = isset($_REQUEST['matchagainstbins']) ? $_REQUEST['matchagainstbins'] : '';
$numarticles = (isset($_REQUEST['numarticles']) && !empty($_REQUEST['numarticles'])) ? $_REQUEST['numarticles'] : $numarticlesdefault;
$clearexistingbins = isset($_REQUEST['clearexistingbins']) ? true : false;
foreach($groupList as $grp)
{
	$gid[$grp["id"]] = $grp["id"];
	$gnames[$grp["id"]] = $grp["name"];
}

$group = $groupname;
if ($group == '')
{
	if ($groupID == 0)
		$group = 0;
	else
		$group = $gnames[$groupID];
}

$page->smarty->assign('gid', $gid);
$page->smarty->assign('gnames', $gnames);
$page->smarty->assign('group', $group);
$page->smarty->assign('groupname', $groupname);
$page->smarty->assign('groupid', $groupID);
$page->smarty->assign('regex', $regex);
$page->smarty->assign('poster', $poster);
$page->smarty->assign('unreleased', $unreleased);
$page->smarty->assign('matchagainstbins', $matchagainstbins);
$page->smarty->assign('numarticles', $numarticles);

switch($action)
{
	case 'test':
		if (isset($_REQUEST["regex"]))
		{
			$matches = $reg->testRegex($_REQUEST['regex'], $group, $poster, $unreleased, $matchagainstbins);

			$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
			$page->smarty->assign('pagertotalitems',sizeof($matches));
			$page->smarty->assign('pageroffset',$offset);
			$page->smarty->assign('pageritemsperpage',ITEMS_PER_PAGE);
			$page->smarty->assign('pagerquerybase', WWW_TOP."/regex-test.php?action=test&groupname={$groupname}&groupid={$groupID}&regex=".urlencode($regex)."&poster=".urlencode($poster)."&unreleased={$unreleased}&matchagainstbins={$matchagainstbins}&offset=");
			$pager = $page->smarty->fetch("pager.tpl");
			$page->smarty->assign('pager', $pager);

			$matches = array_slice($matches, $offset, ITEMS_PER_PAGE);

			$page->smarty->assign('matches', $matches);
		}
		break;
	case 'fetch':
		$result = $reg->fetchTestBinaries($group, $numarticles, $clearexistingbins);

		$page->smarty->assign('error', implode('<br />', $result));

		break;
}

$page->title = "Release Regex Test";

$page->content = $page->smarty->fetch('regex-test.tpl');
$page->render();
