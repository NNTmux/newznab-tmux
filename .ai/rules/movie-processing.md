# Movie processing

## Cache provider data, validate per release

In `MovieService`, the TMDB, OMDb, IMDb and Trakt fetchers cache the provider's raw answer for an IMDb/TMDB id and check it against the current release (`matchesCurrentContext`) on every read. Never cache `false` because the data didn't match *this* release: the key is shared, so one wrong release used to hide the movie from the right one for days. The `doMovieUpdate` call would then store an `imdbid` with no `movieinfo` row. Cache `false` only for a real 404/empty answer.

## Compare titles through the normalizer

Title comparisons go through `titleSimilarity()` / `normalizeTitleForComparison()`: lowercase ASCII, `ä`=`ae`, `&`=`and`, no punctuation, no leading English article, and normalized titles of 3 characters or fewer only match exactly. Don't call `similar_text` on raw titles, and compare years with `yearDifference()`, not string similarity.

## Two match strengths

- `matchesCurrentContext()` checks metadata for an id that has already been chosen. An exact year accepts an international title ("Salve Geral Irmandade" vs "State of Fear").
- `isAcceptableSearchCandidate()` picks results from IMDb, OMDb, Trakt and TMDB searches. The title must also resemble one of the candidate's titles. TMDB supplies original, alternative and translated titles (`known_titles`), which is what lets German releases match.

## Parser

`parseMovieSearchName()` keeps hyphens and apostrophes inside titles (`Re-Animator`, `Spider-Man`, `Draughtsman's`). It takes the last plausible year before the first source tag, and strips language/edition tags from the end. Titles split by " - " or in brackets become `alternateTitles`, which are tried after the main title. Add new production examples to `MovieServiceTest::releaseNameProvider()`.
