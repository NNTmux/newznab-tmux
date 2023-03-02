<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:
 *
 * @link      <http://www.gnu.org/licenses/>.
 *
 * @author    ruhllatio
 * @copyright 2016 nZEDb
 */

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Release;
use Illuminate\Support\Carbon;

/**
 * Class XMLReturn.
 */
class XML_Response
{
    /**
     * @var string The buffered cData before final write
     */
    protected string $cdata;

    /**
     * The RSS namespace used for the output.
     */
    protected string $namespace;

    /**
     * The trailing URL parameters on the request.
     */
    protected mixed $parameters;

    /**
     * The release we are adding to the stream.
     */
    protected mixed $release;

    /**
     * The retrieved releases we are returning from the API call.
     */
    protected mixed $releases;

    /**
     * The various server variables and active categories.
     */
    protected mixed $server;

    /**
     * The XML formatting operation we are returning.
     */
    protected mixed $type;

    /**
     * The XMLWriter Class.
     */
    protected \XMLWriter $xml;

    protected mixed $offset;

    /**
     * XMLReturn constructor.
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Parameters' => null,
            'Data' => null,
            'Server' => null,
            'Offset' => null,
            'Type' => null,
        ];
        $options += $defaults;

        $this->parameters = $options['Parameters'];
        $this->releases = $options['Data'];
        $this->server = $options['Server'];
        $this->offset = $options['Offset'];
        $this->type = $options['Type'];

        $this->xml = new \XMLWriter();
        $this->xml->openMemory();
        $this->xml->setIndent(true);
    }

    public function returnXML(): bool|string
    {
        if ($this->xml) {
            switch ($this->type) {
                case 'caps':
                    return $this->returnCaps();
                    break;
                case 'api':
                    $this->namespace = 'newznab';

                    return $this->returnApiXml();
                    break;
                case 'rss':
                    $this->namespace = 'nntmux';

                    return $this->returnApiRssXml();
                    break;
                case 'reg':
                    return $this->returnReg();
                    break;
            }
        }

        return false;
    }

    /**
     * XML writes and returns the API capabilities.
     *
     * @return string The XML Formatted string data
     */
    protected function returnCaps(): string
    {
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement('caps');
        $this->addNode(['name' => 'server', 'data' => $this->server['server']]);
        $this->addNode(['name' => 'limits', 'data' => $this->server['limits']]);
        $this->addNode(['name' => 'registration', 'data' => $this->server['registration']]);
        $this->addNodes(['name' => 'searching', 'data' => $this->server['searching']]);
        $this->writeCategoryListing();
        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * XML writes and returns the API data.
     *
     * @return string The XML Formatted string data
     */
    protected function returnApiRssXml(): string
    {
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->includeRssAtom(); // Open RSS
        $this->xml->startElement('channel'); // Open channel
        $this->includeRssAtomLink();
        $this->includeMetaInfo();
        $this->includeImage();
        $this->includeTotalRows();
        $this->includeLimits();
        $this->includeReleases();
        $this->xml->endElement(); // End channel
        $this->xml->endElement(); // End RSS
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * XML writes and returns the API data.
     *
     * @return string The XML Formatted string data
     */
    protected function returnApiXml(): string
    {
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->includeRssAtom(); // Open RSS
        $this->xml->startElement('channel'); // Open channel
        $this->includeMetaInfo();
        $this->includeImage();
        $this->includeTotalRows();
        $this->includeLimits();
        $this->includeReleases();
        $this->xml->endElement(); // End channel
        $this->xml->endElement(); // End RSS
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * @return string The XML formatted registration information
     */
    protected function returnReg(): string
    {
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement('register');
        $this->xml->writeAttribute('username', $this->parameters['username']);
        $this->xml->writeAttribute('password', $this->parameters['password']);
        $this->xml->writeAttribute('apikey', $this->parameters['token']);
        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * Starts a new element, loops through the attribute data and ends the element.
     *
     * @param  array  $element  An array with the name of the element and the attribute data
     */
    protected function addNode(array $element): void
    {
        $this->xml->startElement($element['name']);
        foreach ($element['data'] as $attr => $val) {
            $this->xml->writeAttribute($attr, $val);
        }
        $this->xml->endElement();
    }

    /**
     * Starts a new element, loops through the attribute data and ends the element.
     *
     * @param  array  $element  An array with the name of the element and the attribute data
     */
    protected function addNodes(array $element): void
    {
        $this->xml->startElement($element['name']);
        foreach ($element['data'] as $elem => $value) {
            $subelement['name'] = $elem;
            $subelement['data'] = $value;
            $this->addNode($subelement);
        }
        $this->xml->endElement();
    }

    /**
     * Adds the site category listing to the XML feed.
     */
    protected function writeCategoryListing(): void
    {
        $this->xml->startElement('categories');
        foreach ($this->server['categories'] as $this->parameters) {
            $this->xml->startElement('category');
            $this->xml->writeAttribute('id', $this->parameters['id']);
            $this->xml->writeAttribute('name', html_entity_decode($this->parameters['title']));
            if (! empty($this->parameters['description'])) {
                $this->xml->writeAttribute('description', html_entity_decode($this->parameters['description']));
            }
            foreach ($this->parameters['categories'] as $c) {
                $this->xml->startElement('subcat');
                $this->xml->writeAttribute('id', $c['id']);
                $this->xml->writeAttribute('name', html_entity_decode($c['title']));
                if (! empty($c['description'])) {
                    $this->xml->writeAttribute('description', html_entity_decode($c['description']));
                }
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }
    }

    /**
     * Adds RSS Atom information to the XML.
     */
    protected function includeRssAtom(): void
    {
        $url = match ($this->namespace) {
            'newznab' => 'http://www.newznab.com/DTD/2010/feeds/attributes/',
            default => $this->server['server']['url'].'/rss-info/',
        };

        $this->xml->startElement('rss');
        $this->xml->writeAttribute('version', '2.0');
        $this->xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $this->xml->writeAttribute("xmlns:{$this->namespace}", $url);
        $this->xml->writeAttribute('encoding', 'utf-8');
    }

    protected function includeRssAtomLink(): void
    {
        $this->xml->startElement('atom:link');
        $this->xml->startAttribute('href');
        $this->xml->text($this->server['server']['url'].($this->namespace === 'newznab' ? '/api/v1/api' : '/rss'));
        $this->xml->endAttribute();
        $this->xml->startAttribute('rel');
        $this->xml->text('self');
        $this->xml->endAttribute();
        $this->xml->startAttribute('type');
        $this->xml->text('application/rss+xml');
        $this->xml->endAttribute();
        $this->xml->endElement();
    }

    /**
     * Writes the channel information for the feed.
     */
    protected function includeMetaInfo(): void
    {
        $server = $this->server['server'];

        switch ($this->namespace) {
            case 'newznab':
                $path = '/apihelp/';
                $tag = 'API';
                break;
            case 'nntmux':
            default:
                $path = '/rss-info/';
                $tag = 'RSS';
        }

        $this->xml->writeElement('title', $server['title']);
        $this->xml->writeElement('description', $server['title']." {$tag} Details");
        $this->xml->writeElement('link', $server['url']);
        $this->xml->writeElement('language', 'en-gb');
        $this->xml->writeElement('webMaster', $server['email'].' '.$server['title']);
        $this->xml->writeElement('category', $server['meta']);
        $this->xml->writeElement('generator', 'nntmux');
        $this->xml->writeElement('ttl', '10');
        $this->xml->writeElement('docs', $this->server['server']['url'].$path);
    }

    /**
     * Adds nntmux logo data to the XML.
     */
    protected function includeImage(): void
    {
        $this->xml->startElement('image');
        $this->xml->writeAttribute('url', $this->server['server']['url'].'/assets/images/tmux_logo.png');
        $this->xml->writeAttribute('title', $this->server['server']['title']);
        $this->xml->writeAttribute('link', $this->server['server']['url']);
        $this->xml->writeAttribute(
            'description',
            'Visit '.$this->server['server']['title'].' - '.$this->server['server']['strapline']
        );
        $this->xml->endElement();
    }

    public function includeTotalRows(): void
    {
        $this->xml->startElement($this->namespace.':response');
        $this->xml->writeAttribute('offset', $this->offset);
        $this->xml->writeAttribute('total', $this->releases[0]->_totalrows ?? 0);
        $this->xml->endElement();
    }

    public function includeLimits(): void
    {
        $this->xml->startElement($this->namespace.':apilimits');
        $this->xml->writeAttribute('apicurrent', $this->parameters['requests']);
        $this->xml->writeAttribute('apimax', $this->parameters['apilimit']);
        $this->xml->writeAttribute('grabcurrent', $this->parameters['grabs']);
        $this->xml->writeAttribute('grabmax', $this->parameters['downloadlimit']);
        if (! empty($this->parameters['oldestapi'])) {
            $this->xml->writeAttribute('apioldesttime', $this->parameters['oldestapi']);
        }
        if (! empty($this->parameters['oldestgrab'])) {
            $this->xml->writeAttribute('graboldesttime', $this->parameters['oldestgrab']);
        }
        $this->xml->endElement();
    }

    /**
     * Loop through the releases and add their info to the XML stream.
     */
    public function includeReleases(): void
    {
        if (! empty($this->releases)) {
            if (! $this->releases instanceof Release) {
                foreach ($this->releases as $this->release) {
                    $this->xml->startElement('item');
                    $this->includeReleaseMain();
                    $this->setZedAttributes();
                    $this->xml->endElement();
                }
            } else {
                $this->release = $this->releases;
                $this->xml->startElement('item');
                $this->includeReleaseMain();
                $this->setZedAttributes();
                $this->xml->endElement();
            }
        }
    }

    /**
     * Writes the primary release information.
     */
    public function includeReleaseMain(): void
    {
        $this->xml->writeElement('title', $this->release->searchname);
        $this->xml->startElement('guid');
        $this->xml->writeAttribute('isPermaLink', 'true');
        $this->xml->text("{$this->server['server']['url']}/details/{$this->release->guid}");
        $this->xml->endElement();
        $this->xml->writeElement(
            'link',
            "{$this->server['server']['url']}/getnzb?id={$this->release->guid}.nzb".
            "&r={$this->parameters['token']}".
            ((int) $this->parameters['del'] === 1 ? '&del=1' : '')
        );
        $this->xml->writeElement('comments', "{$this->server['server']['url']}/details/{$this->release->guid}#comments");
        $this->xml->writeElement('pubDate', date(DATE_RSS, strtotime($this->release->adddate)));
        $this->xml->writeElement('category', $this->release->category_name);
        if ($this->namespace === 'newznab') {
            $this->xml->writeElement('description', $this->release->searchname);
        } else {
            $this->writeRssCdata();
        }
        if (! isset($this->parameters['dl']) || (isset($this->parameters['dl']) && (int) $this->parameters['dl'] === 1)) {
            $this->xml->startElement('enclosure');
            $this->xml->writeAttribute(
                'url',
                "{$this->server['server']['url']}/getnzb?id={$this->release->guid}.nzb".
                "&r={$this->parameters['token']}".
                ((int) $this->parameters['del'] === 1 ? '&del=1' : '')
            );
            $this->xml->writeAttribute('length', $this->release->size);
            $this->xml->writeAttribute('type', 'application/x-nzb');
            $this->xml->endElement();
        }
    }

    /**
     * Writes the Zed (newznab) specific attributes.
     */
    protected function setZedAttributes(): void
    {
        $this->writeZedAttr('category', $this->release->categories_id);
        $this->writeZedAttr('size', $this->release->size);
        if (! empty($this->release->coverurl)) {
            $this->writeZedAttr(
                'coverurl',
                $this->server['server']['url']."/covers/{$this->release->coverurl}"
            );
        }

        if ((int) $this->parameters['extended'] === 1) {
            $this->writeZedAttr('files', $this->release->totalpart);
            $this->writeZedAttr('poster', $this->release->fromname);
            if (($this->release->videos_id > 0 || $this->release->tv_episodes_id > 0) && $this->namespace === 'newznab') {
                $this->setTvAttr();
            }

            if (isset($this->release->imdbid) && $this->release->imdbid > 0) {
                $this->writeZedAttr('imdb', $this->release->imdbid);
            }
            if (isset($this->release->anidbid) && $this->release->anidbid > 0) {
                $this->writeZedAttr('anidbid', $this->release->anidbid);
            }
            if (isset($this->release->predb_id) && $this->release->predb_id > 0) {
                $this->writeZedAttr('prematch', 1);
            }
            if (isset($this->release->nfostatus) && (int) $this->release->nfostatus === 1) {
                $this->writeZedAttr(
                    'info',
                    $this->server['server']['url'].
                    "api?t=info&id={$this->release->guid}&r={$this->parameters['token']}"
                );
            }

            $this->writeZedAttr('grabs', $this->release->grabs);
            $this->writeZedAttr('comments', $this->release->comments);
            $this->writeZedAttr('password', $this->release->passwordstatus);
            $this->writeZedAttr('usenetdate', Carbon::parse($this->release->postdate)->toRssString());
            if (! empty($this->release->group_name)) {
                $this->writeZedAttr('group', $this->release->group_name);
            }
        }
    }

    /**
     * Writes the TV Specific attributes.
     */
    protected function setTvAttr(): void
    {
        if (! empty($this->release->title)) {
            $this->writeZedAttr('title', $this->release->title);
        }
        if (isset($this->release->series) && $this->release->series > 0) {
            $this->writeZedAttr('season', $this->release->series);
        }
        if (isset($this->release->episode->episode) && $this->release->episode->episode > 0) {
            $this->writeZedAttr('episode', $this->release->episode->episode);
        }
        if (! empty($this->release->firstaired)) {
            $this->writeZedAttr('tvairdate', $this->release->firstaired);
        }
        if (isset($this->release->tvdb) && $this->release->tvdb > 0) {
            $this->writeZedAttr('tvdbid', $this->release->tvdb);
        }
        if (isset($this->release->trakt) && $this->release->trakt > 0) {
            $this->writeZedAttr('traktid', $this->release->trakt);
        }
        if (isset($this->release->tvrage) && $this->release->tvrage > 0) {
            $this->writeZedAttr('tvrageid', $this->release->tvrage);
            $this->writeZedAttr('rageid', $this->release->tvrage);
        }
        if (isset($this->release->tvmaze) && $this->release->tvmaze > 0) {
            $this->writeZedAttr('tvmazeid', $this->release->tvmaze);
        }
        if (isset($this->release->imdb) && $this->release->imdb > 0) {
            $this->writeZedAttr('imdbid', $this->release->imdb);
        }
        if (isset($this->release->tmdb) && $this->release->tmdb > 0) {
            $this->writeZedAttr('tmdbid', $this->release->tmdb);
        }
    }

    /**
     * Writes individual zed (newznab) type attributes.
     *
     * @param  string  $name  The namespaced attribute name tag
     * @param  string  $value  The namespaced attribute value
     */
    protected function writeZedAttr(string $name, string $value): void
    {
        $this->xml->startElement($this->namespace.':attr');
        $this->xml->writeAttribute('name', $name);
        $this->xml->writeAttribute('value', $value);
        $this->xml->endElement();
    }

    /**
     * Writes the cData (HTML format) for the RSS feed
     * Also calls supplementary cData writes depending upon post process.
     */
    protected function writeRssCdata(): void
    {
        $this->cdata = "\n\t<div>\n";
        switch (1) {
            case ! empty($this->release->cover):
                $dir = 'movies';
                $column = 'imdbid';
                break;
            case ! empty($this->release->mu_cover):
                $dir = 'music';
                $column = 'musicinfo_id';
                break;
            case ! empty($this->release->co_cover):
                $dir = 'console';
                $column = 'consoleinfo_id';
                break;
            case ! empty($this->release->bo_cover):
                $dir = 'books';
                $column = 'bookinfo_id';
                break;
        }
        if (isset($dir, $column)) {
            $dcov = ($dir === 'movies' ? '-cover' : '');
            $this->cdata .=
                "\t<img style=\"margin-left:10px;margin-bottom:10px;float:right;\" ".
                "src=\"{$this->server['server']['url']}/covers/{$dir}/{$this->release->$column}{$dcov}.jpg\" ".
                "width=\"120\" alt=\"{$this->release->searchname}\" />\n";
        }
        $size = human_filesize($this->release->size);
        $this->cdata .=
            "\t<li>ID: <a href=\"{$this->server['server']['url']}/details/{$this->release->guid}\">{$this->release->guid}</a></li>\n".
            "\t<li>Name: {$this->release->searchname}</li>\n".
            "\t<li>Size: {$size}</li>\n".
            "\t<li>Category: <a href=\"{$this->server['server']['url']}/browse/{$this->release->category_name}\">{$this->release->category_name}</a></li>\n".
            "\t<li>Group: <a href=\"{$this->server['server']['url']}/browse/group?g={$this->release->group_name}\">{$this->release->group_name}</a></li>\n".
            "\t<li>Poster: {$this->release->fromname}</li>\n".
            "\t<li>Posted: {$this->release->postdate}</li>\n";

        $pstatus = match ($this->release->passwordstatus) {
            0 => 'None',
            1 => 'Possibly Passworded',
            2 => 'Probably not viable',
            10 => 'Passworded',
            default => 'Unknown',
        };
        $this->cdata .= "\t<li>Password: {$pstatus}</li>\n";
        if ($this->release->nfostatus === 1) {
            $this->cdata .=
                "\t<li>Nfo: ".
                "<a href=\"{$this->server['server']['url']}/api?t=nfo&id={$this->release->guid}&raw=1&i={$this->parameters['uid']}&r={$this->parameters['token']}\">".
                "{$this->release->searchname}.nfo</a></li>\n";
        }

        if ($this->release->parentid === Category::MOVIE_ROOT && $this->release->imdbid !== '') {
            $this->writeRssMovieInfo();
        } elseif ($this->release->parentid === Category::MUSIC_ROOT && $this->release->musicinfo_id > 0) {
            $this->writeRssMusicInfo();
        } elseif ($this->release->parentid === Category::GAME_ROOT && $this->release->consoleinfo_id > 0) {
            $this->writeRssConsoleInfo();
        }
        $this->xml->startElement('description');
        $this->xml->writeCdata($this->cdata."\t</div>");
        $this->xml->endElement();
    }

    /**
     * Writes the Movie Info for the RSS feed cData.
     */
    protected function writeRssMovieInfo(): void
    {
        $movieCol = ['rating', 'plot', 'year', 'genre', 'director', 'actors'];

        $cData = $this->buildCdata($movieCol);

        $this->cdata .=
            "\t<li>Imdb Info:
				\t<ul>
					\t<li>IMDB Link: <a href=\"http://www.imdb.com/title/tt{$this->release->imdbid}/\">{$this->release->searchname}</a></li>\n
					\t{$cData}
				\t</ul>
			\t</li>
			\n";
    }

    /**
     * Writes the Music Info for the RSS feed cData.
     */
    protected function writeRssMusicInfo(): void
    {
        $tData = $cDataUrl = '';

        $musicCol = ['mu_artist', 'mu_genre', 'mu_publisher', 'mu_releasedate', 'mu_review'];

        $cData = $this->buildCdata($musicCol);

        if ($this->release->mu_url !== '') {
            $cDataUrl = "<li>Amazon: <a href=\"{$this->release->mu_url}\">{$this->release->mu_title}</a></li>";
        }

        $this->cdata .=
            "\t<li>Music Info:
			<ul>
			{$cDataUrl}
			{$cData}
			</ul>
			</li>\n";
        if ($this->release->mu_tracks !== '') {
            $tracks = explode('|', $this->release->mu_tracks);
            if (\count($tracks) > 0) {
                foreach ($tracks as $track) {
                    $track = trim($track);
                    $tData .= "<li>{$track}</li>";
                }
            }
            $this->cdata .= "
			<li>Track Listing:
				<ol>
				{$tData}
				</ol>
			</li>\n";
        }
    }

    /**
     * Writes the Console Info for the RSS feed cData.
     */
    protected function writeRssConsoleInfo(): void
    {
        $gamesCol = ['co_genre', 'co_publisher', 'year', 'co_review'];

        $cData = $this->buildCdata($gamesCol);

        $this->cdata .= "
		<li>Console Info:
			<ul>
				<li>Amazon: <a href=\"{$this->release->co_url}\">{$this->release->co_title}</a></li>\n
				{$cData}
			</ul>
		</li>\n";
    }

    /**
     * Accepts an array of values to loop through to build cData from the release info.
     *
     * @param  array  $columns  The columns in the release we need to insert
     * @return string The HTML format cData
     */
    protected function buildCdata(array $columns): string
    {
        $cData = '';

        foreach ($columns as $info) {
            if (! empty($this->release->$info)) {
                if ($info === 'mu_releasedate') {
                    $ucInfo = 'Released';
                    $rDate = date('Y-m-d', strtotime($this->release->$info));
                    $cData .= "<li>{$ucInfo}: {$rDate}</li>\n";
                } else {
                    $ucInfo = ucfirst(preg_replace('/^[a-z]{2}_/i', '', $info));
                    $cData .= "<li>{$ucInfo}: {$this->release->$info}</li>\n";
                }
            }
        }

        return $cData;
    }
}
