<?php
namespace nntmux;
use nntmux\utility\Utility;

class NZBInfo
{
	public $source = '';
	public $metadata = [];
	public $groups = [];
	public $filecount = 0;
	public $parcount = 0;
	public $rarcount = 0;
	public $zipcount = 0;
	public $videocount = 0;
	public $audiocount = 0;
    public $imgcount = 0;
    public $srrcount = 0;
    public $txtcount = 0;
    public $sfvcount = 0;
	public $filesize = 0;
	public $poster	= '';
	public $postedfirst = 0;
	public $postedlast = 0;
	public $completion = 0;
	public $segmenttotal = 0;
	public $segmentactual = 0;
	public $gid = '';

	public $nzb = [];
	public $nfofiles = [];
	public $samplefiles = [];
	public $mediafiles = [];
	public $audiofiles = [];
	public $rarfiles = [];
    public $imgfiles = [];
    public $srrfiles = [];
    public $txtfiles = [];
    public $sfvfiles = [];
	public $segmentfiles = [];
    public $parfiles = [];

    private $isLoaded = false;
	private $loadAllVars = false;

	public function __construct()
	{
        $this->nfofileregex = '/[ "\(\[].*?\.(nfo|ofn)[ "\)\]]/iS';
        $this->mediafileregex = '/.*\.(AVI|VOB|MKV|MP4|TS|WMV|MOV|M4V|F4V|MPG|MPEG)(\.001)?[ "\)\]]/iS';
        $this->audiofileregex = '/\.(MP3|FLAC|AAC|OGG|AIFF)[ "\)\]]/iS';
        $this->rarfileregex = '/.*\W(?:part0*1|(?!part\d+)[^.]+)\.(rar|001)[ "\)\]]/iS';
        $this->imgfileregex = '/\.(jpe?g|gif)[ "\)\]]/iS';
        $this->srrfileregex = '/\.(srr)[ "\)\]]/iS';
        $this->txtfileregex = '/\.(txt)[ "\)\]]/iS';
        $this->sfvfileregex = '/\.(sfv)[ "\)\]]/iS';
    }

	public function loadFromString($str, $loadAllVars=false)
	{
		if (empty($this->source))
			$this->source = 'string';
		$this->loadAllVars = $loadAllVars;

		$xmlObj = @simplexml_load_string($str);
		if ($this->isValidNzb($xmlObj))
			$this->parseNzb($xmlObj);

		unset($xmlObj);

		return $this->isLoaded;
	}

    public function loadFromFile($loc, $loadAllVars=false)
    {
        $this->source = $loc;
        $this->loadAllVars = $loadAllVars;

        if (file_exists($loc))
        {
            if (preg_match('/\.(gz|zip)$/i', $loc, $ext))
            {
                switch(strtolower($ext[1]))
                {
                    case 'gz':
                        $loc = 'compress.zlib://'.$loc;
                        break;
                    case 'zip':
                        $zip = new ZipArchive;
                        if ($zip->open($loc) === true && $zip->numFiles == 1)
                            return $this->loadFromString($zip->getFromIndex(0), $loadAllVars);
                        else
                            $loc = 'zip://'.$loc;
                        break;
                }
            }

            libxml_use_internal_errors(true);
            $xmlObj = @simplexml_load_file($loc);
            if ($this->isValidNzb($xmlObj))
                $this->parseNzb($xmlObj);

            unset($xmlObj);
        }
        return $this->isLoaded;
    }

	public function summarize()
	{
		$out = [];
		$out[] = 'Reading from '.basename($this->source).'...';
		if (!empty($this->nfofiles))
			$out[] = ' -nfo detected';
		if (!empty($this->samplefiles))
			$out[] = ' -sample detected';
		if (!empty($this->mediafiles))
			$out[] = ' -media detected';
		if (!empty($this->audio))
			$out[] = ' -audio detected';

		if (!empty($this->metadata))
		{
			$out[] = ' -metadata:';
			foreach($this->metadata as $mk=>$mv)
				$out[] = '       -'.$mk.': '.$mv;
		}

		$out[] = ' -sngl: '.sizeof($this->segmentfiles);

		$out[] = ' -pstr: '.$this->poster;
		$out[] = ' -grps: '.implode(', ', $this->groups);
		$out[] = ' -size: '.round(($this->filesize / 1048576), 2).' MB in '.$this->filecount.' Files';
		$out[] = '       -'.$this->rarcount.' rars';
		$out[] = '       -'.$this->parcount.' pars';
        $out[] = '       -'.$this->sfvcount.' sfvs';
		$out[] = '       -'.$this->zipcount.' zips';
		$out[] = '       -'.$this->videocount.' videos';
		$out[] = '       -'.$this->audiocount.' audios';
		$out[] = ' -cmpltn: '.$this->completion.'% ('.$this->segmentactual.'/'.$this->segmenttotal.')';
		$out[] = ' -pstd: '.date("Y-m-d H:i:s", $this->postedlast);
		$out[] = '';
		$out[] = '';

		return implode(PHP_EOL, $out);
	}

	private function isValidNzb($xmlObj)
	{
		if (!$xmlObj || strtolower($xmlObj->getName()) != 'nzb' || !isset($xmlObj->file))
			return false;

        return true;
	}

	private function parseNzb($xmlObj)
	{
		//Metadata
		if (isset($xmlObj->head->meta))
		{
			foreach($xmlObj->head->meta as $meta)
			{
				if (isset($meta->attributes()->type))
				{
					$metaKey = (string) $meta->attributes()->type;
					$this->metadata[$metaKey] = (string) $meta;
				}
			}
		}

		//NZB GID = first segment of first file
		$gid = (string) $xmlObj->file->segments->segment;
		if (!empty($gid))
			$this->gid = md5($gid);

		foreach($xmlObj->file as $file)
		{
			$fileArr = [];
			$fileArr['subject'] = (string) $file->attributes()->subject;
			$fileArr['poster'] = (string) $file->attributes()->poster;
			$fileArr['posted'] = (int) $file->attributes()->date;
			$fileArr['groups'] = [];
			$fileArr['filesize'] = 0;
			$fileArr['segmenttotal'] = 0;
			$fileArr['segmentactual'] = 0;
			$fileArr['completion'] = 0;
			$fileArr['segments'] = [];

			//subject
			$subject = $fileArr['subject'];

			//poster
			$this->poster = $fileArr['poster'];

			//dates
			$date = $fileArr['posted'];
			if ($date > $this->postedlast || $this->postedlast == 0)
				$this->postedlast = $date;

			if ($date < $this->postedfirst || $this->postedfirst == 0)
				$this->postedfirst = $date;


			//groups
			foreach ($file->groups->group as $group)
			{
				$this->groups[] = (string) $group;
				$fileArr['groups'][] = (string) $group;
			}

			//file segments
			foreach($file->segments->segment as $segment)
			{
				$bytes = (int) $segment->attributes()->bytes;
				$number = (int) $segment->attributes()->number;

				$this->filesize += $bytes;
				$this->segmentactual++;

				$fileArr['filesize'] += $bytes;
				$fileArr['segmentactual']++;
				$fileArr['segments'][$number] = (string) $segment;
                $fileArr['segmentbytes'][$number] = $bytes;
			}

            $pattern = '|\((\d+)[\/](\d+)\)|i';
            preg_match_all($pattern, $subject, $matches, PREG_PATTERN_ORDER);
            $matchcnt = sizeof($matches[0]);
            $msgPart = $msgTotalParts = 0;
            for ($i=0; $i<$matchcnt; $i++)
            {
                //not (int)'d here because of the preg_replace later on
                $msgPart = $matches[1][$i];
                $msgTotalParts = $matches[2][$i];
            }
            if((int)$msgPart > 0 && (int)$msgTotalParts > 0)
            {
                $this->segmenttotal += (int) $msgTotalParts;
                $fileArr['segmenttotal'] = (int) $msgTotalParts;
                $fileArr['completion'] = number_format(($fileArr['segmentactual']/$fileArr['segmenttotal'])*100, 0);

                $fileArr['subject'] = utf8_encode(trim(preg_replace('|\('.$msgPart.'[\/]'.$msgTotalParts.'\)|i', '', $subject)));
            }

			//file counts
			$this->filecount++;

			if ($fileArr['segmenttotal'] == 1)
				$this->segmentfiles[] = $fileArr;

			if (preg_match($this->nfofileregex, $subject))
				$this->nfofiles[] = $fileArr;

			if (preg_match($this->mediafileregex, $subject) && preg_match('/sample[\.\-]/i', $subject) && !preg_match('/\.par2|\.srs/i', $subject))
				$this->samplefiles[] = $fileArr;

			if (preg_match($this->mediafileregex, $subject) && !preg_match('/sample[\.\-]/i', $subject) && !preg_match('/\.par2|\.srs/i', $subject))
			{
				$this->mediafiles[] = $fileArr;
				$this->videocount++;
			}

			if (preg_match('/\.(rar|r\d{2,3})(?!\.)/i', $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->rarcount++;

			if (preg_match($this->rarfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->rarfiles[] = $fileArr;

			if (preg_match($this->audiofileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
			{
				$this->audiofiles[] = $fileArr;
				$this->audiocount++;
			}

            if (preg_match($this->imgfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/iS', $subject))
            {
                $this->imgfiles[] = $fileArr;
                $this->imgcount++;
            }

            if (preg_match($this->srrfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/iS', $subject))
            {
                $this->srrfiles[] = $fileArr;
                $this->srrcount++;
            }

            if (preg_match($this->txtfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/iS', $subject))
            {
                $this->txtfiles[] = $fileArr;
                $this->txtcount++;
            }

            if (preg_match($this->sfvfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|nzb)/iS', $subject))
            {
                $this->sfvfiles[] = $fileArr;
                $this->sfvcount++;
            }

            if (preg_match('/\.par2(?!\.)/iS', $subject))
            {
                $this->parcount++;
                if (!preg_match('/(vol\d+\+|vol[_\.\s]\d)/iS', $subject) && $fileArr['segmenttotal'] < 3)
                    $this->parfiles[] = $fileArr;
            }

			if (preg_match('/\.zip(?!\.)/i', $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->zipcount++;

			if ($this->loadAllVars === true)
				$this->nzb[] = $fileArr;
			else
				$this->nzb[]['subject'] = $fileArr['subject'];
		}

		$this->groups = array_unique($this->groups);

		if ($this->segmenttotal > 0)
			$this->completion = number_format(($this->segmentactual/$this->segmenttotal)*100, 0);

		if (is_array($this->nzb) && !empty($this->nzb))
			$this->isLoaded = true;

		return $this->isLoaded;
	}

    public function toNzb()
    {
        if ($this->loadAllVars === false)
            return false;

        $nzb = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $nzb .= "<!DOCTYPE nzb PUBLIC \"-//newzBin//DTD NZB 1.1//EN\" \"http://www.newzbin.com/DTD/nzb/nzb-1.1.dtd\">\n";
        $nzb .= "<nzb xmlns=\"http://www.newzbin.com/DTD/2003/nzb\">\n\n";
        if (!empty($this->metadata))
        {
            $nzb .= "<head>\n";
			$out = [];
            foreach($this->metadata as $mk=>$mv)
                $out[] = ' <meta type="'.$mk.'">'.$mv."</meta>\n";
            $nzb .= "</head>\n";
        }
        foreach($this->nzb as $postFile)
        {
            $nzb .= "<file poster=\"".htmlspecialchars($postFile["poster"], ENT_QUOTES, 'utf-8')."\" date=\"".$postFile["posted"]."\" subject=\"".htmlspecialchars($postFile["subject"], ENT_QUOTES, 'utf-8')." (1/".$postFile["segmenttotal"].")\">\n";
            $nzb .= " <groups>\n";
            foreach($postFile['groups'] as $fileGroup)
            {
                $nzb .= "  <group>".$fileGroup."</group>\n";
            }
            $nzb .= " </groups>\n";
            $nzb .= " <segments>\n";
            foreach($postFile['segments'] as $fileSegmentNum=>$fileSegment)
            {
                $nzb .= "  <segment bytes=\"".$postFile['segmentbytes'][$fileSegmentNum]."\" number=\"".$fileSegmentNum."\">".Utility::htmlfmt($fileSegment)."</segment>\n";
            }
            $nzb .= " </segments>\n</file>\n";
        }
        $nzb .= "<!-- nntmux ".date("Y-m-d H:i:s")." -->\n</nzb>";

        return $nzb;
    }
}
