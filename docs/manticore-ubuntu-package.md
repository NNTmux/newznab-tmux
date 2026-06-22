# ManticoreSearch Ubuntu package operations

This project can run ManticoreSearch either through Docker Compose or through a native Ubuntu/Debian package install. Native package installs are managed by `systemd`, so Docker `ulimits` do **not** apply.

## 27.1.5 upgrade: `Too many open files` during binlog replay

If installing or configuring ManticoreSearch 27.1.5 fails with output like this:

```text
Job for manticore.service failed because the control process exited with error code.
Status: "Replaying binlogs..."
WARNING: accept() failed, raise ulimit -n and restart searchd: Too many open files
```

then the daemon is starting and replaying binlogs, but its file descriptor limit is too low for the current tables/binlogs/connections. This is a host-level `systemd` limit issue for package installs.

### 1. Pause app traffic before restarting Manticore

Stop NNTmux workers/processes that may reconnect repeatedly while Manticore is replaying binlogs:

```bash
php artisan tmux:stop
php artisan horizon:terminate
```

If Horizon is not used on that host, stop whichever queue supervisor/process manager is running workers.

### 2. Inspect the current limit

```bash
sudo systemctl show manticore -p LimitNOFILE -p LimitNPROC -p MainPID
pid=$(systemctl show manticore -p MainPID --value)
if [ "$pid" != "0" ]; then sudo grep -E 'open files|max user processes' /proc/$pid/limits; fi
sudo journalctl -u manticore -n 200 --no-pager
```

A low `LimitNOFILE` value, commonly `1024`, is not enough for large RT tables and binlog replay.

### 3. Add a systemd override

```bash
sudo install -d -m 0755 /etc/systemd/system/manticore.service.d
cat <<'EOF' | sudo tee /etc/systemd/system/manticore.service.d/override.conf
[Service]
LimitNOFILE=1048576
LimitNPROC=65535
EOF
sudo systemctl daemon-reload
```

`LimitNOFILE` must be at least as high as the `max_open_files` value in `/etc/manticoresearch/manticore.conf`. This repo’s `config/manticore.conf` sets:

```text
max_open_files = 524288
```

The higher `systemd` limit leaves headroom for future table/binlog growth. If
`systemctl show manticore -p LimitNOFILE` is high but Manticore still logs
`Too many open files`, check the live process limit too; `searchd` may have
lowered its own limit from `max_open_files`:

```bash
pid=$(systemctl show manticore -p MainPID --value)
if [ "$pid" != "0" ]; then sudo grep -E 'open files|max user processes' /proc/$pid/limits; fi
```

### 4. Ensure Manticore config has `max_open_files`

If the production `/etc/manticoresearch/manticore.conf` is not generated from this repository, add this inside the `searchd { ... }` block:

```text
max_open_files = 524288
```

Then validate the config:

```bash
sudo searchd --config /etc/manticoresearch/manticore.conf --check
```

### 5. Restart and finish package configuration

```bash
sudo systemctl stop manticore || true
sudo systemctl reset-failed manticore
sudo systemctl start manticore
sudo systemctl status manticore --no-pager
sudo journalctl -u manticore -n 100 --no-pager
sudo dpkg --configure -a
```

If `Status: "Replaying binlogs..."` remains for a while but the `Too many open files` warnings stop, let replay continue. Large binlogs can take time after an upgrade.

### 6. Verify the effective runtime limit

```bash
pid=$(systemctl show manticore -p MainPID --value)
sudo grep -E 'open files|max user processes' /proc/$pid/limits
sudo lsof -p "$pid" | wc -l
```

The `open files` hard and soft limits should reflect the override.

### 7. Resume app traffic

After Manticore is fully active and responding:

```bash
php artisan nntmux:check-index --manticore --releases
php artisan nntmux:search-reconcile --dry-run --since=1h
php artisan tmux:start
```

Restart queue workers/process supervisors as appropriate for the host.

## Backtrace or crash during binlog replay

If `searchd --config /etc/manticoresearch/manticore.conf --check` returns `OK`
but `manticore.service` prints a backtrace while the service status is still
`Status: "Replaying binlogs..."`, the config is syntactically valid and the
failure is happening while Manticore replays persisted binlogs. Treat this as a
possible daemon bug, corrupt binlog, or incompatible replay edge case.

If the log also contains both of these lines, handle the file limit first even
when `systemctl show manticore -p LimitNOFILE` already looks high:

```text
prealloc failed: failed to open file '...': 'Too many open files' - NOT SERVING
BuddyStart(...) ... boost::process::detail::posix::async_pipe ... abort
```

This means Manticore exhausted its effective descriptor limit while opening table
files, then crashed while starting Buddy. Ensure `/etc/manticoresearch/manticore.conf`
uses `max_open_files = 524288` or higher, then restart with the matching systemd
override before touching binlogs or table data.

```bash
sudo grep -nE 'max_open_files|preopen_tables|buddy_path|listen.*9443|https' /etc/manticoresearch/manticore.conf
sudo perl -0pi -e 's/max_open_files\s*=\s*\d+/max_open_files = 524288/' /etc/manticoresearch/manticore.conf
sudo searchd --config /etc/manticoresearch/manticore.conf --check
sudo systemctl daemon-reload
sudo systemctl stop manticore || true
sudo systemctl reset-failed manticore
sudo systemctl start manticore
sleep 3
pid=$(systemctl show manticore -p MainPID --value)
if [ "$pid" != "0" ]; then sudo grep -E 'open files|max user processes' /proc/$pid/limits; fi
sudo journalctl -u manticore -n 120 --no-pager
```

If Buddy still crashes after the effective `/proc/$pid/limits` open-files value
is high, temporarily disable Buddy to get the search daemon online and finish
the package configuration:

```bash
sudo cp -a /etc/manticoresearch/manticore.conf /etc/manticoresearch/manticore.conf.before-buddy-disable
sudo perl -0pi -e 's/^\s*#?\s*buddy_path\s*=.*$/\tbuddy_path\t\t=/' /etc/manticoresearch/manticore.conf
sudo searchd --config /etc/manticoresearch/manticore.conf --check
sudo systemctl restart manticore
sudo systemctl status manticore --no-pager
sudo dpkg --configure -a
```

Re-enable Buddy after upgrading to a fixed Manticore package or after the table
open-file issue is resolved.

Do not keep restart-looping the service. First stop application traffic and keep
the evidence needed for rollback or an upstream bug report:

```bash
php artisan tmux:stop
php artisan horizon:terminate
sudo systemctl stop manticore || true
sudo systemctl reset-failed manticore

stamp=$(date +%Y%m%d-%H%M%S)
sudo install -d -m 0755 /root/manticore-recovery/$stamp
sudo journalctl -u manticore -b --no-pager > /root/manticore-recovery/$stamp/journal.log
sudo cp -a /var/log/manticore /root/manticore-recovery/$stamp/logs
sudo cp -a /etc/manticoresearch/manticore.conf /root/manticore-recovery/$stamp/manticore.conf
sudo tar -C /var/lib -czf /root/manticore-recovery/$stamp/manticore-var-lib-before-recovery.tgz manticore
if command -v coredumpctl >/dev/null 2>&1; then
  sudo coredumpctl info searchd > /root/manticore-recovery/$stamp/coredumpctl-info.txt || true
fi
```

List the binlog files under the configured `binlog_path`/`data_dir`:

```bash
sudo find /var/lib/manticore /var/lib/manticore/data -maxdepth 2 -type f \
  \( -name 'binlog*' -o -name '*.binlog*' \) -ls 2>/dev/null
```

If the service still crashes during replay after the file descriptor limit fix,
quarantine the binlogs instead of deleting them. This allows Manticore to start
from flushed table data while preserving the replay files for analysis. Pending
unflushed writes may be missing and must be reconciled/reindexed afterward.

```bash
stamp=$(date +%Y%m%d-%H%M%S)
sudo install -d -m 0755 /var/lib/manticore/binlog-quarantine-$stamp
sudo find /var/lib/manticore/data -maxdepth 1 -type f \
  \( -name 'binlog*' -o -name '*.binlog*' \) \
  -exec mv -t /var/lib/manticore/binlog-quarantine-$stamp {} +

sudo systemctl start manticore
sudo systemctl status manticore --no-pager
sudo journalctl -u manticore -n 100 --no-pager
sudo dpkg --configure -a
```

After Manticore starts, reconcile the index with the database before resuming
normal processing:

```bash
php artisan nntmux:check-index --manticore --releases
php artisan nntmux:search-reconcile --dry-run --since=24h
php artisan nntmux:search-reconcile --reindex --since=24h
php artisan tmux:start
```

If tables fail to open even after quarantining binlogs, keep the recovery backup
and rebuild the RT tables during a maintenance window:

```bash
php artisan manticore:create-indexes --drop
php artisan nntmux:populate --manticore --all
```

Attach `/root/manticore-recovery/<stamp>/journal.log`, the copied searchd logs,
the Manticore package version, and the preserved quarantined binlogs when filing
an upstream Manticore issue.

## Last-resort binlog recovery

Do **not** delete or move binlogs as a first response. If Manticore still cannot start after raising file limits and config validation passes, capture logs first:

```bash
sudo journalctl -u manticore -b --no-pager > /tmp/manticore-journal.log
sudo tail -500 /var/log/manticore/searchd.log > /tmp/manticore-searchd-tail.log
```

Only after backing up `/var/lib/manticore` and accepting that search indexes may need a full rebuild should binlogs be moved aside. Prefer a maintenance window and reindex with:

```bash
php artisan manticore:create-indexes --drop
php artisan nntmux:populate --manticore --all
```

