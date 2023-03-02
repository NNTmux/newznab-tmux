<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Blacklight\NZBGet;
use Blacklight\NZBVortex;
use Blacklight\SABnzbd;
use Illuminate\Http\Request;

class QueueController extends BasePageController
{
    /**
     * @throws \Exception
     */
    public function index(Request $request): void
    {
        $this->setPrefs();

        $queueType = $error = '';
        $queue = null;
        switch (Settings::settingValue('apps.sabnzbplus.integrationtype')) {
            case SABnzbd::INTEGRATION_TYPE_NONE:
                if ($this->userdata->queuetype === 2) {
                    $queueType = 'NZBGet';
                    $queue = new NZBGet($this);
                }
                break;
            case SABnzbd::INTEGRATION_TYPE_USER:
                switch ((int) $this->userdata->queuetype) {
                    case 1:
                        $queueType = 'Sabnzbd';
                        $queue = new SABnzbd($this);
                        break;
                    case 2:
                        $queueType = 'NZBGet';
                        $queue = new NZBGet($this);
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
                if ($request->has('del')) {
                    $queue->delFromQueue($request->input('del'));
                }

                if ($request->has('pause')) {
                    $queue->pauseFromQueue($request->input('pause'));
                }

                if ($request->has('resume')) {
                    $queue->resumeFromQueue($request->input('resume'));
                }

                if ($request->has('pall')) {
                    $queue->pauseAll();
                }

                if ($request->has('rall')) {
                    $queue->resumeAll();
                }

                $this->smarty->assign('serverURL', $queue->url);
            }
        }

        $this->smarty->assign(
            [
                'queueType' => $queueType,
                'error' => $error,
                'user' => $this->userdata,
            ]
        );
        $title = 'Your '.$queueType.' Download Queue';
        $meta_title = 'View'.$queueType.' Queue';
        $meta_keywords = 'view,'.strtolower($queueType).',queue';
        $meta_description = 'View'.$queueType.' Queue';
        $content = $this->smarty->fetch('viewqueue.tpl');
        $this->smarty->assign(compact('title', 'content', 'meta_title', 'meta_keywords', 'meta_description'));
        $this->pagerender();
    }

    /**
     * @throws \Exception
     */
    public function nzbget()
    {
        $this->setPrefs();
        $nzbGet = new NZBGet($this);

        $output = '';
        $data = $nzbGet->getQueue();

        if ($data !== false) {
            if (\count($data) > 0) {
                $status = $nzbGet->status();

                if ($status !== false) {
                    $output .=
                        "<div class='container text-center' style='display:block;'>
				<div style='width:16.666666667%;float:left;'><b>Avg Speed:</b><br /> ".human_filesize($status['AverageDownloadRate'], 2)."/s </div>
				<div style='width:16.666666667%;float:left;'><b>Speed:</b><br /> ".human_filesize($status['DownloadRate'], 2)."/s </div>
				<div style='width:16.666666667%;float:left;'><b>Limit:</b><br /> ".human_filesize($status['DownloadLimit'], 2)."/s </div>
				<div style='width:16.666666667%;float:left;'><b>Queue Left(no pars):</b><br /> ".human_filesize($status['RemainingSizeLo'], 2)." </div>
				<div style='width:16.666666667%;float:left;'><b>Free Space:</b><br /> ".human_filesize($status['FreeDiskSpaceMB'] * 1024000, 2)." </div>
				<div style='width:16.666666667%;float:left;'><b>Status:</b><br /> ".($status['Download2Paused'] === 1 ? 'Paused' : 'Downloading').' </div>
			</div>';
                }

                $count = 1;
                $output .=
                    "<table class='table table-striped table-condensed table-highlight data'>
				<thead>
					<tr >
						<th style='width=10px;text-align:center;'>#</th>
						<th style='text-align:left;'>Name</th>
						<th style='width:80px;text-align:center;'>Size</th>
						<th style='width:80px;text-align:center;'>Left(+pars)</th>
						<th style='width:50px;text-align:center;'>Done</th>
						<th style='width:80px;text-align:center;'>Status</th>
						<th style='width:50px;text-align:center;'>Delete</th>
						<th style='width:80px;text-align:center;'><a href='?pall'>Pause all</a></th>
						<th style='width:80px;text-align:center;'><a href='?rall'>Resume all</a></th>
					</tr>
				</thead>
				<tbody>";

                foreach ($data as $item) {
                    $output .=
                        '<tr>'.
                        "<td style='text-align:center;width:10px'>".$count.'</td>'.
                        "<td style='text-align:left;'>".$item['NZBName'].'</td>'.
                        "<td style='text-align:center;'>".$item['FileSizeMB'].' MB</td>'.
                        "<td style='text-align:center;'>".$item['RemainingSizeMB'].' MB</td>'.
                        "<td style='text-align:center;'>".($item['FileSizeMB'] === 0 ? 0 : round(100 - ($item['RemainingSizeMB'] / $item['FileSizeMB']) * 100)).'%</td>'.
                        "<td style='text-align:center;'>".($item['ActiveDownloads'] > 0 ? 'Downloading' : 'Paused').'</td>'.
                        "<td style='text-align:center;'><a  onclick=\"return confirm('Are you sure?');\" href='?del=".$item['LastID']."'>Delete</a></td>".
                        "<td style='text-align:center;'><a href='?pause=".$item['LastID']."'>Pause</a></td>".
                        "<td style='text-align:center;'><a href='?resume=".$item['LastID']."'>Resume</a></td>".
                        '</tr>';
                    $count++;
                }
                $output .=
                    '</tbody>
		</table>';
            } else {
                $output .= "<br /><br /><p style='text-align:center;'>The queue is currently empty.</p>";
            }
        } else {
            $output .= "<p style='text-align:center;'>Error retreiving queue.</p>";
        }

        echo $output;
    }

    /**
     * @throws \Exception
     */
    public function sabnzbd()
    {
        $this->setPrefs();
        $sab = new SABnzbd($this);

        $output = '';

        $json = $sab->getAdvQueue();

        if ($json !== false) {
            $obj = json_decode($json);
            $queue = $obj->{'queue'};
            $count = 1;

            $output .=
                "<div class='text-center' style='display:block;'>
			<div style='width:16.666666667%;float:left;'><b>Speed:</b><br /> ".$obj->{'speed'}."B/s </div>
			<div style='width:16.666666667%;float:left;'><b>Queued:</b><br /> ".round($obj->{'mbleft'}, 2).'MB / '.round($obj->{'mb'}, 2).'MB'." </div>
			<div style='width:16.666666667%;float:left;'><b>Status:</b><br /> ".ucwords(strtolower($obj->{'state'}))." </div>
			<div style='width:16.666666667%;float:left;'><b>Free (temp):</b><br /> ".round($obj->{'diskspace1'})."GB </div>
			<div style='width:16.666666667%;float:left;'><b>Free Space:</b><br /> ".round($obj->{'diskspace2'})."GB</div>
			<div style='width:16.666666667%;float:left;'><b>Stats:</b><br /> ".preg_replace('/\s+\|\s+| /', ',', $obj->{'loadavg'}).' </div>
		</div>';

            if (\count($queue) > 0) {
                $output .=
                    "<table class='table table-striped table-condensed table-highlight data'>
				<thead>
					<tr >
						<th style='width=10px;text-align:center;'>#</th>
						<th style='text-align:left;'>Name</th>
						<th style='width:80px;text-align:center;'>Size</th>
						<th style='width:80px;text-align:center;'>Left</th>
						<th style='width:50px;text-align:center;'>Done</th>
						<th style='width:80px;text-align:center;'>Time Left</th>
						<th style='width:50px;text-align:center;'>Delete</th>
						<th style='width:80px;text-align:center;'><a href='?pall'>Pause all</a></th>
						<th style='width:80px;text-align:center;'><a href='?rall'>Resume all</a></th>
					</tr>
				</thead>
				<tbody>";

                foreach ($queue->{'slots'} as $item) {
                    if (strpos($item->{'filename'}, 'fetch NZB') === false) {
                        $output .=
                            '<tr>'.
                            "<td style='text-align:center;width:10px'>".$count.'</td>'.
                            "<td style='text-align:left;'>".$item->{'filename'}.'</td>'.
                            "<td style='text-align:center;'>".round($item->{'mb'}, 2).' MB</td>'.
                            "<td style='text-align:center;'>".round($item->{'mbleft'}, 2).' MB</td>'.
                            "<td style='text-align:center;'>".($item->{'mb'} === 0 ? 0 : round(100 - ($item->{'mbleft'} / $item->{'mb'}) * 100)).'%</td>'.
                            "<td style='text-align:center;'>".$item->{'timeleft'}.'</td>'.
                            "<td style='text-align:center;'><a  onclick=\"return confirm('Are you sure?');\" href='?del=".$item->{'id'}."'>Delete</a></td>".
                            "<td style='text-align:center;'><a href='?pause=".$item->{'id'}."'>Pause</a></td>".
                            "<td style='text-align:center;'><a href='?resume=".$item->{'id'}."'>Resume</a></td>".
                            '</tr>';
                        $count++;
                    }
                }
                $output .=
                    '</tbody>
			</table>';
            } else {
                $output .= "<br /><br /><p style='text-align:center;'>The queue is currently empty.</p>";
            }
        } else {
            $output .= "<p style='text-align:center;'>Error retrieving queue.</p>";
        }

        echo $output;
    }

    /**
     * @throws \Exception
     */
    public function nzbVortex()
    {
        $this->setPrefs();
        try {
            if (isset($_GET['isAjax'])) {
                $vortex = new NZBVortex;

                // I guess we Ajax this way.
                if (isset($_GET['getOverview'])) {
                    $overview = $vortex->getOverview();
                    $this->smarty->assign('overview', $overview);
                    $content = $this->smarty->fetch('nzbvortex-ajax.tpl');
                    echo $content;
                    exit;
                }

                if (isset($_GET['addQueue'])) {
                    $nzb = $_GET['addQueue'];
                    $vortex->addQueue($nzb);
                    exit;
                }

                if (isset($_GET['resume'])) {
                    $vortex->resume((int) $_GET['resume']);
                    exit;
                }

                if (isset($_GET['pause'])) {
                    $vortex->pause((int) $_GET['pause']);
                    exit;
                }

                if (isset($_GET['moveup'])) {
                    $vortex->moveUp((int) $_GET['moveup']);
                    exit;
                }

                if (isset($_GET['movedown'])) {
                    $vortex->moveDown((int) $_GET['movedown']);
                    exit;
                }

                if (isset($_GET['movetop'])) {
                    $vortex->moveTop((int) $_GET['movetop']);
                    exit;
                }

                if (isset($_GET['movebottom'])) {
                    $vortex->moveBottom((int) $_GET['movebottom']);
                    exit;
                }

                if (isset($_GET['delete'])) {
                    $vortex->delete((int) $_GET['delete']);
                    exit;
                }

                if (isset($_GET['filelist'])) {
                    $response = $vortex->getFilelist((int) $_GET['filelist']);
                    echo json_encode($response);
                    exit;
                }
            }
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            printf($e->getMessage());
            exit;
        }

        $title = 'NZBVortex';

        $content = $this->smarty->fetch('nzbvortex.tpl');

        $this->smarty->assign(compact('title', 'content'));

        $this->pagerender();
    }
}
