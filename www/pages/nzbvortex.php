<?php

if (!$users->isLoggedIn())
    $page->show403();

try
{
    if (isset($_GET['isAjax']))
    {
        $vortex = new NZBVortex;

        // I guess we Ajax this way.
        if (isset($_GET['getOverview']))
        {
            $overview = $vortex->getOverview();
            $page->smarty->assign('overview', $overview);
            $content = $page->smarty->fetch('nzbvortex-ajax.tpl');
            echo $content;
            exit;
        }

        if (isset($_GET['addQueue']))
        {
            $nzb = $_GET['addQueue'];
            $vortex->addQueue($nzb);
            exit;
        }

        if (isset($_GET['resume']))
        {
            $vortex->resume((int)$_GET['resume']);
            exit;
        }

        if (isset($_GET['pause']))
        {
            $vortex->pause((int)$_GET['pause']);
            exit;
        }

        if (isset($_GET['moveup']))
        {
            $vortex->moveUp((int)$_GET['moveup']);
            exit;
        }

        if (isset($_GET['movedown']))
        {
            $vortex->moveDown((int)$_GET['movedown']);
            exit;
        }

        if (isset($_GET['movetop']))
        {
            $vortex->moveTop((int)$_GET['movetop']);
            exit;
        }

        if (isset($_GET['movebottom']))
        {
            $vortex->moveBottom((int)$_GET['movebottom']);
            exit;
        }

        if (isset($_GET['delete']))
        {
            $vortex->delete((int)$_GET['delete']);
            exit;
        }

        if (isset($_GET['filelist']))
        {
            $response = $vortex->getFilelist((int)$_GET['filelist']);
            echo json_encode($response);
            exit;
        }
    }
}
catch (Exception $e)
{
    header('HTTP/1.1 500 Internal Server Error');
    printf($e->getMessage());
    exit;
}

$page->title = 'NZBVortex';

$page->content = $page->smarty->fetch('nzbvortex.tpl');
$page->render();