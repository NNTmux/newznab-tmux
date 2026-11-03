# TV processing

## Provider errors are never "show not found"

In `app/Services/TvProcessing/`, only a 404 or an empty result from TVDB, TVMaze, TMDB or Trakt counts as a miss. Every other failure (429, 5xx, 401/403 rejected key, other 4xx, unreadable body, connection error, rejected TVDB login) must surface as `App\Exceptions\TvProviderException`, so the release is retried instead of being written as `TvLookupStatus::ShowNotFound` (-10), which is never retried automatically. TMDB and Trakt only throw through their `throwingOnTransientFailure()` copies; use those in the pipeline and for enrichment, never the default Trakt client (it sleeps up to 300s on a 429).

## Fix the backlog locally first

Releases waiting for an episode are linked from stored `tv_episodes` by `tv:relink-local` (hourly; `--unmatched` daily for -10/-100). It calls no API and resumes from a cached cursor. `tv:repair-links --stuck` also links stored episodes before queueing an API retry. Run these before mass-requeueing releases into the API path (`tv:reprocess-unmatched --requeue`).

## Local show matching

`LocalShowMatcher` compares titles with `AbstractTvProvider::comparableTitle()` (no punctuation, `&` = and, year/country qualifiers ignored) and picks among same-named shows: holds the episode, then year, then country, then most episodes. Don't go back to "first exact title, lowest id": that linked US Jeopardy airdates to a 2002 GB show. Search providers with the full title first and the country-stripped title (`ParsedTvRelease::searchTitle()`) only as a fallback, or "MasterChef Australia" matches the US show.
