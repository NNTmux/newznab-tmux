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

namespace Blacklight\http;

use App\Models\Category;
use App\Models\Settings;
use Blacklight\utility\Utility;

/**
 * Class Output -- abstract class for printing web requests outside of Smarty.
 */
abstract class Capabilities
{
    /**
     * @var \PDO
     */
    public $pdo;

    /**
     * @var string The type of Capabilities request
     */
    protected $type;

    /**
     * Construct.
     *
     * @param  array  $options  Class instances.
     *
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'Settings' => null,
        ];
        $options += $defaults;
    }

    /**
     * Print XML or JSON output.
     *
     * @param  array|\Illuminate\Database\Eloquent\Collection  $data  Data to print.
     * @param  array  $params  Additional request parameters
     * @param  bool  $xml  True: Print as XML False: Print as JSON.
     * @param  int  $offset  How much releases to skip
     * @param  string  $type  What type of API query to format if XML
     *
     * @throws \Exception
     */
    public function output($data, $params, $xml = true, $offset, $type = ''): void
    {
        $this->type = $type;

        $options = [
            'Parameters' => $params,
            'Data'       => $data,
            'Server'     => $this->getForMenu(),
            'Offset'     => $offset,
            'Type'       => $type,
        ];

        // Generate the XML Response
        $response = (new XML_Response($options))->returnXML();

        if ($xml) {
            header('Content-type: text/xml');
        } else {
            // JSON encode the XMLWriter response
            $response = json_encode(
            // Convert SimpleXMLElement response from XMLWriter
            //into array with namespace preservation
                Utility::xmlToArray(
                // Load the XMLWriter response
                    @simplexml_load_string($response),
                    [
                        'attributePrefix' => '_',
                        'textContent'     => 'text',
                    ]
                )
                // Strip the RSS+XML info from the JSON response by selecting enclosed data only
                ['rss']['channel'],
                JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES
            );
            header('Content-type: application/json');
        }
        if ($response === false) {
            Utility::showApiError(201);
        } else {
            header('Content-Length: '.\strlen($response));
            echo $response;
            exit;
        }
    }

    /**
     * Collect and return various capability information for usage in API.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getForMenu(): array
    {
        $serverroot = url('/');

        return [
            'server' => [
                'title'      => config('app.name'),
                'strapline'  => Settings::settingValue('site.main.strapline'),
                'email'      => config('mail.from.address'),
                'meta'       => Settings::settingValue('site.main.metakeywords'),
                'url'        => $serverroot,
                'image'      => $serverroot.'/assets/images/tmux_logo.png',
            ],
            'limits' => [
                'max'     => 100,
                'default' => 100,
            ],
            'registration' => [
                'available' => 'yes',
                'open'      => (int) Settings::settingValue('..registerstatus') === 0 ? 'yes' : 'no',
            ],
            'searching' => [
                'search'       => ['available' => 'yes', 'supportedParams' => 'q'],
                'tv-search'    => ['available' => 'yes', 'supportedParams' => 'q,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep'],
                'movie-search' => ['available' => 'yes', 'supportedParams' => 'q,imdbid, tmdbid, traktid'],
                'audio-search' => ['available' => 'no',  'supportedParams' => ''],
            ],
            'categories' => $this->type === 'caps'
                    ? Category::getForMenu()
                    : null,
        ];
    }
}
