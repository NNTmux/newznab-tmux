<?php

declare(strict_types=1);

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
use App\Services\Api\V1\ReleaseItemPayloadBuilder;
use App\Services\Api\V1\ReleaseRows;
use App\Services\Api\V1\XmlResponseContext;

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
    protected string $namespace = 'newznab';

    protected ReleaseRows $releaseRows;

    protected ?ReleaseItemPayloadBuilder $releasePayloadBuilder = null;

    protected ?string $releasePayloadBuilderNamespace = null;

    /**
     * The trailing URL parameters on the request.
     *
     * @var array<string, mixed>
     */
    protected array $parameters;

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
     *
     * @var array<string, mixed>
     */
    protected array $server;

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
     *
     * @param  array<string, mixed>  $options
     */
    public function __construct(array $options = [])
    {
        $context = XmlResponseContext::fromLegacyOptions($options);
        $this->parameters = $context->parameters;
        $this->releases = $context->data;
        $this->server = $context->server;
        $this->offset = $context->offset;
        $this->type = $context->type;
        $this->releaseRows = new ReleaseRows($this->releases);

        $this->xml = new \XMLWriter;
        $this->xml->openMemory();
        // Disable indentation for API responses (smaller payload, faster generation).
        // Clients (Sonarr, Radarr, etc.) don't need pretty-printed XML.
        $this->xml->setIndent(false);
    }

    protected function writeXmlElement(string $name, mixed $content): void
    {
        $this->xml->writeElement($name, $this->xmlString($content));
    }

    protected function writeXmlAttribute(string $name, mixed $value): void
    {
        $this->xml->writeAttribute($name, $this->xmlString($value));
    }

    protected function writeXmlText(mixed $content): void
    {
        $this->xml->text($this->xmlString($content));
    }

    protected function writeXmlCdata(mixed $content): void
    {
        // A literal "]]>" would end the CDATA section early; split it across two sections instead.
        $this->xml->writeCdata(str_replace(']]>', ']]]]><![CDATA[>', $this->xmlString($content)));
    }

    /**
     * Escape a release or metadata value for the HTML inside an RSS description.
     */
    protected function escapeHtml(mixed $value): string
    {
        return htmlspecialchars($this->xmlString($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function xmlString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;
        if ($string === '') {
            return '';
        }

        // XML 1.0 allows tab, LF, CR, and the non-control Unicode ranges.
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $string) ?? '';
    }

    public function returnXML(): bool|string
    {
        switch ($this->type) {
            case 'caps':
                return $this->returnCaps();
            case 'api':
                $this->namespace = 'newznab';

                return $this->returnApiXml();
            case 'rss':
                $this->namespace = 'nntmux';

                return $this->returnApiRssXml();
            case 'reg':
                return $this->returnReg();
        }

        return false;
    }

    /**
     * Build the API response as a PHP array instead of XML.
     * Used for JSON output to avoid the expensive XML->xml_to_array->json_encode path.
     *
     * @return array<string, mixed>|false
     */
    public function returnArray(): array|false
    {
        return match ($this->type) {
            'caps' => $this->buildCapsArray(),
            'api' => $this->buildApiArray(),
            'reg' => $this->buildRegArray(),
            default => false,
        };
    }

    /**
     * Build capabilities response as array.
     *
     * @return array<string, mixed>
     */
    protected function buildCapsArray(): array
    {
        return [
            'server' => $this->server['server'],
            'limits' => $this->server['limits'],
            'registration' => $this->server['registration'],
            'searching' => $this->server['searching'],
            'categories' => $this->server['categories'] ?? [],
            'groups' => $this->server['groups'] ?? [],
            'genres' => $this->server['genres'] ?? [],
        ];
    }

    /**
     * Build API response as array.
     *
     * @return array<string, mixed>
     */
    protected function buildApiArray(): array
    {
        $response = [
            'offset' => $this->offset,
            'total' => $this->totalRows(),
        ];

        $response['apilimits'] = [
            'apicurrent' => $this->parameters['requests'],
            'apimax' => $this->parameters['apilimit'],
            'grabcurrent' => $this->parameters['grabs'],
            'grabmax' => $this->parameters['downloadlimit'],
        ];
        if (! empty($this->parameters['oldestapi'])) {
            $response['apilimits']['apioldesttime'] = $this->parameters['oldestapi'];
        }
        if (! empty($this->parameters['oldestgrab'])) {
            $response['apilimits']['graboldesttime'] = $this->parameters['oldestgrab'];
        }

        $response['item'] = [];
        foreach ($this->releaseRows() as $release) {
            $response['item'][] = $this->releasePayload($release);
        }

        return $response;
    }

    /**
     * @return list<mixed>
     */
    protected function releaseRows(): array
    {
        return $this->releaseRows->rows();
    }

    protected function totalRows(): int
    {
        return $this->releaseRows->totalRows();
    }

    /**
     * Build a single release as array.
     *
     * @return array<string, mixed>
     */
    protected function buildReleaseArray(): array
    {
        return $this->releasePayload($this->release);
    }

    /**
     * @return array{
     *     title: mixed,
     *     guid: string,
     *     link: string,
     *     comments: string,
     *     pubDate: string,
     *     category: mixed,
     *     description: mixed,
     *     enclosure?: array{url: string, length: mixed, type: string},
     *     attr: array<string, mixed>
     * }
     */
    protected function releasePayload(mixed $release): array
    {
        return $this->releasePayloadBuilder()->build($release);
    }

    protected function releasePayloadBuilder(): ReleaseItemPayloadBuilder
    {
        if ($this->releasePayloadBuilder === null || $this->releasePayloadBuilderNamespace !== $this->namespace) {
            $this->releasePayloadBuilder = new ReleaseItemPayloadBuilder($this->parameters, $this->server, $this->namespace);
            $this->releasePayloadBuilderNamespace = $this->namespace;
        }

        return $this->releasePayloadBuilder;
    }

    /**
     * Build registration response as array.
     *
     * @return array<string, mixed>
     */
    protected function buildRegArray(): array
    {
        return [
            'username' => $this->parameters['username'],
            'password' => $this->parameters['password'],
            'apikey' => $this->parameters['token'],
        ];
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
        $this->writeGroupListing();
        $this->writeGenreListing();
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
        $this->writeXmlAttribute('username', $this->parameters['username'] ?? '');
        $this->writeXmlAttribute('password', $this->parameters['password'] ?? '');
        $this->writeXmlAttribute('apikey', $this->parameters['token'] ?? '');
        $this->xml->endElement();
        $this->xml->endDocument();

        return $this->xml->outputMemory();
    }

    /**
     * Starts a new element, loops through the attribute data and ends the element.
     *
     * @param  array<string, mixed>  $element  An array with the name of the element and the attribute data
     */
    protected function addNode(array $element): void
    {
        $this->xml->startElement($element['name']);
        foreach ($element['data'] as $attr => $val) {
            $this->writeXmlAttribute((string) $attr, $val);
        }
        $this->xml->endElement();
    }

    /**
     * Starts a new element, loops through the attribute data and ends the element.
     *
     * @param  array<string, mixed>  $element  An array with the name of the element and the attribute data
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
        foreach (($this->server['categories'] ?? []) as $category) {
            $this->xml->startElement('category');
            $this->writeXmlAttribute('id', $category['id']);
            $this->writeXmlAttribute('name', html_entity_decode((string) $category['title']));
            if (! empty($category['description'])) {
                $this->writeXmlAttribute('description', html_entity_decode((string) $category['description']));
            }
            foreach (($category['categories'] ?? []) as $c) {
                $this->xml->startElement('subcat');
                $this->writeXmlAttribute('id', $c['id']);
                $this->writeXmlAttribute('name', html_entity_decode((string) $c['title']));
                if (! empty($c['description'])) {
                    $this->writeXmlAttribute('description', html_entity_decode((string) $c['description']));
                }
                $this->xml->endElement();
            }
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    protected function writeGroupListing(): void
    {
        $this->xml->startElement('groups');
        foreach (($this->server['groups'] ?? []) as $group) {
            $this->xml->startElement('group');
            $this->writeXmlAttribute('name', $group['name'] ?? '');
            $this->writeXmlAttribute('description', $group['description'] ?? '');
            if (! empty($group['lastupdate'])) {
                $this->writeXmlAttribute('lastupdate', $group['lastupdate']);
            }
            $this->xml->endElement();
        }
        $this->xml->endElement();
    }

    protected function writeGenreListing(): void
    {
        $this->xml->startElement('genres');
        foreach (($this->server['genres'] ?? []) as $genre) {
            $this->xml->startElement('genre');
            $this->writeXmlAttribute('id', $genre['id'] ?? '');
            $this->writeXmlAttribute('name', $genre['name'] ?? '');
            $this->writeXmlAttribute('categoryid', $genre['categoryid'] ?? '0');
            $this->xml->endElement();
        }
        $this->xml->endElement();
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
        $this->writeXmlAttribute('version', '2.0');
        $this->writeXmlAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $this->writeXmlAttribute("xmlns:{$this->namespace}", $url);
        $this->writeXmlAttribute('encoding', 'utf-8');
    }

    protected function includeRssAtomLink(): void
    {
        $this->xml->startElement('atom:link');
        $this->xml->startAttribute('href');
        $this->writeXmlText($this->server['server']['url'].($this->namespace === 'newznab' ? '/api/v1/api' : '/rss'));
        $this->xml->endAttribute();
        $this->xml->startAttribute('rel');
        $this->writeXmlText('self');
        $this->xml->endAttribute();
        $this->xml->startAttribute('type');
        $this->writeXmlText('application/rss+xml');
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

        $this->writeXmlElement('title', $server['title']);
        $this->writeXmlElement('description', $server['title']." {$tag} Details");
        $this->writeXmlElement('link', $server['url']);
        $this->writeXmlElement('language', 'en-gb');
        $this->writeXmlElement('webMaster', $server['email'].' '.$server['title']);
        $this->writeXmlElement('category', $server['meta']);
        $this->writeXmlElement('generator', 'nntmux');
        $this->writeXmlElement('ttl', '10');
        $this->writeXmlElement('docs', $this->server['server']['url'].$path);
    }

    /**
     * Adds nntmux logo data to the XML.
     */
    protected function includeImage(): void
    {
        $this->xml->startElement('image');
        $this->writeXmlAttribute('url', $this->server['server']['url'].'/assets/images/tmux_logo.png');
        $this->writeXmlAttribute('title', $this->server['server']['title']);
        $this->writeXmlAttribute('link', $this->server['server']['url']);
        $this->writeXmlAttribute(
            'description',
            'Visit '.$this->server['server']['title'].' - '.$this->server['server']['strapline']
        );
        $this->xml->endElement();
    }

    protected function includeTotalRows(): void
    {
        $this->xml->startElement($this->namespace.':response');
        $this->writeXmlAttribute('offset', $this->offset);
        $this->writeXmlAttribute('total', $this->totalRows());
        $this->xml->endElement();
    }

    protected function includeLimits(): void
    {
        $this->xml->startElement($this->namespace.':apilimits');
        $this->writeXmlAttribute('apicurrent', $this->parameters['requests']);
        $this->writeXmlAttribute('apimax', $this->parameters['apilimit']);
        $this->writeXmlAttribute('grabcurrent', $this->parameters['grabs']);
        $this->writeXmlAttribute('grabmax', $this->parameters['downloadlimit']);
        if (! empty($this->parameters['oldestapi'])) {
            $this->writeXmlAttribute('apioldesttime', $this->parameters['oldestapi']);
        }
        if (! empty($this->parameters['oldestgrab'])) {
            $this->writeXmlAttribute('graboldesttime', $this->parameters['oldestgrab']);
        }
        $this->xml->endElement();
    }

    /**
     * Loop through the releases and add their info to the XML stream.
     */
    protected function includeReleases(): void
    {
        foreach ($this->releaseRows() as $this->release) {
            $payload = $this->releasePayload($this->release);
            $this->xml->startElement('item');
            $this->includeReleaseMain($payload);
            $this->setZedAttributes($payload['attr']);
            $this->xml->endElement();
        }
    }

    /**
     * @param  array{
     *     title: mixed,
     *     guid: string,
     *     link: string,
     *     comments: string,
     *     pubDate: string,
     *     category: mixed,
     *     description: mixed,
     *     enclosure?: array{url: string, length: mixed, type: string},
     *     attr: array<string, mixed>
     * }  $payload
     */
    protected function includeReleaseMain(array $payload): void
    {
        $this->writeXmlElement('title', $payload['title']);
        $this->xml->startElement('guid');
        $this->writeXmlAttribute('isPermaLink', 'true');
        $this->writeXmlText($payload['guid']);
        $this->xml->endElement();
        $this->writeXmlElement('link', $payload['link']);
        $this->writeXmlElement('comments', $payload['comments']);
        $this->writeXmlElement('pubDate', $payload['pubDate']);
        $this->writeXmlElement('category', $payload['category']);
        if ($this->namespace === 'newznab') {
            $this->writeXmlElement('description', $payload['description']);
        } else {
            $this->writeRssCdata();
        }
        if (isset($payload['enclosure'])) {
            $this->xml->startElement('enclosure');
            $this->writeXmlAttribute('url', $payload['enclosure']['url']);
            $this->writeXmlAttribute('length', $payload['enclosure']['length']);
            $this->writeXmlAttribute('type', $payload['enclosure']['type']);
            $this->xml->endElement();
        }
    }

    /**
     * Writes the Zed (newznab) specific attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function setZedAttributes(array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            $this->writeZedAttr((string) $name, $value);
        }
    }

    /**
     * Writes individual zed (newznab) type attributes.
     *
     * @param  string  $name  The namespaced attribute name tag
     * @param  mixed  $value  The namespaced attribute value (int/string/null from DB)
     */
    protected function writeZedAttr(string $name, mixed $value): void
    {
        $this->xml->startElement($this->namespace.':attr');
        $this->writeXmlAttribute('name', $name);
        $this->writeXmlAttribute('value', $value);
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
                $dir = 'book';
                $column = 'bookinfo_id';
                break;
        }
        if (isset($dir, $column)) {
            $dcov = ($dir === 'movies' ? '-cover' : '');
            $filename = resolveImageAssetFilename($dir, $this->release->$column.$dcov) ?? 'no-cover.jpg';
            $this->cdata .=
                "\t<img style=\"margin-left:10px;margin-bottom:10px;float:right;\" ".
                "src=\"{$this->server['server']['url']}/covers/{$dir}/{$filename}\" ".
                "width=\"120\" alt=\"{$this->escapeHtml($this->release->searchname)}\" />\n";
        }
        $size = human_filesize($this->release->size);
        $this->cdata .=
            "\t<li>ID: <a href=\"{$this->server['server']['url']}/details/{$this->release->guid}\">{$this->release->guid}</a></li>\n".
            "\t<li>Name: {$this->escapeHtml($this->release->searchname)}</li>\n".
            "\t<li>Size: {$size}</li>\n".
            "\t<li>Category: <a href=\"{$this->server['server']['url']}/browse/{$this->escapeHtml(rawurlencode($this->xmlString($this->release->category_name)))}\">{$this->escapeHtml($this->release->category_name)}</a></li>\n".
            "\t<li>Group: <a href=\"{$this->server['server']['url']}/browse/group?g={$this->escapeHtml(rawurlencode($this->xmlString($this->release->group_name)))}\">{$this->escapeHtml($this->release->group_name)}</a></li>\n".
            "\t<li>Poster: {$this->escapeHtml($this->release->fromname)}</li>\n".
            "\t<li>Posted: {$this->escapeHtml($this->release->postdate)}</li>\n";

        $pstatus = match ($this->release->passwordstatus ?? 0) {
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
                "{$this->escapeHtml($this->release->searchname)}.nfo</a></li>\n";
        }

        if ($this->release->parentid === Category::MOVIE_ROOT && $this->release->imdbid !== '') {
            $this->writeRssMovieInfo();
        } elseif ($this->release->parentid === Category::MUSIC_ROOT && $this->release->musicinfo_id > 0) {
            $this->writeRssMusicInfo();
        } elseif ($this->release->parentid === Category::GAME_ROOT && $this->release->consoleinfo_id > 0) {
            $this->writeRssConsoleInfo();
        }
        $this->xml->startElement('description');
        $this->writeXmlCdata($this->cdata."\t</div>");
        $this->xml->endElement();
    }

    /**
     * Writes the Movie Info for the RSS feed cData.
     */
    protected function writeRssMovieInfo(): void
    {
        $movieCol = ['rating', 'plot', 'year', 'genre', 'director', 'actors'];

        $cData = $this->buildCdata($movieCol); // @phpstan-ignore argument.type

        $this->cdata .=
            "\t<li>Imdb Info:
				\t<ul>
					\t<li>IMDB Link: <a href=\"http://www.imdb.com/title/tt{$this->escapeHtml($this->release->imdbid)}/\">{$this->escapeHtml($this->release->searchname)}</a></li>\n
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

        $cData = $this->buildCdata($musicCol); // @phpstan-ignore argument.type

        if ($this->release->mu_url !== '') {
            $cDataUrl = "<li>Amazon: <a href=\"{$this->escapeHtml($this->release->mu_url)}\">{$this->escapeHtml($this->release->mu_title)}</a></li>";
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
                    $tData .= "<li>{$this->escapeHtml($track)}</li>";
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

        $cData = $this->buildCdata($gamesCol); // @phpstan-ignore argument.type

        $this->cdata .= "
		<li>Console Info:
			<ul>
				<li>Amazon: <a href=\"{$this->escapeHtml($this->release->co_url)}\">{$this->escapeHtml($this->release->co_title)}</a></li>\n
				{$cData}
			</ul>
		</li>\n";
    }

    /**
     * Accepts an array of values to loop through to build cData from the release info.
     *
     * @param  array<string, mixed>  $columns  The columns in the release we need to insert
     * @return string The HTML format cData
     */
    protected function buildCdata(array $columns): string
    {
        $cData = '';

        foreach ($columns as $info) {
            if (! empty($this->release->$info)) {
                if ($info === 'mu_releasedate') {
                    $ucInfo = 'Released';
                    $rDate = date('Y-m-d', strtotime((string) $this->release->$info));
                    $cData .= "<li>{$ucInfo}: {$rDate}</li>\n";
                } else {
                    $ucInfo = ucfirst(preg_replace('/^[a-z]{2}_/i', '', $info));
                    $cData .= "<li>{$ucInfo}: {$this->escapeHtml($this->release->$info)}</li>\n";
                }
            }
        }

        return $cData;
    }
}
