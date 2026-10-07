# Agents never commit or push

NNTmux agents must not run `git commit` or `git push` (including `--amend`, tags, or force pushes). The maintainer reviews, commits, and pushes.

- When a task is done, stage every project file you created or modified with `git add <path>` (list paths explicitly; no `git add -A` / `git add .`).
- Never stage temporary files you created (scratch scripts, debug output, logs, planning documents), and do not stage unrelated pre-existing changes.
- Commit or push only when the user explicitly asks in the current conversation; that approval covers that one request only.
