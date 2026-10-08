<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiErrorHelpersTest extends TestCase
{
    private const HOSTILE_TEXT = "No such function (<x a=\"1\">&'\r\nInjected: yes)";

    #[Test]
    public function xml_errors_escape_reflected_text_into_well_formed_xml(): void
    {
        $response = showApiError(202, self::HOSTILE_TEXT);

        $xml = simplexml_load_string((string) $response->getContent());

        $this->assertNotFalse($xml);
        $this->assertSame('No such function (<x a="1">&\' Injected: yes)', (string) $xml['description']);
    }

    #[Test]
    public function error_headers_never_contain_line_breaks(): void
    {
        $xmlHeader = (string) showApiError(202, self::HOSTILE_TEXT)->headers->get('X-NNTmux');
        $jsonResponse = apiJsonError(202, self::HOSTILE_TEXT);
        $jsonHeader = (string) $jsonResponse->headers->get('X-NNTmux');

        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $xmlHeader);
        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $jsonHeader);
        $this->assertStringNotContainsString("\n", (string) $jsonResponse->getData(true)['error']);
    }
}
