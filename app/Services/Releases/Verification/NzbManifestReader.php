<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use RuntimeException;

final class NzbManifestReader
{
    /** @return array<string, mixed> */
    public function read(string $xml, object $release): array
    {
        if (strlen($xml) > 33554432) {
            throw new RuntimeException('NZB exceeds audit metadata budget.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOBLANKS);
            if ($document === false || $document->getName() !== 'nzb') {
                throw new RuntimeException('Invalid NZB.');
            }
            $files = [];
            $groups = [];
            $expected = 0;
            $conflict = false;
            foreach ($document->head->meta ?? [] as $metadata) {
                $value = (string) $metadata;
                if ((string) $metadata['type'] === 'nntmux:verified-file-count'
                    && preg_match('/^[1-9][0-9]{0,6}$/D', $value) === 1) {
                    $expected = max($expected, (int) $value);
                }
            }
            foreach ($document->file as $index => $file) {
                $subject = (string) $file['subject'];
                $expected = max($expected, self::declaredFileCount($subject));
                $parts = preg_match('/\(\d+\/(\d+)\)\s*$/', $subject, $count) ? (int) $count[1] : null;
                $segments = [];
                $observedParts = 0;
                $partNumbers = [];
                foreach ($file->segments->segment as $segment) {
                    $number = (int) $segment['number'];
                    $messageId = trim((string) $segment);
                    $segments[] = ['number' => $number, 'bytes' => (int) $segment['bytes'], 'messageid' => $messageId];
                    if ($number < 1 || isset($partNumbers[$number])) {
                        $conflict = true;
                    }
                    $partNumbers[$number] = true;
                    if ($number > 0 && $messageId !== '') {
                        $observedParts = max($observedParts, $number);
                    }
                }
                foreach ($file->groups->group as $group) {
                    $groups[(string) $group] = true;
                }
                $files[] = ['key' => hash('sha256', $subject), 'subject' => preg_replace('/\s*\(\d+\/\d+\)\s*$/', '', $subject),
                    'totalparts' => $parts ?? $observedParts, 'totalparts_inferred' => $parts === null, 'segments' => $segments];
            }
            if ($files === []) {
                throw new RuntimeException('NZB contains no files.');
            }

            $metadataSource = ($release->metadata_source ?? null) === 'import' ? 'import' : 'audit';
            $manifest = ['subject' => $release->searchname, 'fromname' => $release->fromname, 'date' => $release->postdate,
                'groups_id' => (int) $release->groups_id, 'regex_id' => 0, 'expected_files' => $expected,
                'groups' => array_keys($groups), 'files' => $files, 'metadata_source' => $metadataSource];
            if ($conflict) {
                $manifest['conflict'] = true;
            }
            if ($metadataSource === 'import' && is_int($release->categories_id ?? null) && $release->categories_id > 0) {
                $manifest['categories_id'] = $release->categories_id;
            }
            if ($metadataSource === 'import' && ($release->import_name_override ?? false) === true) {
                $manifest['import_name_override'] = true;
            }
            if ($metadataSource === 'import' && is_string($release->original_subject ?? null)) {
                $manifest['original_subject'] = $release->original_subject;
            }

            return $manifest;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function declaredFileCount(string $subject): int
    {
        $outsideNames = preg_replace('/"[^"\r\n]*"|\'[^\'\r\n]*\'/', '', $subject) ?? '';
        $outsideNames = preg_replace('/\s*\(\d+\/\d+\)\s*$/', '', $outsideNames) ?? '';
        preg_match_all('/(?<!\S)[\[(](\d+)\/(\d+)[\])](?=\s|$)/', $outsideNames, $counts, PREG_SET_ORDER);
        $expected = 0;
        foreach ($counts as $count) {
            $index = (int) $count[1];
            $total = (int) $count[2];
            if ($total > 0 && $index <= $total) {
                $expected = max($expected, $total);
            }
        }

        return $expected;
    }
}
