<?php
require_once(WWW_DIR."/lib/util.php");
require_once(WWW_DIR."/lib/sabnzbd.php");

/**
 * Class NZBGet
 */
class NZBGet
{
    public $username = '';
    public $password = '';
    public $url = '';
    public $uid = 0;
    public $rsstoken = '';
    public $serverurl = '';

	/**
	 * @param $page
	 */
	public function __construct(&$page)
    {
        $this->serverurl = $page->serverurl;
        $this->uid       = $page->userdata['id'];
        $this->rsstoken  = $page->userdata['rsstoken'];

        switch($page->site->sabintegrationtype)
        {
            case SABnzbd::INTEGRATION_TYPE_USER:
                if (!empty($_COOKIE['nzbget_'.$this->uid.'__host']))
                {
                    $this->url = $_COOKIE['nzbget_'.$this->uid.'__host'];
                    $this->username = $_COOKIE['nzbget_'.$this->uid.'__username'];
                    $this->password = $_COOKIE['nzbget_'.$this->uid.'__password'];
                }
                elseif (!empty($page->userdata['nzbgeturl']))
                {
                    $this->url = $page->userdata['nzbgeturl'];
                    $this->username = $page->userdata['nzbgetusername'];
                    $this->password = $page->userdata['nzbgetpassword'];
                }
                $this->integrated = SABnzbd::INTEGRATION_TYPE_USER;
                break;
            case SABnzbd::INTEGRATION_TYPE_SITEWIDE:
                if (!empty($page->site->nzbgeturl))
                {
                    $this->url = $page->site->nzbgeturl;
                    $this->username = $page->site->nzbgetusername;
                    $this->password = $page->site->nzbgetpassword;
                }
                $this->integrated = SABnzbd::INTEGRATION_TYPE_SITEWIDE;
                break;
        }
    }

    public function fullurl()
    {
        $url = $this->url;
        if (preg_match('/(?P<protocol>https?):\/\/(?P<url>.+?)(:(?P<port>\d+\/)|\/)$/i',
            $url,
            $matches)) {
            return
                $matches['protocol'] .
                '://' .
                $this->username .
                ':' .
                $this->password .
                '@' .
                $matches['url'] .
                (isset($matches['port']) ? ':' . $matches['port'] : (substr($matches['url'], -1) === '/' ? '' : '/')) .
                'xmlrpc/';
        } else {
            return false;
        }
    }

    public function sendToNZBGet($guid)
    {
        $releases = new Releases();
        $reldata = $releases->getByGuid($guid);
        $url     = "{$this->serverurl}getnzb/{$guid}&amp;i={$this->uid}&amp;r={$this->rsstoken}";
        $header  = <<<NZBGet_URL
<?xml version="1.0"?>
<methodCall>
	<methodName>appendurl</methodName>
	<params>
		<param>
			<value><string>{$reldata['searchname']}.nzb</string></value>
		</param>
		<param>
			<value><string>{$reldata['category_name']}</string></value>
		</param>
		<param>
			<value><i4>0</i4></value>
		</param>
		<param>
			<value><boolean>>False</boolean></value>
		</param>
		<param>
			<value>
				<string>$url</string>
			</value>
		</param>
	</params>
</methodCall>
NZBGet_URL;

        Utility::getUrl(['url' => $this->fullurl()."appendurl",'method' => "POST", 'postdata' => $header, 'verifycert' => false]);
    }

    public function pauseAll()
    {
        $header = <<<NZBGet_PAUSE_ALL
<?xml version="1.0"?>
<methodCall>
	<methodName>pausedownload2</methodName>
	<params>
		<param>
			<value><boolean>1</boolean></value>
		</param>
	</params>
</methodCall>
NZBGet_PAUSE_ALL;

        Utility::getUrl(['url' => $this->fullurl().'pausedownload2', 'method' => "POST", 'postdata' =>$header, 'verifycert' => false]);
    }

    public function resumeAll()
    {
        $header = <<<NZBGet_RESUME_ALL
<?xml version="1.0"?>
<methodCall>
	<methodName>resumedownload2</methodName>
	<params>
		<param>
			<value><boolean>1</boolean></value>
		</param>
	</params>
</methodCall>'
NZBGet_RESUME_ALL;

        Utility::getUrl(['url' => $this->fullurl().'resumedownload2', 'method' => "POST", 'postdata' => $header, 'verifycert' => false]);
    }

    public function delFromQueue($id)
    {
        $header = <<<NZBGet_DELETE_FROM_QUEUE
<?xml version="1.0"?>
<methodCall>
	<methodName>editqueue</methodName>
	<params>
		<param>
			<value><string>GroupDelete</string></value>
		</param>
		<param>
			<value><i4>0</i4></value>
		</param>
		<param>
			<value><string>""</string></value>
		</param>
		<param>
			<value>
				<array>
					<value><i4>$id</i4></value>
				</array>
			</value>
		</param>
	</params>
</methodCall>
NZBGet_DELETE_FROM_QUEUE;

        Utility::getUrl(['url' => $this->fullurl().'editqueue', 'method' => "POST", 'postdata' => $header, 'verifycert' => false]);
    }

    public function getQueue()
    {
        $data = Utility::getUrl(['url' => $this->fullurl().'listgroups', 'verifycert' => false]);
        $retVal = false;
        if ($data) {
            $xml = simplexml_load_string($data);
            if ($xml) {
                $retVal = array();
                $i = 0;
                foreach ($xml->params->param->value->array->data->value as $value) {
                    foreach ($value->struct->member as $member) {
                        $value = (array)$member->value;
                        $value = array_shift($value);
                        if (!is_object($value)) {
                            $retVal[$i][(string)$member->name] = $value;
                        }
                    }
                    $i++;
                }
            }
        }

        return $retVal;
    }

    public function status()
    {
        $data = Utility::getUrl(['url' => $this->fullurl()."status", 'verifycert' => false]);
        $retVal = false;
        if ($data) {
            $xml = simplexml_load_string($data);
            if ($xml) {
                foreach ($xml->params->param->value->struct->member as $member) {
                    $value = (array)$member->value;
                    $value = array_shift($value);
                    if (!is_object($value)) {
                        $retVal[(string)$member->name] = $value;
                    }
                }
            }
        }

        return $retVal;
    }

    public function checkCookie()
    {
        $res = false;
        if (isset($_COOKIE['nzbget_'.$this->uid.'__host']))
            $res = true;
        if (isset($_COOKIE['nzbget_'.$this->uid.'__username']))
            $res = true;
        if (isset($_COOKIE['nzbget_'.$this->uid.'__password']))
            $res = true;

        return $res;
    }

    public function setCookie($host, $username, $password)
    {
        setcookie('nzbget_'.$this->uid.'__host', $host, (time()+2592000));
        setcookie('nzbget_'.$this->uid.'__username', $username, (time()+2592000));
        setcookie('nzbget_'.$this->uid.'__password', $password, (time()+2592000));
    }

    public function unsetCookie()
    {
        setcookie('nzbget_'.$this->uid.'__host', '', (time()-2592000));
        setcookie('nzbget_'.$this->uid.'__username', '', (time()-2592000));
        setcookie('nzbget_'.$this->uid.'__password', '', (time()-2592000));
    }
}