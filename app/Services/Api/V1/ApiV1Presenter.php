<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Http\Controllers\Api\XML_Response;
use App\Services\Api\ApiCapabilitiesService;
use Illuminate\Http\Response;

final readonly class ApiV1Presenter
{
    // JSON_HEX_TAG would turn category separators such as ">" into "\u003E".
    private const JSON_ENCODING_OPTIONS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(private ApiCapabilitiesService $capabilities) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, string>  $headers
     */
    public function output(
        mixed $data,
        array $parameters,
        bool $xml,
        int $offset,
        string $type = '',
        array $headers = []
    ): Response {
        $response = new XML_Response([
            'Parameters' => $parameters,
            'Data' => $data,
            'Server' => $this->capabilities->v1($type === 'caps'),
            'Offset' => $offset,
            'Type' => $type,
        ]);

        if ($xml) {
            $body = $response->returnXML();
            $contentType = 'text/xml';
        } else {
            $array = $response->returnArray();
            if ($array === false) {
                return showApiError(201);
            }
            $body = json_encode($array, self::JSON_ENCODING_OPTIONS);
            $contentType = 'application/json';
        }

        if ($body === false) {
            return showApiError(201);
        }

        return response($body, 200, array_merge([
            'Content-type' => $contentType,
            'Content-Length' => (string) strlen($body),
        ], $headers));
    }
}
