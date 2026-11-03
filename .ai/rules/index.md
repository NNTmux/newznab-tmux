# Project rules index

Path-scoped and standing agent rules for NNTmux. Hand-written entries live here; Boost may also write under `.ai/rules/boost/` when scoped guidelines are enabled.

| Glob | File | Summary |
|------|------|---------|
| `**` | [command-execution.md](./command-execution.md) | Host WSL commands overrule Boost Sail “must use Sail” |
| `database/migrations/*cbp*` | [migrations.md](./migrations.md) | Keep `CBP_STORAGE_MIGRATION_EXECUTE` false after maintenance |
| `app/Console/Commands/OptimizeCbpStorage.php` | [migrations.md](./migrations.md) | Keep `CBP_STORAGE_MIGRATION_EXECUTE` false after maintenance |
| `**` | [git-workflow.md](./git-workflow.md) | Agents never commit or push; stage task files only; final replies require a change summary, file list, and a linked temporary Markdown file containing the complete diff |
| `app/Services/TvProcessing/**` | [tv-processing.md](./tv-processing.md) | Provider errors never become ShowNotFound; local relink before API retries; title-normalized show matching |
| `app/Services/{TmdbClient,TraktService,TvMazeClient}.php` | [tv-processing.md](./tv-processing.md) | Only 404/empty is a miss; throw `TvProviderException` otherwise |
| `app/Services/{MovieService,MoviesProcessor}.php` | [movie-processing.md](./movie-processing.md) | Cache raw provider data and validate per release; normalized title comparison; TMDB translations for German titles |
| `app/Console/Commands/{RelinkLocalTvEpisodes,RepairTvReleaseLinks,ReprocessUnmatchedTvReleases,ReprocessMissingEpisodes}.php` | [tv-processing.md](./tv-processing.md) | Local relink first, API requeue last |

Before editing, also `grep -rin 'keyword' .ai/rules` for related notes.
