# NNTmux API v2 Specification

Code-first reference for the JSON API under `/api/v2`.

Primary sources:

- `routes/api.php`
- `app/Http/Controllers/Api/ApiV2Controller.php`
- `app/Transformers/ApiTransformer.php`
- `app/Transformers/DetailsTransformer.php`
- `app/Transformers/CategoryTransformer.php`

## Base URL

```text
https://<host>/api/v2
```

## Authentication and Rate Limits

- `GET /capabilities` is public.
- All other v2 routes require `api_token`.
- Route-level middleware uses token-aware throttling (`apiRateLimit`) and accepts `api_token` or the legacy `apikey` alias when that middleware is reused.
- `POST /nzbadd` is intentionally exempt from route-level rate limiting and API request quotas, and uploads are not recorded as API usage. Authentication and posting privileges still apply.
- Daily quota: each role allows `apirequests` requests in a rolling 24 hours. Every authenticated request to a quota-counted endpoint (all except `capabilities` and `nzbadd`) is checked and recorded in one atomic step, so a user at exactly the limit is rejected and parallel requests cannot share the last slot. A request counts once it passes authentication, even if its parameters are then rejected.
- `/getnzb` also enforces the role's `downloadrequests` allowance; a user who has used it gets `429 Download limit reached`.
- Controller-level auth errors return a JSON error envelope:

```json
{
  "error": "Missing parameter (api_token)"
}
```

Common auth/rate-limit statuses:

| HTTP | Error |
|---:|---|
| 400 | `Missing parameter (api_token)` |
| 401 | `Incorrect user credentials` |
| 403 | `Account suspended` |
| 429 | `Request limit reached` |
| 429 | `Too many concurrent requests, retry shortly` (another request for the same user held the quota lock for over 5 seconds) |

## Common Query Parameters

| Parameter | Type | Default | Notes |
|---|---|---:|---|
| `api_token` | string | - | Required except `capabilities`. |
| `id` | string | `""` | Search text/fallback identifier on search endpoints. |
| `limit` | int | `100` | Rows per page. Values above `100` are capped at `100`; `0`, negative or non-integer values return JSON `400`. |
| `offset` | int | `0` | Zero-based pagination offset. Negative or non-integer values return JSON `400`. |
| `cat` | csv string | `-1` | Category filter; `TV_WEBDL` auto-add can apply when `TV_HD` is requested. |
| `group` | string | `-1` | Usenet group filter (where supported). |
| `maxage` | int | `-1` | Max post age in days. Invalid values return JSON `400`. |
| `minsize` | int | `0` | Min release size in bytes. |
| `sort` | string | `posted_desc` | `cat|name|size|files|stats|posted` + `_asc|_desc`. |

Only `cat` accepts a list (`cat[]=2000&cat[]=5030`). Any other parameter sent as
an array (for example `id[]=ubuntu`) returns JSON `400` (`Parameter id has an
unsupported type`) before the request is counted.

`maxsize` and `genre` are not supported and are not advertised in
`capabilities`.

Text search with `sort=name_asc|name_desc` orders by name across the 2,000 most
recent matches (after all filters); `Total` is capped at that number, so paging
never goes past what the ordering covers.

Sorting examples:

- `/api/v2/search?api_token=<token>&id=ubuntu&sort=posted_desc`
- `/api/v2/search?api_token=<token>&id=ubuntu&sort=name_asc`
- `/api/v2/tv?api_token=<token>&id=last+week+tonight&season=2025&ep=11/10&sort=posted_desc`
- `/api/v2/movies?api_token=<token>&imdbid=tt0816692&sort=size_desc`

JSON sorting response snippet (`sort=size_desc`):

```http
HTTP/1.1 200 OK
Content-Type: application/json
{
  "Total": 2,
  "apiCurrent": 0,
  "apiMax": 100,
  "grabCurrent": 0,
  "grabMax": 100,
  "apiOldestTime": "",
  "grabOldestTime": "",
  "results": [
    { "title": "Ubuntu ISO x64", "size": 734003200 },
    { "title": "Ubuntu ISO x86", "size": 367001600 }
  ]
}
```

Releases are ordered largest-to-smallest because `sort=size_desc`.

> The legacy `Results` (capital R) Fractal field has been replaced by the
> lower-case `results` field. Search responses retain pagination and quota
> metadata in the top-level JSON object. Movie/TV-only fields (`tvdbid`,
> `imdbid`, `season`, …) are omitted when they do not apply to a release.

## HTTP QUERY Method (RFC 10008)

The read-only search endpoints also accept the HTTP `QUERY` method, which carries
the filters in a JSON request body instead of the URL. This avoids URL-length limits
for long filter sets. Existing `GET` clients do not need to change.

Endpoints that accept `QUERY`: `/search`, `/tv`, `/movies`, `/audio`, `/books`,
`/anime`, `/details` (and `/api/v1/api` for read-only `t` functions, see the v1
API help page). `/getnzb`, `/nzbadd` and `/capabilities` do not accept `QUERY`
and return `405`.

```bash
curl -X QUERY https://<host>/api/v2/search \
  -H 'Content-Type: application/json' \
  -d '{"api_token":"<token>","id":"ubuntu","cat":[2000,5030],"limit":50,"sort":"posted_desc"}'
```

The parameters, results, quotas, rate limits and errors are the same as the
equivalent `GET` request.

### Body contract

- `Content-Type` must be exactly `application/json` (optionally `; charset=utf-8`).
  Other types, including `+json` variants and form bodies, return `415`.
- The body must be a JSON object, at most 8,192 bytes (`API_QUERY_MAX_BODY_BYTES`).
- Values must be strings or numbers. `cat` may also be a JSON array of strings or
  numbers; `[2000, 5030]` is the same as `"2000,5030"`. `null`, booleans, nested
  objects and arrays for other parameters return `400`.
- An empty string (`"id": ""`) behaves exactly like an empty URL parameter (`?id=`).
- Parameter names may contain only letters, digits and underscores (max 32
  characters). At most 64 parameters are accepted (URL and body combined).
- Parameters may also be sent in the URL, but the same parameter must not appear in
  both the URL and the body (`400`).

### Status codes specific to QUERY

| HTTP | Source | Meaning |
|---:|---|---|
| 400 | app | Malformed JSON, non-object body, unsupported value type, invalid or duplicate parameter |
| 405 | app | Endpoint does not accept `QUERY` |
| 411 | web server | No `Content-Length` (deployment policy of the bundled nginx config) |
| 413 | app / web server | Body over 8,192 bytes (app) or 10,000 bytes or more (web server) |
| 415 | app | `Content-Type` is not `application/json` |

### Caching and discovery

- `QUERY` responses are sent with `Cache-Control: private, no-store`: they contain
  per-user quota data and token-bearing download links.
- Responses of QUERY-capable endpoints include `Accept-Query: application/json`.
  `OPTIONS` on those endpoints returns `204` with `Allow` and `Accept-Query`.
- CORS preflights for `QUERY` are allowed, and `Accept-Query` and `Allow` are
  exposed to browser clients.

### Cursor pagination

A search cursor is bound to the endpoint and to the effective filters (URL and body
combined, after normalization), not to the HTTP method. A cursor obtained with `GET`
can therefore continue with `QUERY` when the filters are the same. A cursor used with
different filters, a different sort or on another endpoint returns `400`.

> Rollout note: cursors issued before this change was deployed are rejected once
> (`400`, "Search cursor does not match this query or index generation."). Clients
> restart from the first page.

### Web-server body limit (operators)

Laravel decodes the JSON body before any middleware runs, so the app limit is a
contract limit. The resource limit belongs in the web server. It must cover both
`/api/v1/api` and the v2 search paths without lowering the 100m limit that `POST`
NZB uploads need. The bundled `docker/8.5/nginx.conf` does this:

```nginx
map "$request_method:$http_content_length" $nntmux_query_body_status {
    default             0;
    "~^QUERY:$"         411;  # deployment policy: QUERY must declare Content-Length
    "~^QUERY:\d{5,}$"   413;  # 10000 bytes or more
}
# in server {}:
if ($nntmux_query_body_status = 411) { return 411; }
if ($nntmux_query_body_status = 413) { return 413; }
```

The `411` rule is a deployment policy, not an RFC 10008 requirement. HTTP/2 clients
may legitimately omit `Content-Length`, and browser JavaScript cannot set it. Only
keep the rule if every proxy hop into nginx forwards the length; without it,
`client_max_body_size` and the app limit still apply.

Apache equivalent. `LimitRequestBody` allows exactly N bytes, so `9999` matches
nginx's "10,000 or more is rejected". This rule limits size only and does not
require `Content-Length`:

```apache
<If "%{REQUEST_METHOD} == 'QUERY'">
    LimitRequestBody 9999
</If>
```

### Before enabling QUERY in production

Test with a production-equivalent proxy setup first:

- The CDN/WAF (for example Cloudflare) forwards `QUERY` with its body intact and does
  not block the method. Most CDNs do not cache non-`GET` requests by default.
- CORS preflights work through the CDN.
- The QUERY body limit applies at every layer, for both `/api/v1/api` and the v2 paths.
- The real browser → CDN → origin path, including `fetch` over HTTP/2, delivers
  `Content-Length` to nginx; otherwise drop the `411` rule.
- Run these checks on the nginx/Apache versions actually deployed.

## Endpoints

## 1) Capabilities

- `GET /capabilities`
- Auth: none

Returns:

- `server`
- `limits`
- `searching`
- `registration`
- `categories`
- `groups`
- `genres`

## 2) Search

- `GET /search` (also `QUERY`)
- Auth: required

Behavior:

- If `id` is present: text search.
- If `id` is omitted: browse mode.
- Includes API usage counters in the response body (`apiCurrent`, `apiMax`, `grabCurrent`, `grabMax`, `apiOldestTime`, `grabOldestTime`; see [Search Envelope](#search-envelope-search-tv-movies-audio-books-anime)). `apiCurrent` includes the current request.

## 3) TV Search

- `GET /tv` (also `QUERY`)
- Auth: required

Identifiers:

- `vid`, `tvdbid`, `traktid`, `rid`, `tvmazeid`, `imdbid`, `tmdbid`

Optional filters:

- `season`, `ep`, `cat`, `maxage`, `minsize`, `sort`, `offset`, `limit`

Daily parsing:

- `season=YYYY` and `ep=MM/DD` infers an airdate query.

## 4) Movie Search

- `GET /movies` (also `QUERY`)
- Auth: required

Identifiers:

- `imdbid`, `tmdbid`, `traktid`

Optional filters:

- `id`, `cat`, `maxage`, `minsize`, `sort`, `offset`, `limit`

## 5) Audio Search

- `GET /audio` (also `QUERY`)
- Auth: required

Required:

- `id` (query string)

## 6) Book Search

- `GET /books` (also `QUERY`)
- Auth: required

Required:

- `id` (query string)

## 7) Anime Search

- `GET /anime` (also `QUERY`)
- Auth: required

Selectors:

- `id` and/or `anidbid` and/or `anilistid`

Optional filters:

- `cat`, `maxage`, `minsize`, `sort`, `offset`, `limit`

## 8) Get NZB

- `GET /getnzb`
- Auth: required
- Requires `id` (GUID; a trailing `.nzb` is accepted). Optional `del=1` removes the release from the user's cart.
- A valid GUID streams the NZB directly (`200`, `Content-Type: application/x-nzb`) and records one grab.
- Errors are JSON: unknown GUID or missing NZB file `404`, download allowance used up `429 Download limit reached`.

## 9) Details

- `GET /details` (also `QUERY`)
- Auth: required
- Requires `id` (GUID)

## 10) Add NZB

- `POST /nzbadd`
- Auth: required; the user must have posting privileges (`can_post`)
- Content type: `multipart/form-data`

Fields:

| Field | Required | Notes |
|---|---|---|
| `api_token` | yes | API token for a verified, enabled user. |
| `nzb` | yes | Valid `.nzb` file staged for deferred import. |
| `nfo` | no | `.nfo` file with any safe basename, maximum 65,535 bytes. |
| `cat` | no | Echoed as response metadata; it does not affect import categorization. |

When `nfo` is supplied, its basename does not need to match the NZB basename.
The request is atomic: both files are validated before staging in an isolated
upload directory, and a partial write is rolled back.

NZB-only example:

```bash
curl -X POST -F "api_token=<token>" -F "nzb=@Release.nzb" https://<host>/api/v2/nzbadd
```

Paired example:

```bash
curl -X POST -F "api_token=<token>" -F "cat=5040" -F "nzb=@Release.nzb" -F "nfo=@scene-info.nfo" https://<host>/api/v2/nzbadd
```

Successful staging returns HTTP `201`:

```json
{
  "success": true,
  "status": "staged",
  "name": "Release",
  "category": "5040",
  "files": {
    "nzb": { "filename": "Release.nzb", "type": "nzb" },
    "nfo": { "filename": "scene-info.nfo", "type": "nfo" }
  }
}
```

Staging does not synchronously create a release. Import the NZB files first,
then import their paired NFO files using the manifest identity recorded by the
NZB importer:

```bash
php artisan nntmux:import-nzbs --folder=/path/to/NZB_UPLOAD_FOLDER
php artisan nntmux:import-nfos --folder=/path/to/NZB_UPLOAD_FOLDER
```

The NFO importer replaces an existing NFO for the resolved release. Add
`--delete` to remove successfully imported NFO payloads or `--delete-failed`
to remove payloads that cannot be linked. NZB-only responses set `files.nfo`
to `null`.

## Response Models

### Search Envelope (`/search`, `/tv`, `/movies`, `/audio`, `/books`, `/anime`)

```json
{
  "Total": 123,
  "apiCurrent": 2,
  "apiMax": 1000,
  "grabCurrent": 1,
  "grabMax": 100,
  "apiOldestTime": "Wed, 20 Nov 2024 12:00:00 +0000",
  "grabOldestTime": "",
  "results": [
    {
      "title": "Linux.ISO.Collection.2024-11",
      "guid": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "details": "https://example.com/details/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "url": "https://example.com/getnzb?id=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.nzb&r=<api_token>"
    }
  ]
}
```

Each result carries the release `guid`, so clients do not need to parse it out
of `details` or `url`. Use it as `id` for `/details` and `/getnzb`.

### Details Object (`/details`)

Returns a single release object (not envelope). Download field name is `link` (not `url`).

## Error Response Conventions

- `QUERY` body contract violations: JSON `400`, `413` or `415` (see [HTTP QUERY Method](#http-query-method-rfc-10008))
- Missing token: JSON `400`
- Invalid token: JSON `401`
- Disabled account: JSON `403`
- Invalid `maxage`: JSON `400`
- Invalid `sort`, `limit` or `offset`, or a scalar parameter sent as an array: JSON `400`
- Missing required endpoint parameter (`id`, etc.): JSON `400`
- Unknown GUID or missing NZB file in `/getnzb`: JSON `404`
- Daily request quota or download allowance used up: JSON `429`
- Search engine query failed and no recent result is available: JSON `503` with `Retry-After: 30`. Failed searches are never cached as empty results; if the same search succeeded recently, its last known rows are returned instead.

## Unsupported in v2

The following are intentionally not part of v2 JSON API:

- `register`
- `user`
- `comments`
- `commentadd`
- `cartadd`
- `cartdel`

## Postman Collection

- `docs/postman/nntmux_api_v2.postman_collection.json`
