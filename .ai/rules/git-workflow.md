# Agents never commit or push

NNTmux agents must not run `git commit` or `git push` (including `--amend`, tags, or force pushes). The maintainer reviews, commits, and pushes.

- When a task is done, stage every project file you created or modified with `git add <path>` (list paths explicitly; no `git add -A` / `git add .`).
- Never stage temporary files you created (scratch scripts, debug output, logs, planning documents), and do not stage unrelated pre-existing changes.
- Commit or push only when the user explicitly asks in the current conversation; that approval covers that one request only.

## Task completion report

- Every final reply after changing project files must include a brief change summary, a file list, and access to the diff.
- List each affected file exactly once with its status (`Created`, `Updated`, `Deleted`, or `Renamed`), a clickable path, and a short description. For renames, show the old and new paths in one entry.
- Include an available review-diff link for the task's changes. If no suitable link exists, provide a runnable `git diff -- <explicit task paths>` command; use `git diff --cached -- <explicit task paths>` after staging. If task changes remain unstaged, include the unstaged command too. Full inline patches are optional.
- Report only changes made for the current task; exclude unrelated pre-existing changes and temporary files from the file list and diff reference.
- If no project files changed, explicitly state that no files changed.
