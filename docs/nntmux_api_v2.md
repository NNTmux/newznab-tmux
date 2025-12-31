# NNTmux API v2 Specification

This document summarizes the JSON-based v2 API exposed under `/api/v2`. It is derived from the current routes and controllers in `routes/api.php` and `app/Http/Controllers/Api/ApiV2Controller.php`.

## Base URL
```
https://<host>/api/v2
```

## Authentication & Throttling
- Auth: Bearer-style `api_token` query or form parameter; all endpoints except `capabilities` require it.
- Middleware: `auth:api` + `throttle:rate_limit,1` (Laravel). Server configuration defines the actual limit window.
- Error responses use HTTP 403 with `{ "error": "Invalid API Token" }` for bad tokens and HTTP 403 with `{ "error": "Missing parameter (api_token)" }` when absent.

## Common Query Parameters
- `api_token` (string, required except for `capabilities`)
- `limit` (int, optional, default 100, max 100)
- `offset` (int, optional, default 0)
- `cat` (comma-separated category IDs; TV_HD auto-adds TV_WEBDL if enabled server-side)
- `maxage` (int days, optional; negative means disabled)
- `minsize` (int bytes, optional, default 0)

## Endpoints

### 1) Capabilities
- `GET /capabilities`
- Auth: none
- Response: server metadata, registration flags, searching capabilities, and category tree.

Example response (abbreviated):
```json
{
  "server": {
    "title": "NNTmux",
    "strapline": "<site strapline>",
    "email": "admin@example.com",
    "url": "https://example.com"
  },
  "limits": { "max": 100, "default": 100 },
  "registration": { "available": "no", "open": "yes" },
  "searching": {
    "search": { "available": "yes", "supportedParams": "id" },
    "tv-search": { "available": "yes", "supportedParams": "id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep" },
    "movie-search": { "available": "yes", "supportedParams": "id, imdbid, tmdbid, traktid" },
    "audio-search": { "available": "no", "supportedParams": "" }
  },
  "categories": [
    {
      "id": 2000,
      "name": "Movies",
      "subcategories": { "2030": "SD", "2040": "HD" }
    }
  ]
}
```

### 2) Movies Search
- `GET /movies`
- Auth: `api_token`
- Params:
  - `imdbid`, `tmdbid`, `traktid` (ints, optional; defaults -1)
  - `id` (string, optional; title/name search)
  - Common params: `limit`, `offset`, `cat`, `maxage`, `minsize`
- Response: JSON object with totals, rate/usage counters, and `Results` array.

Example response (trimmed):
```json
{
  "Total": 123,
  "apiCurrent": 2,
  "apiMax": 1000,
  "grabCurrent": 1,
  "grabMax": 100,
  "apiOldestTime": "Wed, 20 Nov 2024 12:00:00 +0000",
  "grabOldestTime": "",
  "Results": [
    {
      "title": "Movie.Title.2024.1080p",
      "details": "https://example.com/details/<guid>",
      "url": "https://example.com/getnzb?id=<guid>.nzb&r=<api_token>",
      "category": 2040,
      "category_name": "Movies > HD",
      "added": "Wed, 20 Nov 2024 12:00:00 +0000",
      "size": 734003200,
      "files": 55,
      "grabs": 0,
      "comments": null,
      "password": 0,
      "usenetdate": "Wed, 20 Nov 2024 10:00:00 +0000",
      "imdbid": 1234567,
      "tmdbid": 98765,
      "traktid": null
    }
  ]
}
```

### 3) General Search (by GUID or recent)
- `GET /search`
- Auth: `api_token`
- Params: `id` (guid or search id, optional), `cat`, `limit`, `offset`, `maxage`, `minsize`, `group` (Usenet group name, optional)
- Response: same envelope as Movies (`Total`, counters, `Results` array with base fields; movie/TV extras included when applicable).

### 4) TV Search
- `GET /tv`
- Auth: `api_token`
- Params (all optional but at least one identifier recommended):
  - Identifiers: `vid` (site id), `tvdbid`, `traktid`, `rid` (TVRage), `tvmazeid`, `imdbid`, `tmdbid`
  - Episode selectors: `season`, `ep` (episode or MM/DD for daily), airdate inferred when `season` is YYYY and `ep` contains `/`
  - Name: `id` (search string)
  - Common: `limit`, `offset`, `cat`, `maxage`, `minsize`
- Response: same envelope as Movies. TV-specific fields include `episode_title`, `season`, `episode`, `tvairdate`, `tvdbid`, `traktid`, `tvrageid`, `tvmazeid`, `imdbid`, `tmdbid`.

### 5) Download NZB
- `GET /getnzb`
- Auth: `api_token`
- Params: `id` (release guid, required), `del=1` (optional; deletes from user cart on download)
- Behavior: Valid GUID redirects to `/getnzb?r=<api_token>&id=<guid>[&del=1]`. On missing/invalid GUID, returns 404 JSON `{ "data": "No such item (the guid you provided has no release in our database)" }`.

### 6) Release Details
- `GET /details`
- Auth: `api_token`
- Params: `id` (guid, required)
- Response: single release object with the base fields plus movie/TV-specific attributes. Example:
```json
{
  "title": "Some.Show.S01E01.720p",
  "details": "https://example.com/details/<guid>",
  "link": "https://example.com/getnzb?id=<guid>.nzb&r=<api_token>",
  "category": 5030,
  "category_name": "TV > SD",
  "added": "Wed, 20 Nov 2024 12:00:00 +0000",
  "size": 450971565,
  "files": 44,
  "grabs": 3,
  "comments": 0,
  "password": 0,
  "usenetdate": "Wed, 20 Nov 2024 10:00:00 +0000",
  "tvairdate": "2024-11-19",
  "tvdbid": 12345,
  "traktid": 67890,
  "tvrageid": null,
  "tvmazeid": null,
  "imdbid": 1234567,
  "tmdbid": 98765
}
```

## Error Responses (observed)
- 400 `{ "error": "Missing parameter (guid is required for single release details)" }` (details)
- 403 `{ "error": "Missing parameter (api_token)" }`
- 403 `{ "error": "Invalid API Token" }`
- 404 `{ "data": "No such item (the guid you provided has no release in our database)" }`

## Notes
- All responses are UTF-8 JSON.
- Category data in capabilities comes from `Category::getForApi()` via `CategoryTransformer` and includes a map of subcategory IDs to names.
- TV `imdbid`/`tmdbid` are passed through as provided; unlike v1, v2 does not strip the `tt` prefix in controller logic.
