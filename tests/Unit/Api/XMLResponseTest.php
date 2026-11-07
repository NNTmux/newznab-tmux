<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Http\Controllers\Api\XML_Response;
use App\Models\Category;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class XMLResponseTest extends TestCase
{
    public function test_api_xml_removes_invalid_xml_control_characters_from_release_fields(): void
    {
        $release = $this->release(['searchname' => "Clean\x1fTitle"]);
        $response = $this->response([$release]);

        $xml = $response->returnXML();

        self::assertIsString($xml);
        self::assertStringNotContainsString("\x1f", $xml);
        self::assertStringContainsString('CleanTitle', $xml);

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertNotFalse($parsed);
    }

    public function test_api_xml_handles_single_stdclass_release_payload(): void
    {
        $response = $this->response($this->release([
            'searchname' => 'Single Release',
            '_totalrows' => null,
        ]));

        $xml = $response->returnXML();

        self::assertIsString($xml);
        self::assertStringContainsString('total="1"', $xml);
        self::assertStringContainsString('Single Release', $xml);
    }

    public function test_api_array_handles_single_stdclass_release_payload(): void
    {
        $array = $this->response($this->release([
            'searchname' => 'Single Release',
            '_totalrows' => null,
        ]))->returnArray();

        self::assertIsArray($array);
        self::assertSame(1, $array['total']);
        self::assertCount(1, $array['item']);
        self::assertSame('Single Release', $array['item'][0]['title']);
    }

    public function test_api_xml_and_array_use_same_extended_release_payload(): void
    {
        $release = $this->release([
            'totalpart' => 42,
            'videos_id' => 99,
            'title' => 'Episode Title',
            'series' => 3,
            'episode' => (object) ['episode' => 7],
            'firstaired' => '2026-05-20',
            'tvdb' => 111,
            'trakt' => 222,
            'tvrage' => 333,
            'tvmaze' => 444,
            'tmdb' => 555,
            'imdb' => '1234567',
            'imdbid' => '7654321',
            'anidbid' => 123,
            'predb_id' => 456,
            'nfostatus' => 1,
            'grabs' => 5,
            'comments' => 6,
            'passwordstatus' => 0,
            'postdate' => '2026-05-28 08:30:00',
            'group_name' => 'alt.binaries.testing',
        ]);

        $array = $this->response([$release], ['extended' => '1'])->returnArray();
        $xml = $this->response([$release], ['extended' => '1'])->returnXML();

        self::assertIsArray($array);
        self::assertSame('Episode Title', $array['item'][0]['attr']['title']);
        self::assertSame(42, $array['item'][0]['attr']['files']);
        self::assertSame(7, $array['item'][0]['attr']['episode']);
        self::assertSame('alt.binaries.testing', $array['item'][0]['attr']['group']);

        self::assertIsString($xml);
        self::assertStringContainsString('<newznab:attr name="files" value="42"/>', $xml);
        self::assertStringContainsString('<newznab:attr name="title" value="Episode Title"/>', $xml);
        self::assertStringContainsString('<newznab:attr name="episode" value="7"/>', $xml);
        self::assertStringContainsString('<newznab:attr name="group" value="alt.binaries.testing"/>', $xml);
    }

    public function test_extended_api_falls_back_to_adddate_for_invalid_postdate(): void
    {
        $release = $this->release([
            'postdate' => 'not-a-date',
        ]);

        $array = $this->response([$release], ['extended' => '1'])->returnArray();

        self::assertIsArray($array);
        self::assertSame(
            date(DATE_RSS, strtotime('2026-05-29 12:00:00')),
            $array['item'][0]['attr']['usenetdate']
        );
    }

    public function test_api_rows_are_normalized_from_supported_payload_shapes(): void
    {
        $arrayRow = (array) $this->release([
            'searchname' => 'Array Release',
            '_totalrows' => null,
        ]);
        $collection = new Collection([$this->release(['searchname' => 'Collection Release', '_totalrows' => null])]);

        $arrayResponse = $this->response([$arrayRow])->returnArray();
        $collectionResponse = $this->response($collection)->returnArray();
        $emptyResponse = $this->response([])->returnArray();

        self::assertIsArray($arrayResponse);
        self::assertSame('Array Release', $arrayResponse['item'][0]['title']);

        self::assertIsArray($collectionResponse);
        self::assertSame('Collection Release', $collectionResponse['item'][0]['title']);

        self::assertIsArray($emptyResponse);
        self::assertSame(0, $emptyResponse['total']);
        self::assertSame([], $emptyResponse['item']);
    }

    public function test_api_rows_memoize_traversables_for_total_and_items(): void
    {
        $generator = (static function (): \Generator {
            yield (object) [
                'searchname' => 'First Generator Release',
                'guid' => 'first-generator-guid',
                'adddate' => '2026-05-29 12:00:00',
                'category_name' => 'Movies > HD',
                'categories_id' => 2040,
                'size' => 111,
                '_totalrows' => 7,
            ];
            yield (object) [
                'searchname' => 'Second Generator Release',
                'guid' => 'second-generator-guid',
                'adddate' => '2026-05-29 13:00:00',
                'category_name' => 'Movies > HD',
                'categories_id' => 2040,
                'size' => 222,
                '_totalrows' => 7,
            ];
        })();

        $array = $this->response($generator)->returnArray();

        self::assertIsArray($array);
        self::assertSame(7, $array['total']);
        self::assertCount(2, $array['item']);
        self::assertSame('Second Generator Release', $array['item'][1]['title']);
    }

    public function test_caps_xml_and_array_include_categories_groups_and_genres(): void
    {
        $array = $this->capsResponse()->returnArray();
        $xml = $this->capsResponse()->returnXML();

        self::assertIsArray($array);
        self::assertSame('NNTmux Tests', $array['server']['title']);
        self::assertSame('Movies', $array['categories'][0]['title']);
        self::assertSame('alt.binaries.testing', $array['groups'][0]['name']);
        self::assertSame('Action', $array['genres'][0]['name']);

        self::assertIsString($xml);
        self::assertStringContainsString('<category id="2000" name="Movies"', $xml);
        self::assertStringContainsString('<subcat id="2040" name="HD"', $xml);
        self::assertStringContainsString('<group name="alt.binaries.testing"', $xml);
        self::assertStringContainsString('<genre id="1" name="Action" categoryid="2000"/>', $xml);
    }

    public function test_registration_xml_and_array_match_legacy_shape(): void
    {
        $array = $this->registrationResponse()->returnArray();
        $xml = $this->registrationResponse()->returnXML();

        self::assertSame([
            'username' => 'tester',
            'password' => 'secret',
            'apikey' => 'test-token',
        ], $array);

        self::assertIsString($xml);
        self::assertStringContainsString('<register username="tester" password="secret" apikey="test-token"/>', $xml);
    }

    public function test_rss_xml_removes_invalid_control_characters_from_cdata(): void
    {
        $release = $this->release([
            'searchname' => "RSS\x1fRelease",
            'group_name' => 'alt.binaries.testing',
            'fromname' => 'poster',
            'postdate' => '2026-05-28 08:30:00',
            'passwordstatus' => 0,
            'nfostatus' => 0,
            'parentid' => Category::MOVIE_ROOT,
            'imdbid' => '',
            'musicinfo_id' => 0,
            'consoleinfo_id' => 0,
        ]);

        $xml = $this->response([$release], ['uid' => 1], 'rss')->returnXML();

        self::assertIsString($xml);
        self::assertStringNotContainsString("\x1f", $xml);
        self::assertStringContainsString('RSSRelease', $xml);

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        self::assertNotFalse($parsed);
    }

    public function test_rss_description_escapes_usenet_fields_and_cannot_break_out_of_cdata(): void
    {
        $release = $this->release([
            'searchname' => '<img src=x onerror=alert(1)>',
            'group_name' => 'alt.binaries.testing',
            'fromname' => 'poster]]></description><enclosure url="http://evil.test/x.nzb"/><description><![CDATA[',
            'postdate' => '2026-05-28 08:30:00',
            'passwordstatus' => 0,
            'nfostatus' => 0,
            'parentid' => Category::MOVIE_ROOT,
            'imdbid' => '',
            'musicinfo_id' => 0,
            'consoleinfo_id' => 0,
        ]);

        $xml = $this->response([$release], ['uid' => 1], 'rss')->returnXML();

        self::assertIsString($xml);
        $parsed = simplexml_load_string($xml);
        self::assertNotFalse($parsed);

        $item = $parsed->channel->item[0];
        self::assertCount(1, $item->enclosure);
        self::assertStringNotContainsString('evil.test', (string) $item->enclosure['url']);

        $description = (string) $item->description;
        self::assertStringNotContainsString('<img', $description);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $description);
        self::assertStringContainsString('&lt;/description&gt;&lt;enclosure', $description);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function release(array $overrides = []): object
    {
        return (object) array_merge([
            'searchname' => 'Clean Title',
            'guid' => 'release-guid',
            'adddate' => '2026-05-29 12:00:00',
            'category_name' => 'Movies > HD',
            'categories_id' => 2040,
            'size' => 123456789,
            '_totalrows' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $parameterOverrides
     */
    private function response(mixed $data, array $parameterOverrides = [], string $type = 'api'): XML_Response
    {
        return new XML_Response([
            'Parameters' => array_merge([
                'extended' => '0',
                'del' => '0',
                'token' => 'test-token',
                'requests' => 1,
                'apilimit' => 100,
                'grabs' => 0,
                'downloadlimit' => 100,
                'oldestapi' => '',
                'oldestgrab' => '',
            ], $parameterOverrides),
            'Data' => $data,
            'Server' => $this->server(),
            'Offset' => 0,
            'Type' => $type,
        ]);
    }

    private function capsResponse(): XML_Response
    {
        return new XML_Response([
            'Parameters' => [],
            'Data' => null,
            'Server' => array_merge($this->server(), [
                'limits' => [
                    'max' => 100,
                    'default' => 100,
                ],
                'registration' => [
                    'available' => 'yes',
                    'open' => 'yes',
                ],
                'searching' => [
                    'search' => ['available' => 'yes', 'supportedParams' => 'q'],
                ],
                'categories' => [
                    [
                        'id' => 2000,
                        'title' => 'Movies',
                        'description' => 'Movie releases',
                        'categories' => [
                            ['id' => 2040, 'title' => 'HD', 'description' => 'HD Movies'],
                        ],
                    ],
                ],
                'groups' => [
                    [
                        'name' => 'alt.binaries.testing',
                        'description' => 'Testing',
                        'lastupdate' => 'Fri, 29 May 2026 12:00:00 +0000',
                    ],
                ],
                'genres' => [
                    ['id' => 1, 'name' => 'Action', 'categoryid' => 2000],
                ],
            ]),
            'Offset' => 0,
            'Type' => 'caps',
        ]);
    }

    private function registrationResponse(): XML_Response
    {
        return new XML_Response([
            'Parameters' => [
                'username' => 'tester',
                'password' => 'secret',
                'token' => 'test-token',
            ],
            'Data' => null,
            'Server' => $this->server(),
            'Offset' => 0,
            'Type' => 'reg',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function server(): array
    {
        return [
            'server' => [
                'title' => 'NNTmux Tests',
                'strapline' => 'Testing',
                'email' => 'noreply@example.test',
                'meta' => 'usenet',
                'url' => 'https://indexer.example.test',
            ],
        ];
    }
}
