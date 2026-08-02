<?php

declare(strict_types=1);

namespace App\Services\Api\V2;

use App\Data\Api\DetailsData;
use App\Data\Api\ReleaseData;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class ApiV2Presenter
{
    // JSON_HEX_TAG would turn category separators such as ">" into "\u003E".
    private const JSON_ENCODING_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** @param array<string, mixed> $data */
    public function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, [], self::JSON_ENCODING_OPTIONS);
    }

    /**
     * @param  iterable<int, Release|\stdClass>  $rows
     * @param  array<string, mixed>  $usage
     * @param  array{next_cursor: string|null, has_more: bool}|null  $pagination
     */
    public function search(iterable $rows, User $user, array $usage, ?array $pagination = null): JsonResponse
    {
        $rows = is_array($rows) ? $rows : iterator_to_array($rows, false);
        $results = [];
        foreach ($rows as $row) {
            $results[] = ReleaseData::toArrayFromRelease($row, $user, url('/details').'/', url('/getnzb'));
        }

        $payload = array_merge(
            ['Total' => (int) ($rows[0]->_totalrows ?? 0)],
            $usage,
            ['results' => $results],
        );
        if ($pagination !== null) {
            $payload['pagination'] = $pagination;
        }

        return $this->json($payload);
    }

    public function details(Release|\stdClass $release, User $user): JsonResponse
    {
        return $this->json(DetailsData::toArrayFromRelease(
            $release,
            $user,
            url('/details').'/',
            url('/getnzb')
        ));
    }

    /** @return array<string, mixed> */
    public function usage(User $user, object $statistics): array
    {
        return [
            'apiCurrent' => (int) ($statistics->api_count ?? 0),
            'apiMax' => $user->role->apirequests,
            'grabCurrent' => (int) ($statistics->grab_count ?? 0),
            'grabMax' => $user->role->downloadrequests,
            'apiOldestTime' => $statistics->api_time ? Carbon::parse($statistics->api_time)->toRfc2822String() : '',
            'grabOldestTime' => $statistics->grab_time ? Carbon::parse($statistics->grab_time)->toRfc2822String() : '',
        ];
    }
}
