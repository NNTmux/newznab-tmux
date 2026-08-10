# NNTmux

A Usenet indexer: downloads NNTP headers, assembles them into releases, enriches them with metadata, and serves them for search. Background processing is orchestrated by a tmux monitor that drives per-task panes.

## Language

**Threads**:
The configured count of parallel forked worker processes for a tmux-driven task (e.g. `binarythreads`, `backfillthreads`). Kept from nZEDb's Python-threading days; each "thread" is actually a separate process holding its own NNTP connection. UI and DB deliberately both say "threads" so the terms never diverge.
_Avoid_: workers, processes (in UI copy and setting names)

**Pane**:
A tmux pane owned by the monitor, running exactly one background task (binaries, backfill, releases, postprocessing, fix-names).

**Safe backfill**:
The incremental backfill mode: one group per cycle, a bounded number of headers (`backfill_qty` × `backfillthreads`), stopping at the Safe Backfill Date.
_Avoid_: calling the "All" mode "full backfill" in code — the setting values are Disabled/Safe/All.

**Amazon postprocessing**:
The metadata-lookup family for books, music, console and PC games (pane 2.2, `postthreadsamazon`). Named for the nZEDb-era Amazon Product API source, retained as the umbrella term for these categories.

**Edit Selected**:
An in-place update of several settings at once, applied to the groups an admin has checked on the group list. Distinct from **Bulk Add**, which creates groups from a name filter and edits nothing.
_Avoid_: "bulk edit" — "bulk" belongs to Bulk Add.

**Release floor**:
A group's `minsizetoformrelease` / `minfilestoformrelease`: the smallest collection that may become a release for that group. Combined with the site-wide setting by taking the larger of the two, so a group floor below the site setting has no effect. Zero and unset both mean "no group floor" and display as "n/a".
_Avoid_: calling a group floor an override of the site setting — it can only raise it, never lower it.

**Backfill Days**:
A group's `backfill_target`: how many days back the backfill runner aims for on that group. Always at least 1; a group never lacks a target.
