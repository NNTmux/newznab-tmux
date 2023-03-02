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
 * @author    DariusIII
 * @copyright 2016 newznab-tmux
 */

namespace Blacklight;

use GuzzleHttp\Client;

/**
 * Class CouchPotato.
 */
class CouchPotato
{
    /**
     * URL to the CP server.
     *
     * @var string
     */
    public $cpurl = '';

    /**
     * The CP key.
     *
     * @var string
     */
    public $cpapi = '';

    /**
     * Imdb ID.
     *
     * @var string
     */
    public $imdbid = '';

    /**
     * CouchPotato constructor.
     *
     * @param  \App\Http\Controllers\BasePageController  $page
     */
    public function __construct($page)
    {
        $this->cpurl = ! empty($page->userdata['cp_url']) ? $page->userdata['cp_url'] : '';
        $this->cpapi = ! empty($page->userdata['cp_api']) ? $page->userdata['cp_api'] : '';
    }

    /**
     * Send a movie to CouchPotato.
     *
     * @param  string  $id
     * @return bool|mixed
     *
     * @throws \RuntimeException
     */
    public function sendToCouchPotato($id)
    {
        $this->imdbid = $id;

        return (new Client(['verify' => false]))->get(
            $this->cpurl.
                '/api/'.
                $this->cpapi.
                '/movie.add/?identifier=tt'.
                $this->imdbid

        )->getBody()->getContents();
    }
}
