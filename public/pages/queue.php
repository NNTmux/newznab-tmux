<?php

use App\Models\User;
use Blacklight\NZBGet;
use Blacklight\SABnzbd;
use App\Models\Settings;
use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$userData = User::find(Auth::id());
if (! $userData) {
    $page->show404();
}
$page->smarty->assign('user', $userData);

$queueType = $error = '';
$queue = null;
switch (Settings::settingValue('apps.sabnzbplus.integrationtype')) {
    case SABnzbd::INTEGRATION_TYPE_NONE:
        if ($userData['queuetype'] === 2) {
            $queueType = 'NZBGet';
            $queue = new NZBGet($page);
        }
        break;
    case SABnzbd::INTEGRATION_TYPE_USER:
        switch ((int) $userData['queuetype']) {
            case 1:
                $queueType = 'Sabnzbd';
                $queue = new SABnzbd($page);
                break;
            case 2:
                $queueType = 'NZBGet';
                $queue = new NZBGet($page);
                break;
        }
        break;
}

if ($queue !== null) {
    if ($queueType === 'Sabnzbd') {
        if (empty($queue->url)) {
            $error = 'ERROR: The Sabnzbd URL is missing!';
        }

        if (empty($queue->apikey)) {
            if ($error === '') {
                $error = 'ERROR: The Sabnzbd API key is missing!';
            } else {
                $error .= ' The Sabnzbd API key is missing!';
            }
        }
    }

    if ($error === '') {
        if (request()->has('del')) {
            $queue->delFromQueue(request()->input('del'));
        }

        if (request()->has('pause')) {
            $queue->pauseFromQueue(request()->input('pause'));
        }

        if (request()->has('resume')) {
            $queue->resumeFromQueue(request()->input('resume'));
        }

        if (request()->has('pall')) {
            $queue->pauseAll();
        }

        if (request()->has('rall')) {
            $queue->resumeAll();
        }

        $page->smarty->assign('serverURL', $queue->url);
    }
}

$page->smarty->assign(['queueType' => $queueType, 'error' => $error, 'user', User::class]);
$page->title = 'Your '.$queueType.' Download Queue';
$page->meta_title = 'View'.$queueType.' Queue';
$page->meta_keywords = 'view,'.strtolower($queueType).',queue';
$page->meta_description = 'View'.$queueType.' Queue';
$page->content = $page->smarty->fetch('viewqueue.tpl');
$page->pagerender();
