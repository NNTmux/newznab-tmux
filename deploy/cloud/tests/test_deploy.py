"""Exercise real deployment control flow with Docker/AWS replaced at process boundaries."""
import fcntl
import configparser
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ASSETS = Path(__file__).resolve().parents[1]
OLD_IMAGE = '123456789012.dkr.ecr.eu-central-1.amazonaws.com/nntmux-test@sha256:' + '1' * 64
DIGEST = 'sha256:' + '2' * 64

MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
args = sys.argv[1:]
name = pathlib.Path(sys.argv[0]).name
with open(os.environ['TRACE_FILE'], 'a') as trace:
    trace.write(json.dumps([name] + args) + '\n')
fault = os.environ.get('FAULT', '')
if name == 'mountpoint':
    sys.exit(1 if fault == 'mount' else 0)
if name == 'systemctl' and args and args[0] == 'stop' and fault == 'daemon-stop':
    sys.exit(1)
if name == 'curl':
    if any('instance-id' in arg for arg in args): print('i-1234567890abcdef0')
    elif any('/api/token' in arg for arg in args): print('metadata-token')
    sys.exit(0)
if name == 'aws':
    if 'get-login-password' in args: print('test-token')
    elif 'get-secret-value' in args:
        if fault == 'secret': sys.exit(1)
        print('test configuration')
    elif 'create-snapshot' in args:
        if fault == 'snapshot': sys.exit(1)
        print('snap-1234567890abcdef0')
    elif 'describe-snapshots' in args:
        if '--snapshot-ids' in args:
            print(json.dumps({'Snapshots': [{'State': 'pending', 'Encrypted': True, 'Tags': []}]}))
        else:
            print(json.dumps({'Snapshots': [{'StartTime': '2026-09-%02d' % n, 'SnapshotId': 'snap-%d' % n} for n in range(1, 10)]}))
    sys.exit(0)
if name == 'docker':
    if 'login' in args: sys.stdin.read()
    if args and args[0] == 'run' and any('configure.php' in a for a in args):
        config = pathlib.Path(args[args.index('-v') + 1].split(':')[0])
        (config / 'database.env').write_text('MYSQL_PASSWORD=test\n')
        (config / 'identity.json').write_text('different' if fault == 'identity' else 'stable-identity')
    if 'ps' in args:
        print('web\ncaddy\nhorizon\nscheduler\nindexer\nmariadb\nredis\nmanticore')
    if 'artisan' in args:
        command = args[args.index('artisan') + 1]
        flag = pathlib.Path(os.environ['DATA_ROOT']) / 'storage/framework/down'
        if command == 'down': flag.write_text('maintenance')
        if command == 'up': flag.unlink(missing_ok=True)
        if command == 'migrate' and fault == 'migration': sys.exit(23)
    if 'up' in args and fault == 'readiness' and 'indexer' not in args and args[-1] == '300': sys.exit(1)
    sys.exit(0)
'''


class DeploymentTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='nntmux-deployment-test-')
        self.root = Path(self.temp.name)
        self.assets = self.root / 'assets'
        self.assets.mkdir()
        shutil.copy(ASSETS / 'deploy.sh', self.assets / 'deploy.sh')
        shutil.copytree(ASSETS / 'providers', self.assets / 'providers')
        (self.assets / 'volume.sh').write_text('#!/bin/sh\nexit 0\n')
        (self.assets / 'volume.sh').chmod(0o755)
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        for command in ['aws', 'docker', 'mountpoint', 'curl', 'chown', 'systemctl', 'sync']:
            executable = self.bin / command
            executable.write_text(MOCK)
            executable.chmod(0o755)
        self.state = self.root / 'state'
        self.state.mkdir()
        self.config = self.state / 'config.original'
        self.config.mkdir()
        (self.config / 'application.env').write_text('original-config')
        (self.config / 'database.env').write_text('original-db')
        (self.config / 'identity.json').write_text('stable-identity')
        self.data = self.root / 'data'
        (self.data / 'install').mkdir(parents=True)
        (self.data / 'storage/framework').mkdir(parents=True)
        (self.data / 'install/install.lock').write_text('initialized')
        self.original_env = f'APP_IMAGE={OLD_IMAGE}\nAPPLICATION_HOSTNAME=indexer.example.com\nDATA_ROOT={self.data}\nCONFIG_DIRECTORY={self.config}\n'
        (self.state / 'current.env').write_text(self.original_env)
        self.host = self.root / 'host.json'
        self.host.write_text(json.dumps({
            'region': 'eu-central-1', 'deployment': 'nntmux-test',
            'hostname': 'indexer.example.com', 'repository': OLD_IMAGE.split('@')[0],
            'data_volume_id': 'vol-1234567890abcdef0', 'secret_arn': 'test-secret',
        }))
        self.trace = self.root / 'trace.jsonl'
        self.lock = self.root / 'operation.lock'
        self.environment = dict(os.environ, PATH=str(self.bin) + ':' + os.environ['PATH'],
                                HOST_CONFIG=str(self.host), STATE_ROOT=str(self.state),
                                DATA_ROOT=str(self.data), LOCK_FILE=str(self.lock), TRACE_FILE=str(self.trace))

    def tearDown(self):
        self.temp.cleanup()

    def run_deploy(self, operation='release', fault='', extra=None):
        arguments = [operation] + (extra if extra is not None else [DIGEST] if operation in ('release', 'rollback') else [])
        return subprocess.run(['bash', str(self.assets / 'deploy.sh'), *arguments],
                              env=dict(self.environment, FAULT=fault), capture_output=True, text=True, timeout=20)

    def events(self):
        return [json.loads(line) for line in self.trace.read_text().splitlines()] if self.trace.exists() else []

    def test_docker_restart_cannot_precede_persistent_storage_mount(self):
        unit = configparser.ConfigParser()
        unit.read(ASSETS / 'docker-data.conf')
        self.assertIn('nntmux-data.service', unit['Unit']['Requires'].split())
        self.assertIn('nntmux-data.service', unit['Unit']['After'].split())
        mount = configparser.ConfigParser()
        mount.read(ASSETS / 'nntmux-data.service')
        self.assertIn('docker.service', mount['Unit']['Before'].split())
        self.assertIn('volume.sh mount', mount['Service']['ExecStart'])
        daemon = json.loads((ASSETS / 'docker-daemon.json').read_text())
        self.assertEqual('/srv/nntmux/docker', daemon['data-root'])
        self.assertFalse(daemon['features']['containerd-snapshotter'])
        application = configparser.ConfigParser()
        application.read(ASSETS / 'nntmux.service')
        self.assertIn('docker.service', application['Unit']['Wants'].split())
        self.assertNotIn('docker.service', application['Unit']['Requires'].split())

    def test_backup_stops_engine_before_snapshot_and_restarts_it_after_failure(self):
        result = self.run_deploy('backup', fault='snapshot')
        self.assertNotEqual(0, result.returncode)
        events = self.events()
        stopped = events.index(['systemctl', 'stop', 'docker.service', 'docker.socket', 'containerd.service'])
        flushed = events.index(['sync'])
        snapshot = next(i for i, event in enumerate(events) if 'create-snapshot' in event)
        started = events.index(['systemctl', 'start', 'docker.service'])
        services = next(i for i, event in enumerate(events) if event[0] == 'docker' and 'up' in event)
        self.assertLess(stopped, flushed)
        self.assertLess(flushed, snapshot)
        self.assertLess(snapshot, started)
        self.assertLess(started, services)

    def test_partial_engine_stop_failure_restarts_services_without_snapshot(self):
        result = self.run_deploy('backup', fault='daemon-stop')
        self.assertNotEqual(0, result.returncode)
        events = self.events()
        self.assertIn(['systemctl', 'start', 'docker.service'], events)
        self.assertTrue(any(event[0] == 'docker' and 'up' in event for event in events))
        self.assertFalse(any('create-snapshot' in event for event in events))

    def test_failed_migration_preserves_release_and_leaves_maintenance_enabled(self):
        result = self.run_deploy(fault='migration')
        self.assertNotEqual(0, result.returncode, result.stderr)
        self.assertIn('Migration failed', result.stderr)
        self.assertTrue((self.data / 'storage/framework/down').exists())
        self.assertEqual(self.original_env, (self.state / 'current.env').read_text())
        self.assertFalse(any('artisan' in event and 'up' in event for event in self.events()))
        self.assertFalse(any('migrate:fresh' in event or 'migrate:rollback' in event for event in self.events()))

    def test_missing_secret_fails_before_maintenance_or_service_stop(self):
        result = self.run_deploy(fault='secret')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('retrieve application configuration', result.stderr)
        self.assertFalse(any('down' in event or 'stop' in event for event in self.events()))
        self.assertEqual(self.original_env, (self.state / 'current.env').read_text())

    def test_missing_mount_fails_before_aws_or_docker_calls(self):
        result = self.run_deploy(fault='mount')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('not mounted', result.stderr)
        self.assertFalse(any(event[0] in ('aws', 'docker') for event in self.events()))

    def test_concurrent_operation_is_rejected(self):
        with self.lock.open('w') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            result = self.run_deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Another deployment operation', result.stderr)
        self.assertEqual([], self.events())

    def test_snapshot_failure_restarts_services(self):
        result = self.run_deploy('backup', fault='snapshot')
        self.assertNotEqual(0, result.returncode)
        events = self.events()
        stop_index = next(i for i, event in enumerate(events) if event[0] == 'docker' and 'stop' in event)
        self.assertTrue(any(event[0] == 'docker' and 'up' in event and 'indexer' in event for event in events[stop_index + 1:]))

    def test_identity_change_is_rejected_before_stopping_writers(self):
        result = self.run_deploy(fault='identity')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('must remain unchanged', result.stderr)
        self.assertFalse(any('stop' in event for event in self.events()))

    def test_successful_release_retains_previous_digest_and_exits_maintenance(self):
        result = self.run_deploy()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(self.original_env, (self.state / 'previous.env').read_text())
        self.assertIn(DIGEST, (self.state / 'current.env').read_text())
        self.assertFalse((self.data / 'storage/framework/down').exists())
        self.assertEqual('original-config', (self.data / 'recovery/config/application.env').read_text())

    def test_daily_backup_only_prunes_completed_backups_beyond_seven(self):
        result = self.run_deploy('backup')
        self.assertEqual(0, result.returncode, result.stderr)
        deleted = [event[-1] for event in self.events() if 'delete-snapshot' in event]
        self.assertEqual(['snap-2', 'snap-1'], deleted)

    def test_rollback_requires_explicit_schema_compatibility(self):
        result = self.run_deploy('rollback')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('--schema-compatible', result.stderr)
        self.assertFalse(any('stop' in event for event in self.events()))

    def test_incomplete_restore_snapshot_is_rejected_without_stopping_services(self):
        result = self.run_deploy('restore', extra=['snap-1234567890abcdef0'])
        self.assertNotEqual(0, result.returncode)
        self.assertIn('completed, encrypted backup', result.stderr)
        self.assertFalse(any('stop' in event for event in self.events()))


if __name__ == '__main__':
    unittest.main()
