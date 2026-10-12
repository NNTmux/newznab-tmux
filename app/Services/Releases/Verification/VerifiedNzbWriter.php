<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Services\Nzb\NzbService;
use RuntimeException;
use XMLWriter;

final class VerifiedNzbWriter
{
    public function __construct(private readonly NzbService $nzb) {}

    /** @param array<string, mixed> $manifest */
    public function write(array $manifest, string $guid): string
    {
        $path = $this->nzb->getNzbPath($guid, createIfNotExist: true);
        $body = gzencode($this->document($manifest));
        if ($body === false) {
            throw new RuntimeException('Cannot compress verified NZB.');
        }
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Cannot write verified NZB.');
        }
        try {
            try {
                if (fwrite($handle, $body) !== strlen($body) || ! fflush($handle) || ! fsync($handle)) {
                    throw new RuntimeException('Cannot persist verified NZB.');
                }
            } finally {
                fclose($handle);
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Cannot finalize verified NZB.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return $path;
    }

    /** @param array<string, mixed> $manifest */
    public function matches(array $manifest, string $guid): bool
    {
        $contents = $this->nzb->readNzbContents($guid);

        return is_string($contents) && hash_equals($this->document($manifest), $contents);
    }

    /** @param array<string, mixed> $manifest */
    private function document(array $manifest): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('nzb');
        $xml->writeAttribute('xmlns', 'http://www.newzbin.com/DTD/2003/nzb');
        $inventory = max((int) ($manifest['expected_files'] ?? 0), (int) ($manifest['verified_file_count'] ?? 0),
            count(array_filter($manifest['files'], static fn (array $file): bool => $file['segments'] !== [])));
        if ($inventory > 0) {
            $xml->startElement('head');
            $xml->startElement('meta');
            $xml->writeAttribute('type', 'nntmux:verified-file-count');
            $xml->text((string) $inventory);
            $xml->endElement();
            $xml->endElement();
        }
        foreach ($manifest['files'] as $file) {
            if ($file['segments'] === []) {
                continue;
            }
            $xml->startElement('file');
            $xml->writeAttribute('poster', (string) $manifest['fromname']);
            $xml->writeAttribute('date', (string) strtotime($manifest['date']));
            $xml->writeAttribute('subject', $file['subject'].' (1/'.$file['totalparts'].')');
            $xml->startElement('groups');
            foreach ($manifest['groups'] as $group) {
                $xml->writeElement('group', $group);
            }
            $xml->endElement();
            $xml->startElement('segments');
            foreach ($file['segments'] as $segment) {
                $xml->startElement('segment');
                $xml->writeAttribute('number', (string) $segment['number']);
                $xml->writeAttribute('bytes', (string) $segment['bytes']);
                $xml->text(trim($segment['messageid'], '<>'));
                $xml->endElement();
            }
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
