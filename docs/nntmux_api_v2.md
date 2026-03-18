# NNTmux API v2 Specification

This document is a code-first reference for the JSON API under `/api/v2`, based on:

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
- All other v2 routes are behind `auth:api` and `throttle:rate_limit,1` middleware.
- Controller-level validation also requires `api_token` in request input/query.
- Invalid or missing token at controller level returns:

```json
{
  "error": "Missing or invalid API key"
}
```

with HTTP `403`.

> Note: If middleware rejects first, response shape may differ from controller responses depending on your auth guard configuration.

## Common Query Parameters

| Parameter | Type | Default | Notes |
|---|---|---:|---|
| `api_token` | string | - | Required for all endpoints except `capabilities`. |
| `limit` | int | `100` | Read from request as numeric value; not hard-clamped in `ApiV2Controller`. |
| `offset` | int | `0` | Zero-based pagination offset. |
| `cat` | csv string | `-1` | Comma-separated category IDs. If `TV_HD` is present and `catwebdl=0`, `TV_WEBDL` is auto-added. |
| `maxage` | int | `-1` | Max age in days (`-1` disables age filtering). |
| `minsize` | int | `0` | Minimum release size in bytes. |

## Endpoints

### 1) Capabilities

- `GET /capabilities`
- Auth: none
- Returns server metadata, declared limits, searching capabilities, registration flags, and category tree.

Example response (abbreviated):

```json
{
  "server": {
    "title": "NNTmux",
    "strapline": "<site strapline>",
    "email": "admin@example.com",
    "url": "https://example.com"
  },
  "limits": {
    "max": 100,
    "default": 100
  },
  "searching": {
    "search": {
      "available": "yes",
      "supportedParams": "id"
    },
    "tv-search": {
      "available": "yes",
      "supportedParams": "id,vid,tvdbid,traktid,rid,tvmazeid,imdbid,tmdbid,season,ep"
    },
    "movie-search": {
      "available": "yes",
      "supportedParams": "id, imdbid, tmdbid, traktid"
    },
    "audio-search": {
      "available": "no",
      "supportedParams": ""
    }
  },
  "registration": {
    "available": "no",
    "open": "yes"
  },
  "categories": [
    {
      "id": 2000,
      "name": "Movies",
      "subcategories": {
        "2030": "SD",
        "2040": "HD"
      }
    }
  ]
}
```

### 2) Search

- `GET /search`
- Auth: required

Parameters:

- `id` (optional): search term or GUID-like string
- `group` (optional): Usenet group name
- common parameters: `api_token`, `cat`, `offset`, `limit`, `maxage`, `minsize`

If `id` is omitted, endpoint returns newest browse results scoped by filters.

### 3) TV Search

- `GET /tv`
- Auth: required

Parameters:

- identifiers: `vid`, `tvdbid`, `traktid`, `rid`, `tvmazeid`, `imdbid`, `tmdbid`
- title fallback: `id`
- episode filters: `season`, `ep`
- common parameters

Daily episode behavior:

- if `season` is a 4-digit year and `ep` contains `/`, airdate is inferred as `YYYY-MM-DD`.

### 4) Movie Search

- `GET /movies`
- Auth: required

Parameters:

- identifiers: `imdbid`, `tmdbid`, `traktid` (default `-1` when not set)
- title fallback: `id`
- common parameters

Implementation note:

- movie search result sets are cached for 10 minutes by filter signature.

### 5) Get NZB

- `GET /getnzb`
- Auth: required

Parameters:

- `id` (GUID, required for success)
- `del=1` (optional): forwards delete flag in downstream redirect

Behavior:

- valid GUID: HTTP `302` redirect to `/getnzb?r=<api_token>&id=<guid>[&del=1]`
- missing/invalid GUID: HTTP `404`

```json
{
  "data": "No such item (the guid you provided has no release in our database)"
}
```

### 6) Details

- `GET /details`
- Auth: required

Parameters:

- `id` (GUID, required)

Errors:

- missing `id`: HTTP `400`

```json
{
  "error": "Missing parameter (guid is required for single release details)"
}
```

## Response Models

### Search Envelope (`/search`, `/tv`, `/movies`)

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
      "title": "Some.Release.2024.1080p",
      "details": "https://example.com/details/<guid>",
      "url": "https://example.com/getnzb?id=<guid>.nzb&r=<api_token>",
      "category": 2040,
      "category_name": "Movies > HD",
      "added": "Wed, 20 Nov 2024 12:00:00 +0000",
      "size": 734003200,
      "files": 55,
      "grabs": null,
      "comments": null,
      "password": 0,
      "usenetdate": "Wed, 20 Nov 2024 10:00:00 +0000"
    }
  ]
}
```

### Additional Movie Fields in `Results`

- `imdbid`
- `tmdbid`
- `traktid`

All three are `null` when source value is zero.

### Additional TV Fields in `Results`

- `episode_title`
- `season`
- `episode`
- `tvairdate`
- `tvdbid`
- `traktid`
- `tvrageid`
- `tvmazeid`
- `imdbid`
- `tmdbid`

### Details Object (`/details`)

Returns one release object (not envelope). Key difference from search results: download field is named `link` instead of `url`.

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

## Status Codes and Error Body Cheat Sheet

| Endpoint(s) | HTTP | Body |
|---|---:|---|
| `/movies`, `/search`, `/tv`, `/getnzb`, `/details` (token failure in controller) | 403 | `{ "error": "Missing or invalid API key" }` |
| `/details` (missing `id`) | 400 | `{ "error": "Missing parameter (guid is required for single release details)" }` |
| `/getnzb` (GUID not found) | 404 | `{ "data": "No such item (the guid you provided has no release in our database)" }` |

## Postman / Documenter Sync Source

If you maintain the public Postman page, use this markdown as the source of truth and mirror:

1. endpoint auth requirements
2. request parameter descriptions
3. response envelope vs details-object differences
4. exact error messages

Starter collection (import into Postman, then publish with Documenter):

- `docs/postman/nntmux_api_v2.postman_collection.json`

This prevents drift between code and `https://documenter.getpostman.com/view/3059471/RW8FGS9E`.
