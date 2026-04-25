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

## Common Query Parameters

| Parameter | Type | Default | Notes |
|---|---|---:|---|
| `api_token` | string | - | Required except `capabilities`. |
| `id` | string | `""` | Search text/fallback identifier on search endpoints. |
| `limit` | int | `100` | Max rows in page. |
| `offset` | int | `0` | Zero-based pagination offset. |
| `cat` | csv string | `-1` | Category filter; `TV_WEBDL` auto-add can apply when `TV_HD` is requested. |
| `group` | string | `-1` | Usenet group filter (where supported). |
| `maxage` | int | `-1` | Max post age in days. Invalid values return JSON `400`. |
| `minsize` | int | `0` | Min release size in bytes. |
| `maxsize` | int | - | Accepted for compatibility; currently not enforced in query layer. |
| `sort` | string | `posted_desc` | `cat|name|size|files|stats|posted` + `_asc|_desc`. |

Sorting examples:

- `/api/v2/search?api_token=<token>&id=ubuntu&sort=posted_desc`
- `/api/v2/search?api_token=<token>&id=ubuntu&sort=name_asc`
- `/api/v2/tv?api_token=<token>&id=last+week+tonight&season=2025&ep=11/10&sort=posted_desc`
- `/api/v2/movies?api_token=<token>&imdbid=tt0816692&sort=size_desc`

JSON sorting response snippet (`sort=size_desc`):

```json
{
  "Total": 2,
  "Results": [
    { "title": "Ubuntu ISO x64", "size": 734003200 },
    { "title": "Ubuntu ISO x86", "size": 367001600 }
  ]
}
```

`Results` are ordered largest-to-smallest because `sort=size_desc`.

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

- `GET /search`
- Auth: required

Behavior:

- If `id` is present: text search.
- If `id` is omitted: browse mode.
- Includes API usage counters in response (`apiCurrent`, `apiMax`, `grabCurrent`, `grabMax`, `apiOldestTime`, `grabOldestTime`).

## 3) TV Search

- `GET /tv`
- Auth: required

Identifiers:

- `vid`, `tvdbid`, `traktid`, `rid`, `tvmazeid`, `imdbid`, `tmdbid`

Optional filters:

- `season`, `ep`, `cat`, `maxage`, `minsize`, `sort`, `offset`, `limit`

Daily parsing:

- `season=YYYY` and `ep=MM/DD` infers an airdate query.

## 4) Movie Search

- `GET /movies`
- Auth: required

Identifiers:

- `imdbid`, `tmdbid`, `traktid`

Optional filters:

- `id`, `cat`, `maxage`, `minsize`, `sort`, `offset`, `limit`

## 5) Audio Search

- `GET /audio`
- Auth: required

Required:

- `id` (query string)

## 6) Book Search

- `GET /books`
- Auth: required

Required:

- `id` (query string)

## 7) Anime Search

- `GET /anime`
- Auth: required

Selectors:

- `id` and/or `anidbid` and/or `anilistid`

## 8) Get NZB

- `GET /getnzb`
- Auth: required
- Valid GUID redirects to `/getnzb?r=<api_token>&id=<guid>[&del=1]`
- Not found returns HTTP `404` JSON.

## 9) Details

- `GET /details`
- Auth: required
- Requires `id` (GUID)

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
  "Results": []
}
```

### Details Object (`/details`)

Returns a single release object (not envelope). Download field name is `link` (not `url`).

## Error Response Conventions

- Missing/invalid token: JSON `403`
- Invalid `maxage`: JSON `400`
- Invalid `sort`: JSON `400`
- Missing required endpoint parameter (`id`, etc.): JSON `400`
- Missing GUID in `/getnzb`: JSON `404`

## Unsupported in v2

The following are intentionally not part of v2 JSON API:

- `register`
- `user`
- `comments`
- `commentadd`
- `cartadd`
- `cartdel`
- `nzbadd`

NZB upload remains in v1 (`/api/v1/api?t=nzbadd`).

## Postman Collection

- `docs/postman/nntmux_api_v2.postman_collection.json`
