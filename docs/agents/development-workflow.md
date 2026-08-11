# Development workflow: branch → PR → auto-merge

Master only moves by pull request. A ruleset enforces this server-side — direct pushes to master are rejected for everyone, including docs-only changes and repo admins. The required check is the **PHP 8.5 via Sail** job in `.github/workflows/laravel.yml`; PRs squash-merge automatically once it passes.

## The loop

1. **Branch, in its own worktree.** Pick a short kebab-case branch name with a type prefix (`feat/`, `fix/`, `chore/`, `docs/`):

   ```bash
   git worktree add ~/worktrees/nntmux/<branch> -b <branch>
   ```

   Work and commit inside the worktree. (A plain `git switch -c <branch>` in the main checkout is acceptable when a worktree is impractical, e.g. the change depends on the running dev containers.)

2. **Open the PR and arm auto-merge** in the same breath:

   ```bash
   git push -u origin <branch>
   gh pr create --fill        # heredoc body for anything non-trivial
   gh pr merge --auto --squash
   ```

3. **Monitor until the PR resolves.** Watch checks, then confirm the merge actually happened:

   ```bash
   gh pr checks <number> --watch
   gh pr view <number> --json state,mergedAt
   ```

   - **Merged** → go to step 4.
   - **CI failed** → fix on the branch, push, and watch again. The PR stays open; auto-merge fires once checks go green.

4. **Clean up and sync.** GitHub deletes the remote branch on merge; mirror that locally:

   ```bash
   git -C /mnt/data/nntmux-dev switch master
   git -C /mnt/data/nntmux-dev pull --ff-only
   git -C /mnt/data/nntmux-dev worktree remove ~/worktrees/nntmux/<branch>
   git -C /mnt/data/nntmux-dev branch -d <branch>
   ```

The loop is done when master contains the squashed commit and `git worktree list` + `git branch` show no leftovers from the branch.

## Notes

- One PR per unit of work; keep unrelated changes on separate branches.
- Auto-merge is armed at PR creation, not after review — green CI is the merge gate.
- Squash is the only enabled merge method; intermediate commit messages on the branch can stay rough.
- CI runs on every PR and on pushes to master (post-merge). A feature-branch push alone does not trigger CI until its PR exists.
