# Agents never commit or push

NNTmux agents must not run `git commit` or `git push` (including `--amend`, tags, or force pushes). The maintainer reviews, commits, and pushes.

- When a task is done, stage every project file you created or modified with `git add <path>` (list paths explicitly; no `git add -A` / `git add .`).
- Never stage temporary files you created (scratch scripts, debug output, logs, planning documents), and do not stage unrelated pre-existing changes.
- Commit or push only when the user explicitly asks in the current conversation; that approval covers that one request only.

## Task completion report

- Every final reply after changing project files must include a brief change summary, a file list, and a clickable link to a temporary Markdown file containing the actual diff.
- List each affected file exactly once with its status (`Created`, `Updated`, `Deleted`, or `Renamed`), a clickable path, and a short description. For renames, show the old and new paths in one entry.
- Write the complete unified diff for the task's changes to a uniquely named `.md` file under `/tmp`, inside fenced `diff` code blocks, including created and deleted files. Read the diff with `git diff --cached -- <explicit task paths>` after staging, and include any remaining unstaged task changes. The Markdown file must contain the actual patch; a review link or Git command alone is insufficient.
- Link the temporary Markdown file using its absolute path in the final reply; the patch does not need to appear inline in the reply. Leave the file available for review and never stage it.
- Report only changes made for the current task; exclude unrelated pre-existing changes and temporary files from the project file list and patch content.
- If no project files changed, explicitly state that no files changed.
