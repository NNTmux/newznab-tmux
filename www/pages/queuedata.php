<?php

if (!$page->users->isLoggedIn())
	$page->show403();

$sab = new SABnzbd($page);
$nzbget = new NZBGet($page);

if (empty($sab->url) && empty($sab->apikey) && empty($nzbget->url) && empty($nzbget->username))
	$page->show404();

$output = "";
$reqtype = isset($_REQUEST["type"]) ? $_REQUEST["type"] : "queue";

switch ($reqtype)
{
    case "queue":
    {
        $json = $sab->getQueue();
        if ($json !== false)
        {
            $obj = json_decode($json);
            $queue = $obj->{'jobs'};
            $count = 1;

            $speed = $obj->{'speed'};
            $queued = round($obj->{'mbleft'}, 2)."MB / ".round($obj->{'mb'}, 2)."MB";
            $status = ucwords(strtolower($obj->{'state'}));

            $output .= "<p><b>Download speed:</b> ".$speed."B/s - <b>Queued:</b> ".$queued." - <b>Status:</b> ".$status."</p>";

            if (count($queue) > 0)
            {
                $output.="<table class=\"data highlight\">";
                $output.="<tr>
                            <th></th>
                            <th>Name</th>
                            <th style='width:80px;'>size</th>
                            <th style='width:80px;'>left</th>
                            <th style='width:50px;'>%</th>
                            <th>time left</th>
                            <th></th>
                            </tr>";
                foreach ($queue as $item)
                {
                    if (strpos($item->{'filename'}, "fetch NZB") > 0)
                    {
                    }
                    else
                    {
                        $output.="<tr>";
                        $output.="<td style='text-align:right;'>".$count."</td>";
                        $output.="<td>".htmlspecialchars($item->{'filename'}, ENT_QUOTES)."</td>";
                        $output.="<td style='text-align:right;'>".round($item->{'mb'}, 2)." MB</td>";
                        $output.="<td class='right'>".round($item->{'mbleft'}, 2)." MB</td>";
                        $output.="<td class='right'>".($item->{'mb'}==0?0:round($item->{'mbleft'}/$item->{'mb'}*100))."%</td>";
                        $output.="<td style='text-align:right;'>".$item->{'timeleft'}."</td>";
                        $output.="<td style='text-align:right;'><a  onclick=\"return confirm('Are you sure?');\" href='?del=".$item->{'id'}."'>delete</a></td>";
                        $output.="</tr>";
                        $count++;
                    }
                }
                $output.="</table>";
            }
            else
            {
                $output.="<p>The queue is currently empty.</p>";
            }
        }
        else
        {
            $output.="<p>Error retreiving queue.</p>";
        }

        break;
    }
    case "history":
    {
        $json = $sab->getHistory();
        if ($json !== false)
        {
            $obj = json_decode($json);
            $history = $obj->history->slots;
            $count = 1;

            if (count($history) > 0)
            {
                $output.="<h2>Download History</h2>";
                $output.="<table class=\"data highlight\">";
                $output.="<tr>
                            <th style='width:20px;'></th>
                            <th>Name</th>
                            <th>Category</th>
                            <th style='text-align:center;'>status</th>
                            <th style='width:80px; text-align:center;'>size</th>
                            <th style='text-align:center;'>dl time</th>
                            <th style='text-align:center;'>date</th>
                            </tr>";
                foreach ($history as $item)
                {
                    $output.="<tr>";
                    $output.="<td class='".($item->{'fail_message'} != "" ? "sabhistoryfail" : "sabhistorysuccess")."'></td>";
                    $output.="<td>".htmlspecialchars($item->{'name'}, ENT_QUOTES)."</td>";
                    $output.="<td style='text-align:center;'>".htmlspecialchars($item->{'category'}, ENT_QUOTES)."</td>";
                    $output.="<td title='".htmlspecialchars($item->{'fail_message'}, ENT_QUOTES)."' style='text-align:center;'>".$item->{'status'}."</td>";
                    $output.="<td style='text-align:right;'>".$item->{'size'}."</td>";
                    $output.="<td style='text-align:right;'>".gmdate("H:i:s", $item->{'download_time'})."</td>";
                    $output.="<td style='text-align:right;'>".gmdate("Y-m-d H:i", $item->{'completed'})."</td>";
                    $output.="</tr>\n";
                    $count++;
                }
                $output.="</table>";
            }
        }

        break;
    }
    case "nzbget":
    {
        $queue = $nzbget->getQueue();
        if (is_array($queue) && count($queue) > 0)
        {
            $count = 1;
            $output.="<table class=\"data highlight\">";
            $output.="<tr>
                            <th></th>
                            <th>Name</th>
                            <th style='width:80px;'>size</th>
                            <th style='width:80px;'>left</th>
                            <th style='width:50px;'>%</th>
                            <th>left</th>
                            <th></th>
                            </tr>";
            foreach ($queue as $item)
            {
                $output.="<tr>";
                $output.="<td style='text-align:right;'>".$count."</td>";
                $output.="<td>".htmlspecialchars($item['NZBNicename'], ENT_QUOTES)."</td>";
                $output.="<td style='text-align:right;'>".round($item['FileSizeMB'], 2)." MB</td>";
                $output.="<td class='right'>".round($item['RemainingSizeMB'], 2)." MB</td>";
                $output.="<td class='right'>".($item['FileSizeMB']==0?0:round($item['RemainingSizeMB']/$item['FileSizeMB']*100))."%</td>";
                $output.="<td style='text-align:right;'>".$item['RemainingFileCount']."</td>";
                $output.="<td style='text-align:right;'><a  onclick=\"return confirm('Are you sure?');\" href='?nzbgetdel=".$item['NZBID']."'>delete</a></td>";
                $output.="</tr>";
                $count++;
            }
            $output.="</table>";
        }
        else
        {
            $output.="<p>The queue is currently empty.</p>";
        }

        break;
    }
}

print $output;
