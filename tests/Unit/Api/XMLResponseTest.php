<?php

declare(strict_types=1);

namespace Tests\Unit\Api;

use App\Http\Controllers\Api\XML_Response;
use PHPUnit\Framework\TestCase;

final class XMLResponseTest extends TestCase
{
    public function test_api_xml_removes_invalid_xml_control_characters_from_release_fields(): void
    {
        $release = (object) [
            'searchname' => "Clean\x1fTitle",
            'guid' => 'release-guid',
            'adddate' => '2026-05-29 12:00:00',
            'category_name' => 'Movies > HD',
            'categories_id' => 2040,
            'size' => 123456789,
            '_totalrows' => 1,
        ];

        $response = new XML_Response([
            'Parameters' => [
                'extended' => '0',
                'del' => '0',
                'token' => 'test-token',
                'requests' => 1,
                'apilimit' => 100,
                'grabs' => 0,
                'downloadlimit' => 100,
                'oldestapi' => '',
                'oldestgrab' => '',
            ],
            'Data' => [$release],
            'Server' => [
                'server' => [
                    'title' => 'NNTmux Tests',
                    'strapline' => 'Testing',
                    'email' => 'noreply@example.test',
                    'meta' => 'usenet',
                    'url' => 'https://indexer.example.test',
                ],
            ],
            'Offset' => 0,
            'Type' => 'api',
        ]);

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
}
