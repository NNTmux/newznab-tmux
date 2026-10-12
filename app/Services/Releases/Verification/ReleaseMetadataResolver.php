<?php

declare(strict_types=1);

namespace App\Services\Releases\Verification;

use App\Models\Category;
use App\Models\Predb;
use App\Models\UsenetGroup;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\PredbIdentityKey;
use App\Services\ReleaseCleaningService;
use App\Support\PredbSearchDocument;
use App\Support\Utf8;

class ReleaseMetadataResolver
{
    public function __construct(private readonly ReleaseCleaningService $cleaning, private readonly ?PayloadIdentityResolver $payloadIdentity = null, private readonly ?CategorizationService $categorization = null) {}

    /** @param array<string, mixed> $manifest
     * @return array{name: string, searchname: string, fromname: string, predb_id: int, isrenamed: int, categories_id: int, naming_regex_id: int}
     */
    public function resolve(array $manifest): array
    {
        $subject = (string) $manifest['subject'];
        $poster = (string) $manifest['fromname'];
        $collectionMetadata = ($manifest['metadata_source'] ?? 'collection') === 'collection';
        $importMetadata = ($manifest['metadata_source'] ?? null) === 'import';
        $displaySubject = $importMetadata && is_string($manifest['original_subject'] ?? null) ? $manifest['original_subject'] : $subject;
        $rawName = Utf8::clean($collectionMetadata ? str_replace(['#', '@', '$', '%', '^', '§', '¨', '©', 'Ö'], '', $subject) : $displaySubject);
        $from = Utf8::clean($collectionMetadata ? trim($poster, "'") : $poster);
        $groupId = (int) ($manifest['groups_id'] ?? 0);
        $group = $groupId > 0 ? (string) UsenetGroup::query()->whereKey($groupId)->value('name') : (string) ($manifest['groups'][0] ?? '');
        $importNameOverride = $importMetadata
            && ($manifest['import_name_override'] ?? false) === true && $subject !== '';
        $cleaned = $importNameOverride ? null : $this->cleaning->releaseCleaner($collectionMetadata ? $subject : $rawName, $collectionMetadata ? $poster : $from, $group);
        $name = $importNameOverride ? Utf8::clean($subject) : (is_array($cleaned) ? (string) ($cleaned['cleansubject'] ?? $rawName) : $rawName);
        $proper = is_array($cleaned) ? (bool) ($cleaned['properlynamed'] ?? false) : true;
        $predb = is_array($cleaned) ? max(0, (int) ($cleaned['predb'] ?? 0)) : 0;
        if ($predb === 0 && $name !== '') {
            $match = Predb::matchPre($name);
            if ($match !== false) {
                $name = $match['title'];
                $predb = (int) $match['predb_id'];
                $proper = true;
            }
        }

        if (! $proper && $predb === 0) {
            $candidate = ($this->payloadIdentity ?? new PayloadIdentityResolver($this->cleaning))->resolve($manifest);
            if ($candidate !== null) {
                $existing = $this->cleaning->releaseNameFromFile($name);
                if ($existing === null || PredbIdentityKey::normalize($existing) === PredbIdentityKey::normalize($candidate)) {
                    $name = $candidate;
                    $proper = true;
                    $match = Predb::matchPre($name);
                    if ($match !== false && PredbSearchDocument::exactValue($match['title']) === PredbSearchDocument::exactValue($name)) {
                        $name = $match['title'];
                        $predb = (int) $match['predb_id'];
                    }
                }
            }
        }

        $explicitCategory = $importMetadata
            ? filter_var($manifest['categories_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $categoryId = $explicitCategory !== false && Category::query()->whereKey($explicitCategory)->where('status', Category::STATUS_ACTIVE)->exists()
            ? $explicitCategory : null;
        if ($categoryId === null) {
            $category = ($this->categorization ?? new CategorizationService)->determineCategory($groupId, $name, $from);
            $categoryId = $category['categories_id'] ?? Category::OTHER_MISC;
        }

        $searchName = ($importMetadata ? $name !== '' : ! empty($name)) ? Utf8::clean($name) : $rawName;

        return ['name' => $rawName, 'searchname' => $searchName, 'fromname' => $from,
            'predb_id' => $predb, 'isrenamed' => $proper ? 1 : 0,
            'categories_id' => $categoryId,
            'naming_regex_id' => is_array($cleaned) ? (int) ($cleaned['id'] ?? 0) : 0];
    }
}
