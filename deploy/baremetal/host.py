#!/usr/bin/env python3
"""Native host lifecycle. Invoke through Ansible or systemd, never as a web endpoint."""

import argparse
import fcntl
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import time

ROOT = Path("/srv/nntmux")
CONFIG = Path("/etc/nntmux")
COMMIT = re.compile(r"^[a-f0-9]{40}$")
APP_SERVICES = [
    "nginx",
    "nntmux-scheduler.timer",
    "nntmux-scheduler.service",
    "nntmux-horizon",
    "nntmux-tmux",
    "php8.5-fpm",
]
DATA_PATHS = [
    "/var/lib/mysql",
    "/var/lib/redis",
    "/var/lib/manticore",
    "/var/lib/elasticsearch",
]
CONFIG_PATHS = [
    "/etc/nntmux",
    "/etc/nginx/sites-available/nntmux",
    "/etc/nginx/sites-enabled/nntmux",
    "/etc/php/8.5",
    "/etc/mysql/mariadb.conf.d/70-nntmux.cnf",
    "/etc/redis/redis.conf",
    "/etc/manticoresearch",
    "/etc/elasticsearch",
    "/etc/letsencrypt",
]
IDENTITY = [
    "APP_KEY",
    "DB_DATABASE",
    "DB_USERNAME",
    "DB_PASSWORD",
    "REDIS_PASSWORD",
    "ADMIN_USER",
    "ADMIN_EMAIL",
    "ADMIN_PASS",
    "ELASTICSEARCH_USER",
    "ELASTICSEARCH_PASS",
]
BACKUP_KEYS = {"RESTIC_PASSWORD", "AWS_ACCESS_KEY_ID", "AWS_SECRET_ACCESS_KEY"}


class DeploymentError(RuntimeError):
    pass


def read_json(path):
    return json.loads(Path(path).read_text())


def atomic_json(path, value, mode=0o600):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(path.name + ".tmp")
    with temporary.open("w") as stream:
        os.chmod(temporary, mode)
        json.dump(value, stream, indent=2)
        stream.write("\n")
    temporary.replace(path)


def run(argv, *, cwd=None, env=None, check=True):
    result = subprocess.run(
        [str(a) for a in argv],
        cwd=cwd,
        env=env,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if check and result.returncode:
        # Child output can contain database URLs, credentials, or application exception context.
        raise DeploymentError(
            f"{Path(str(argv[0])).name} failed with status {result.returncode}; inspect protected service logs."
        )
    return result


def systemctl(action, *units, check=True):
    return run(["systemctl", action, *units], check=check)


def host():
    return read_json(CONFIG / "host.json")


def data_services():
    return ["mariadb", "redis-server", host()["search_driver"]]


def release_path(commit):
    if not COMMIT.fullmatch(commit):
        raise DeploymentError("A full lowercase 40-character commit is required.")
    return ROOT / "releases" / commit


def current_commit():
    link = ROOT / "current"
    if not link.is_symlink():
        raise DeploymentError("No current managed release exists.")
    target = link.resolve()
    if target.parent != ROOT / "releases" or not COMMIT.fullmatch(target.name):
        raise DeploymentError("Current release points outside managed releases.")
    return target.name


def activate(commit):
    path = release_path(commit)
    if not (path / ".baremetal-ready.json").is_file():
        raise DeploymentError("Only a validated release can be activated.")
    temporary = ROOT / "current.next"
    temporary.unlink(missing_ok=True)
    temporary.symlink_to(path)
    temporary.replace(ROOT / "current")
    atomic_json(
        CONFIG / "application.json",
        read_json(CONFIG / "releases" / (commit + ".json")),
        0o640,
    )
    run(["chown", "root:nntmux", CONFIG / "application.json"])


def artisan(commit, *arguments):
    return run(
        [
            "runuser",
            "-u",
            "nntmux",
            "--",
            "/usr/bin/php8.5",
            "artisan",
            *arguments,
            "--no-interaction",
        ],
        cwd=release_path(commit),
    )


def scalar(value):
    if isinstance(value, bool):
        return "true" if value else "false"
    if not isinstance(value, (str, int, float)) or any(
        c in str(value) for c in "\n\r\x00"
    ):
        raise DeploymentError("Environment settings must be single-line scalar values.")
    return str(value)


def environment(candidate, metadata):
    values = {
        k: scalar(v)
        for k, v in (candidate["settings"] | candidate["secrets"]).items()
        if k not in BACKUP_KEYS
    }
    if any(not re.fullmatch(r"[A-Z][A-Z0-9_]*", k) for k in values):
        raise DeploymentError(
            "Application setting names must be uppercase environment keys."
        )
    values.update(
        {
            "APP_ENV": "production",
            "APP_DEBUG": "false",
            "APP_URL": "https://" + metadata["hostname"],
            "DB_CONNECTION": "mariadb",
            "DB_HOST": "127.0.0.1",
            "DB_PORT": "3306",
            "REDIS_HOST": "127.0.0.1",
            "REDIS_PORT": "6379",
            "REDIS_CLIENT": "phpredis",
            "REDIS_USERNAME": "default",
            "CACHE_STORE": "redis",
            "SESSION_DRIVER": "redis",
            "QUEUE_CONNECTION": "redis",
            "SESSION_SECURE_COOKIE": "true",
            "SEARCH_DRIVER": metadata["search_driver"],
            "MANTICORESEARCH_HOST": "127.0.0.1",
            "MANTICORESEARCH_HOSTS": "",
            "MANTICORESEARCH_PORT": "9308",
            "MANTICORESEARCH_SCHEME": "http",
            "ELASTICSEARCH_HOST": "127.0.0.1",
            "ELASTICSEARCH_PORT": "9200",
            "ELASTICSEARCH_SCHEME": "http",
            "PATH_TO_NZBS": str(ROOT / "shared/storage/nzb"),
            "COVERS_PATH": str(ROOT / "shared/storage/covers"),
            "TEMP_UNRAR_PATH": str(ROOT / "shared/storage/tmp/unrar"),
            "TEMP_UNZIP_PATH": str(ROOT / "shared/storage/tmp/unzip"),
            "UNRAR_PATH": "/usr/bin/unrar",
            "UNZIP_PATH": "/usr/bin/unzip",
            "FFMPEG_PATH": "/usr/bin/ffmpeg",
            "MEDIAINFO_PATH": "/usr/bin/mediainfo",
            "LAME_PATH": "/usr/bin/lame",
            "TIMEOUT_PATH": "/usr/bin/timeout",
            "IMAGE_DRIVER": "imagick",
            "TRUST_CLOUDFLARE": "false",
            "TRUSTED_PROXIES": "",
        }
    )
    previous = CONFIG / "application.json"
    if previous.exists():
        old = read_json(previous)
        if any(old.get(key) != values.get(key) for key in IDENTITY):
            raise DeploymentError(
                "Deployment identity changes require a separate migration."
            )
    return values


def dotenv(values):
    lines = []
    for key, value in sorted(values.items()):
        value = (
            scalar(value).replace("\\", "\\\\").replace('"', '\\"').replace("$", "\\$")
        )
        lines.append(f'{key}="{value}"')
    return "\n".join(lines) + "\n"


def link_shared(path, name, destination):
    source = path / name
    if source.is_symlink():
        if source.resolve() != destination.resolve():
            raise DeploymentError("Unexpected release storage symlink.")
        return
    if source.is_dir():
        if name == "storage":
            shutil.copytree(
                source,
                destination,
                dirs_exist_ok=True,
                ignore=shutil.ignore_patterns("*.log", "install.lock", "*.php"),
            )
        shutil.rmtree(source)
    elif source.exists():
        source.unlink()
    source.symlink_to(destination)


def prepare(commit):
    path = release_path(commit)
    values = environment(read_json(CONFIG / "candidates" / (commit + ".json")), host())
    cfg = CONFIG / "releases"
    cfg.mkdir(mode=0o750, exist_ok=True)
    run(["chown", "root:nntmux", cfg])
    manifest = path / ".baremetal-ready.json"
    if manifest.exists():
        if read_json(cfg / (commit + ".json")) != values:
            raise DeploymentError(
                "A completed release configuration is immutable; use a new commit."
            )
        return
    if (
        run(
            ["git", "-c", f"safe.directory={path}", "-C", path, "rev-parse", "HEAD"]
        ).stdout.strip()
        != commit
    ):
        raise DeploymentError("Checkout does not match the requested immutable commit.")
    if (
        not (path / "composer.lock").is_file()
        or not (path / "package-lock.json").is_file()
    ):
        raise DeploymentError("Both committed dependency locks are required.")
    atomic_json(cfg / (commit + ".json"), values, 0o640)
    env_path = cfg / (commit + ".env")
    env_path.write_text(dotenv(values))
    os.chmod(env_path, 0o640)
    run(["chown", "root:nntmux", env_path, cfg / (commit + ".json")])
    (path / ".env").unlink(missing_ok=True)
    (path / ".env").symlink_to(env_path)
    storage = ROOT / "shared/storage"
    for directory in [
        "app/public",
        "framework/cache/data",
        "framework/sessions",
        "framework/views",
        "logs",
        "covers",
        "nzb",
        "tmp/unrar",
        "tmp/unzip",
    ]:
        (storage / directory).mkdir(parents=True, exist_ok=True)
    link_shared(path, "storage", storage)
    link_shared(path, "_install", ROOT / "shared/_install")
    for name, destination in [
        ("covers", storage / "covers"),
        ("storage", storage / "app/public"),
    ]:
        public = path / "public" / name
        if public.is_dir() and not public.is_symlink():
            shutil.copytree(public, destination, dirs_exist_ok=True)
            shutil.rmtree(public)
        public.unlink(missing_ok=True)
        public.symlink_to(destination)
    # Source caches can contain the build machine's database configuration.
    for cache in (path / "bootstrap/cache").glob("*.php"):
        cache.unlink()
    run(["chown", "-R", "nntmux:nntmux", ROOT / "shared", path])
    run(
        [
            "runuser",
            "-u",
            "nntmux",
            "--",
            "php8.5",
            "/usr/local/bin/composer",
            "install",
            "--no-dev",
            "--prefer-dist",
            "--no-interaction",
            "--no-progress",
            "--no-scripts",
            "--optimize-autoloader",
        ],
        cwd=path,
    )
    run(
        [
            "runuser",
            "-u",
            "nntmux",
            "--",
            "php8.5",
            "/usr/local/bin/composer",
            "check-platform-reqs",
            "--no-dev",
        ],
        cwd=path,
    )
    artisan(commit, "package:discover")
    run(
        ["runuser", "-u", "nntmux", "--", "npm", "ci", "--no-audit", "--no-fund"],
        cwd=path,
    )
    run(["runuser", "-u", "nntmux", "--", "npm", "run", "build"], cwd=path)
    if not (path / "public/build/manifest.json").is_file():
        raise DeploymentError("Frontend build produced no Vite manifest.")
    shutil.rmtree(path / "node_modules")
    atomic_json(manifest, {"commit": commit, "built_at": time.time()}, 0o644)
    run(["chown", "nntmux:nntmux", manifest])


def configure_elasticsearch(commit):
    if host()["search_driver"] != "elasticsearch":
        return
    values = read_json(CONFIG / "releases" / (commit + ".json"))
    # elasticsearch-users supports a prompt: never put the password in argv.
    command = [
        "runuser",
        "-u",
        "elasticsearch",
        "--",
        "/usr/share/elasticsearch/bin/elasticsearch-users",
    ]
    existing = run(command + ["list"]).stdout
    if not re.search(r"^\s*nntmux\s*:", existing, re.MULTILINE):
        result = subprocess.run(
            command + ["useradd", "nntmux", "-r", "nntmux"],
            input=values["ELASTICSEARCH_PASS"]
            + "\n"
            + values["ELASTICSEARCH_PASS"]
            + "\n",
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if result.returncode:
            raise DeploymentError("Elasticsearch application user creation failed.")


def ready(commit):
    for attempt in range(30):
        result = run(
            [
                "runuser",
                "-u",
                "nntmux",
                "--",
                "php8.5",
                "/usr/local/lib/nntmux/health.php",
                release_path(commit),
            ],
            check=False,
        )
        if result.returncode == 0:
            return
        time.sleep(2)
    raise DeploymentError("Dependencies did not become ready within 60 seconds.")


def cache(commit):
    for command in ["config:cache", "route:cache", "view:cache", "event:cache"]:
        artisan(commit, command)


def active_services():
    return [
        unit
        for unit in APP_SERVICES + data_services()
        if systemctl("is-active", unit, check=False).returncode == 0
        and unit != "nntmux-scheduler.service"
    ]


def stop_services(units):
    for unit in units:
        systemctl("stop", unit)


def restart_services(units):
    for unit in data_services():
        if unit in units:
            systemctl("start", unit)
    if any(unit in units for unit in APP_SERVICES):
        ready(current_commit())
    for unit in reversed(APP_SERVICES):
        if unit in units and unit != "nntmux-scheduler.service":
            systemctl("start", unit)


def package_versions():
    result = run(["dpkg-query", "-W", "-f=${binary:Package}\t${Version}\n"])
    return dict(
        line.split("\t", 1)
        for line in result.stdout.splitlines()
        if re.match(
            r"^(mariadb|libmariadb|galera|redis|manticore|elasticsearch|php8\.5|nginx|nodejs)",
            line,
        )
    )


def restic(*args, check=True):
    metadata = host()
    env = os.environ.copy()
    env.update(read_json(CONFIG / "backup.json"))
    env.update(
        {
            "RESTIC_REPOSITORY": metadata["backup_repository"],
            "AWS_DEFAULT_REGION": metadata["backup_region"],
        }
    )
    return run(["restic", *args], env=env, check=check)


def backup(stay_stopped=False, previous=None):
    units = active_services() if previous is None else previous
    atomic_json(
        CONFIG / "operation-state.json", {"operation": "backup", "services": units}
    )
    stopped = False
    try:
        stop_services(APP_SERVICES)
        stop_services(data_services())
        stopped = True
        commit = current_commit()
        atomic_json(
            ROOT / "shared/backup-manifest.json",
            {
                "commit": commit,
                "hostname": host()["hostname"],
                "search_driver": host()["search_driver"],
                "packages": package_versions(),
                "services": units,
                "created_at": time.time(),
            },
        )
        paths = [str(ROOT)] + [p for p in CONFIG_PATHS + DATA_PATHS if Path(p).exists()]
        result = restic(
            "backup",
            "--exclude",
            "*.before-restore-*",
            "--json",
            "--tag",
            "nntmux-baremetal",
            "--tag",
            host()["hostname"],
            *paths,
        )
        summaries = [
            json.loads(line)
            for line in result.stdout.splitlines()
            if line.startswith("{")
        ]
        summary = next((v for v in summaries if v.get("message_type") == "summary"), {})
        if not summary.get("snapshot_id"):
            raise DeploymentError(
                "Backup did not return a successful snapshot identifier."
            )
        atomic_json(
            CONFIG / "last-backup.json",
            {"snapshot": summary["snapshot_id"], "completed_at": time.time()},
        )
        restic(
            "forget",
            "--tag",
            "nntmux-baremetal," + host()["hostname"],
            "--keep-daily",
            "7",
            "--keep-weekly",
            "4",
            "--keep-monthly",
            "6",
            "--prune",
        )
        print("Backup completed: " + summary["snapshot_id"])
    finally:
        if not stay_stopped:
            # Restart also after partial shutdown or failed upload; propagate any restart failure.
            restart_services(units)
        elif not stopped:
            raise DeploymentError(
                "Partial shutdown failed; writers remain stopped for explicit recovery."
            )


def initialize(commit):
    prepare(commit)
    marker = ROOT / "shared/_install/install.lock"
    if marker.exists():
        if current_commit() != commit:
            raise DeploymentError("Use update to deploy another release.")
        if read_json(CONFIG / "releases" / (commit + ".json")) != environment(
            read_json(CONFIG / "candidates" / (commit + ".json")), host()
        ):
            raise DeploymentError(
                "A completed release configuration is immutable; use a new commit."
            )
        if (ROOT / "shared/storage/framework/down").exists():
            raise DeploymentError(
                "Maintenance is enabled; recover with rollback or restore before reinstalling."
            )
        ready(commit)
        enable_initial_services()
        print("UNCHANGED: initialized installation preserved.")
        return
    if (ROOT / "current").exists():
        raise DeploymentError(
            "Current release exists without a successful initialization marker."
        )
    configure_elasticsearch(commit)
    ready(commit)
    artisan(commit, "nntmux:deploy-init")
    cache(commit)
    activate(commit)
    enable_initial_services()
    print("Installation initialized; tmux and Usenet groups remain inactive.")


def enable_initial_services():
    restic("init") if restic("snapshots", "--json", check=False).returncode else None
    systemctl("daemon-reload")
    for unit in [
        "php8.5-fpm",
        "nntmux-horizon",
        "nntmux-scheduler.timer",
        "nntmux-backup.timer",
    ]:
        systemctl("enable", "--now", unit)


def recovery_services():
    if not (ROOT / "shared/storage/framework/down").exists():
        return active_services()
    state = CONFIG / "operation-state.json"
    if not state.exists():
        raise DeploymentError("Recovery requires the recorded previous service state.")
    units = read_json(state).get("services", [])
    if any(unit not in APP_SERVICES + data_services() for unit in units):
        raise DeploymentError("Recorded recovery service state is invalid.")
    return units


def update(commit, rollback=False, compatible=False):
    if rollback and not compatible:
        raise DeploymentError(
            "Rollback requires --schema-compatible; otherwise restore a backup."
        )
    if not (ROOT / "shared/_install/install.lock").exists():
        raise DeploymentError(
            "Update requires a successfully initialized installation."
        )
    old = current_commit()
    recovering = rollback and (ROOT / "shared/storage/framework/down").exists()
    if old == commit and not recovering:
        if not rollback:
            prepare(commit)
        print("UNCHANGED: requested release is already active.")
        return
    if not rollback:
        prepare(commit)
    elif not (release_path(commit) / ".baremetal-ready.json").is_file():
        raise DeploymentError("Rollback target is not a completed local release.")
    units = recovery_services()
    artisan(old, "down")
    stop_services(APP_SERVICES)
    backup(stay_stopped=True, previous=units)
    atomic_json(
        CONFIG / "operation-state.json",
        {
            "operation": "rollback" if rollback else "update",
            "previous": old,
            "target": commit,
            "services": units,
        },
    )
    try:
        for unit in data_services():
            systemctl("start", unit)
        ready(commit)
        if not rollback:
            artisan(commit, "migrate", "--force")
        cache(commit)
        activate(commit)
        artisan(commit, "up")
        restart_services(units)
        verify()
    except Exception:
        # A changed schema must never silently run with the previous code.
        stop_services(APP_SERVICES)
        (ROOT / "shared/storage/framework/down").write_text('{"status":503,"retry":60}')
        raise
    print("Release activated: " + commit)


def verify():
    commit = current_commit()
    ready(commit)
    failures = []
    for unit in [
        "nginx",
        "php8.5-fpm",
        "nntmux-horizon",
        "nntmux-scheduler.timer",
        "nntmux-backup.timer",
    ] + data_services():
        if systemctl("is-active", unit, check=False).returncode:
            failures.append(unit)
    response = run(
        [
            "curl",
            "--fail",
            "--silent",
            "--show-error",
            "--max-time",
            "20",
            "https://" + host()["hostname"] + "/login",
        ],
        check=False,
    )
    if response.returncode:
        failures.append("HTTPS login endpoint")
    backup_file = CONFIG / "last-backup.json"
    age = (
        round((time.time() - read_json(backup_file)["completed_at"]) / 3600, 1)
        if backup_file.exists()
        else None
    )
    scheduler = systemctl(
        "show", "nntmux-scheduler.timer", "-p", "LastTriggerUSec", "--value"
    ).stdout.strip()
    print(
        json.dumps(
            {
                "commit": commit,
                "search_driver": host()["search_driver"],
                "failed_checks": failures,
                "scheduler_last_trigger": scheduler,
                "last_backup_age_hours": age,
                "disk_free_bytes": shutil.disk_usage(ROOT).free,
                "processing_active": systemctl(
                    "is-active", "nntmux-tmux", check=False
                ).returncode
                == 0,
            }
        )
    )
    if (ROOT / "shared/storage/framework/down").exists():
        failures.append("maintenance enabled")
    if failures:
        raise DeploymentError(
            "Deployment verification failed; inspect the reported checks."
        )


def restore_packages(packages):
    if not packages or any(
        not re.fullmatch(r"[a-z][a-z0-9.+:-]+", k)
        or not re.fullmatch(r"[A-Za-z0-9.+:~_-]+", v)
        for k, v in packages.items()
    ):
        raise DeploymentError("Invalid package manifest.")
    # Install exact recorded native package versions before replacing service data.
    env = os.environ.copy()
    env.update({"DEBIAN_FRONTEND": "noninteractive", "NEEDRESTART_MODE": "l"})
    policy = Path("/usr/sbin/policy-rc.d")
    original = policy.read_bytes() if policy.exists() else None
    original_mode = policy.stat().st_mode & 0o777 if policy.exists() else None
    try:
        policy.write_text("#!/bin/sh\nexit 101\n")
        policy.chmod(0o755)
        run(
            [
                "apt-get",
                "install",
                "-y",
                "--allow-downgrades",
                "--allow-change-held-packages",
                *[k + "=" + v for k, v in packages.items()],
            ],
            env=env,
        )
    finally:
        if original is None:
            policy.unlink(missing_ok=True)
        else:
            policy.write_bytes(original)
            policy.chmod(original_mode)
    if package_versions() != packages:
        raise DeploymentError("Exact recorded runtime versions could not be restored.")


def replace_restored_path(source, target, stamp):
    if target.is_dir() and os.path.ismount(target):
        quarantine = target / (".before-restore-" + stamp)
        quarantine.mkdir(mode=0o700)
        for item in target.iterdir():
            if item != quarantine:
                item.rename(quarantine / item.name)
        for item in source.iterdir():
            shutil.move(str(item), target / item.name)
    else:
        if target.exists() or target.is_symlink():
            target.rename(target.with_name(target.name + ".before-restore-" + stamp))
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.move(str(source), target)


def restore(snapshot, move_aside=False):
    state = {"started": False}
    try:
        _restore(snapshot, move_aside, state)
    except Exception:
        if state["started"]:
            stop_services(APP_SERVICES)
        raise


def _restore(snapshot, move_aside, state):
    if not re.fullmatch(r"[a-f0-9]{8,64}", snapshot):
        raise DeploymentError(
            "Supply an explicit restic snapshot ID; latest is not accepted."
        )
    # Verify remote snapshot and versions before touching any existing data.
    with tempfile.TemporaryDirectory(prefix="nntmux-restore-", dir="/var/tmp") as stage:
        os.chmod(stage, 0o700)
        metadata = json.loads(
            restic("dump", snapshot, str(ROOT / "shared/backup-manifest.json")).stdout
        )
        if (
            metadata["hostname"] != host()["hostname"]
            or metadata["search_driver"] != host()["search_driver"]
        ):
            raise DeploymentError(
                "Snapshot hostname or search engine does not match this target."
            )
        existing = [
            Path(p)
            for p in DATA_PATHS + [str(ROOT)]
            if Path(p).exists() and any(Path(p).iterdir())
        ]
        if existing and not move_aside:
            raise DeploymentError(
                "Target contains data; explicitly use --move-data-aside to preserve it before restore."
            )
        state["started"] = True
        stop_services(["nntmux-backup.timer"] + APP_SERVICES)
        stop_services(data_services())
        atomic_json(
            CONFIG / "operation-state.json",
            {
                "operation": "restore",
                "snapshot": snapshot,
                "services": metadata["services"],
            },
        )
        restore_packages(metadata["packages"])
        restic("restore", snapshot, "--target", stage, "--verify")
        restored = Path(stage)
        stamp = str(time.time_ns())
        for name in [str(ROOT)] + CONFIG_PATHS + DATA_PATHS:
            source = restored / name.lstrip("/")
            target = Path(name)
            if not source.exists() and not source.is_symlink():
                continue
            replace_restored_path(source, target, stamp)
        # Restic restores numeric ownership from another server; normalize local service UIDs.
        for directory, owner in [
            (ROOT / "releases", "nntmux:nntmux"),
            (ROOT / "shared", "nntmux:nntmux"),
            (Path("/var/lib/mysql"), "mysql:mysql"),
            (Path("/var/lib/redis"), "redis:redis"),
            (Path("/var/lib/manticore"), "manticore:manticore"),
            (Path("/var/lib/elasticsearch"), "elasticsearch:elasticsearch"),
        ]:
            if directory.exists():
                run(["chown", "-R", owner, directory])
        for directory, owner in [
            ("/etc/redis", "root:redis"),
            ("/etc/elasticsearch", "root:elasticsearch"),
            ("/etc/manticoresearch", "root:manticore"),
        ]:
            if Path(directory).exists():
                run(["chown", "-R", owner, directory])
        run(["chown", "-R", "root:nntmux", CONFIG])
        os.chmod(CONFIG / "backup.json", 0o600)
        systemctl("daemon-reload")
        activate(metadata["commit"])
        for unit in data_services():
            systemctl("start", unit)
        ready(metadata["commit"])
        cache(metadata["commit"])
        artisan(metadata["commit"], "up")
        restart_services(metadata["services"])
        systemctl("enable", "--now", "nntmux-backup.timer")
        verify()
        atomic_json(
            CONFIG / "operation-state.json",
            {"operation": "restore", "snapshot": snapshot, "status": "verified"},
        )
        for unit in metadata["services"]:
            if unit != "nntmux-scheduler.service":
                systemctl("enable", unit)
        systemctl("enable", "--now", "nntmux-backup.timer")
        atomic_json(
            CONFIG / "last-backup.json",
            {"snapshot": snapshot, "completed_at": metadata["created_at"]},
        )
    print("Restore verified and activated.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "operation",
        choices=[
            "prepare",
            "require-release",
            "install",
            "update",
            "rollback",
            "verify",
            "backup",
            "restore",
            "processing-start",
            "processing-stop",
        ],
    )
    parser.add_argument("target", nargs="?")
    parser.add_argument("--schema-compatible", action="store_true")
    parser.add_argument("--move-data-aside", action="store_true")
    args = parser.parse_args()
    if os.geteuid() != 0:
        parser.error("Host lifecycle requires root through Ansible or systemd.")
    os.umask(0o077)
    CONFIG.mkdir(mode=0o750, exist_ok=True)
    with Path("/run/lock/nntmux-lifecycle.lock").open("a") as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as error:
            raise DeploymentError("Another lifecycle operation is running.") from error
        if args.operation in [
            "prepare",
            "require-release",
            "install",
            "update",
            "rollback",
        ]:
            release_path(args.target or "")
        if args.operation == "prepare":
            prepare(args.target)
        elif args.operation == "require-release":
            if current_commit() != args.target:
                raise DeploymentError("Use update to change an installed release.")
        elif args.operation == "install":
            initialize(args.target)
        elif args.operation in ["update", "rollback"]:
            update(args.target, args.operation == "rollback", args.schema_compatible)
        elif args.operation == "verify":
            verify()
        elif args.operation == "backup":
            backup()
        elif args.operation == "restore":
            restore(args.target or "", args.move_data_aside)
        elif args.operation == "processing-start":
            verify()
            systemctl("enable", "--now", "nntmux-tmux")
        elif args.operation == "processing-stop":
            systemctl("disable", "--now", "nntmux-tmux")


if __name__ == "__main__":
    try:
        main()
    except (DeploymentError, OSError, ValueError, KeyError) as error:
        # Do not print exception values from JSON/OS operations that may include secret content.
        print(
            (
                str(error)
                if isinstance(error, DeploymentError)
                else "Invalid or unavailable protected deployment configuration."
            ),
            file=__import__("sys").stderr,
        )
        raise SystemExit(1)
