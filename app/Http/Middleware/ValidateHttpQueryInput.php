<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Api\ApiInputCanonicalizer;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict input contract for HTTP QUERY (RFC 10008) API requests.
 *
 * Validates the complete effective input (URL and JSON body together), then
 * rewrites both parameter bags into the canonical form a GET request would
 * produce, so controllers keep their legacy parsing. For v2, other methods get
 * the same URL value-type check; v1 keeps its legacy array parameters.
 *
 * Laravel decodes JSON bodies while capturing the request, so the size check
 * here is a contract limit; the resource limit belongs to the web server.
 */
final class ValidateHttpQueryInput
{
    private const MAX_PARAMETERS = 64;

    private const KEY_PATTERN = '/^[A-Za-z0-9_]{1,32}$/';

    public function __construct(private readonly ApiInputCanonicalizer $canonicalizer) {}

    /**
     * @param  Closure(Request): (Response)  $next
     * @param  string  $version  `v1` renders XML errors, `v2` renders JSON errors
     */
    public function handle(Request $request, Closure $next, string $version = 'v2'): Response
    {
        if (! $request->isMethod('QUERY')) {
            $error = $version === 'v2' ? $this->validateUrlValues($request->query->all()) : null;
            if ($error !== null) {
                return $this->errorResponse($version, $error['status'], $error['message']);
            }

            return $next($request);
        }

        $error = $this->validate($request);
        if ($error !== null) {
            return $this->errorResponse($version, $error['status'], $error['message']);
        }

        $body = $this->canonicalizer->canonicalize($request->json()->all());
        $request->query->replace($this->canonicalizer->canonicalize($request->query->all()));
        $request->json()->replace($body);
        $request->request->replace($body);

        return $next($request);
    }

    /**
     * @return array{status: int, message: string}|null
     */
    private function validate(Request $request): ?array
    {
        if (! $this->isJsonMediaType($request->headers->get('Content-Type'))) {
            return ['status' => 415, 'message' => 'QUERY requests must use Content-Type: application/json'];
        }

        $raw = $request->getContent();
        if (strlen($raw) > (int) config('nntmux.api.query_max_body_bytes', 8192)) {
            return ['status' => 413, 'message' => 'QUERY body exceeds the maximum size'];
        }

        // Decode with objects preserved: associative decoding would turn {"0":1} into a list.
        try {
            $decoded = json_decode($raw, false, 3, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['status' => 400, 'message' => 'QUERY body must be valid JSON without nested values'];
        }

        if (! $decoded instanceof stdClass) {
            return ['status' => 400, 'message' => 'QUERY body must be a JSON object'];
        }

        $body = get_object_vars($decoded);
        $query = $request->query->all();
        $keys = array_unique(array_map('strval', [...array_keys($query), ...array_keys($body)]));

        foreach ($keys as $key) {
            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                return ['status' => 400, 'message' => 'Parameter names may only contain letters, digits and underscores (max 32)'];
            }
        }

        if (count($keys) > self::MAX_PARAMETERS) {
            return ['status' => 400, 'message' => 'QUERY requests accept at most '.self::MAX_PARAMETERS.' parameters'];
        }

        // Keys are safe to name in messages from here on: they passed KEY_PATTERN.
        $duplicates = array_keys(array_intersect_key($query, $body));
        if ($duplicates !== []) {
            return ['status' => 400, 'message' => 'Parameter '.$duplicates[0].' must not be sent in both the URL and the body'];
        }

        foreach ($body as $key => $value) {
            if (! $this->isValidBodyValue((string) $key, $value)) {
                return ['status' => 400, 'message' => 'Parameter '.$key.' has an unsupported type'];
            }
        }

        return $this->validateUrlValues($query);
    }

    /**
     * Reject URL parameters sent as arrays (`?id[]=x`) unless they are list
     * parameters, so scalar filters never reach the controllers as arrays.
     *
     * @param  array<array-key, mixed>  $query
     * @return array{status: int, message: string}|null
     */
    private function validateUrlValues(array $query): ?array
    {
        foreach ($query as $key => $value) {
            if (! $this->isValidUrlValue((string) $key, $value)) {
                $name = preg_match(self::KEY_PATTERN, (string) $key) === 1 ? (string) $key : 'value';

                return ['status' => 400, 'message' => 'Parameter '.$name.' has an unsupported type'];
            }
        }

        return null;
    }

    private function isJsonMediaType(?string $contentType): bool
    {
        if ($contentType === null) {
            return false;
        }

        $parts = array_map('trim', explode(';', $contentType));
        if (strtolower((string) array_shift($parts)) !== 'application/json') {
            return false;
        }

        foreach ($parts as $parameter) {
            if ($parameter === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            if (strtolower(trim($name)) !== 'charset' || strtolower(trim(trim($value), '"')) !== 'utf-8') {
                return false;
            }
        }

        return true;
    }

    private function isValidBodyValue(string $key, mixed $value): bool
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return true;
        }

        // JSON arrays decode to PHP lists; JSON objects stay stdClass and are rejected.
        return ApiInputCanonicalizer::isListParameter($key)
            && is_array($value)
            && array_is_list($value)
            && array_all($value, static fn (mixed $item): bool => is_string($item) || is_int($item) || is_float($item));
    }

    private function isValidUrlValue(string $key, mixed $value): bool
    {
        // null is an empty parameter such as `?id=` after ConvertEmptyStringsToNull.
        if ($value === null || is_string($value)) {
            return true;
        }

        return ApiInputCanonicalizer::isListParameter($key)
            && is_array($value)
            && array_is_list($value)
            && array_all($value, static fn (mixed $item): bool => $item === null || is_string($item));
    }

    private function errorResponse(string $version, int $status, string $message): Response
    {
        $response = $version === 'v1' ? showApiError(201, $message) : apiJsonError(201, $message);
        if (! $response instanceof Response) {
            $response = response($message, $status);
        }

        return $response->setStatusCode($status);
    }
}
