"""Test shared deployment transactions with real Bash/Vault parsing and mocked processes."""
import fcntl
import json
import os
from pathlib import Path
import shutil
import unittest

import test_deploy as base

DIGEST = base.DIGEST

PORTABLE_MOCK = base.MOCK.replace("if name == 'curl':", """if name == 'findmnt':
    stage = pathlib.Path(os.environ['RUNTIME_ROOT']) / 'stage-path'
    if stage.exists(): print(stage.read_text())
    sys.exit(0)
if name == 'restic':
    if args[:2] == ['cat', 'config'] and fault == 'repository': sys.exit(1)
    if args[0] == 'init' and fault == 'repository': sys.exit(1)
    if args[0] == 'backup' and fault == 'snapshot': sys.exit(23)
    if args[0] == 'snapshots':
        print(json.dumps([{'hostname': 'wrong' if fault == 'restore-owner' else 'nntmux-test', 'tags': ['nntmux-test', 'daily']}]))
    if args[0] == 'restore':
        target = pathlib.Path(args[args.index('--target') + 1])
        if fault != 'restore-metadata':
            shutil.copytree(os.environ['RESTORE_FIXTURE'], target, dirs_exist_ok=True)
    sys.exit(0)
if name == 'curl' and any('vault.example.com' in arg for arg in args):
    if fault == 'secret': sys.exit(1)
    print(pathlib.Path(os.environ['VAULT_RESPONSE']).read_text())
    sys.exit(0)
if name == 'curl':""").replace('import json, os, pathlib, sys', 'import json, os, pathlib, sys, shutil')


class PortableDeploymentTest(unittest.TestCase):
    run_deploy = base.DeploymentTest.run_deploy
    events = base.DeploymentTest.events

    def setUp(self):
        base.DeploymentTest.setUp(self)
        previous_state = self.state
        self.state = self.data / 'host-state'
        shutil.move(previous_state, self.state)
        self.config = self.state / 'config.original'
        image = 'ghcr.io/example/nntmux@sha256:' + '1' * 64
        self.original_env = f'APP_IMAGE={image}\nAPPLICATION_HOSTNAME=indexer.example.com\nDATA_ROOT={self.data}\nCONFIG_DIRECTORY={self.config}\n'
        (self.state / 'current.env').write_text(self.original_env)
        host = json.loads(self.host.read_text())
        host.update(provider='hetzner', repository=image.split('@')[0],
                    state_root=str(self.state), data_volume_id='1234',
                    vault_address='https://vault.example.com', vault_secret_path='secret/data/nntmux/test')
        self.host.write_text(json.dumps(host))
        self.credentials = dict(restic_repository='s3:https://backup.example.com/nntmux/test',
                                restic_password='test-repository-password',
                                aws_access_key_id='test-backup-user', aws_secret_access_key='test-backup-key')
        (self.config / 'credentials.json').write_text(json.dumps(self.credentials))
        self.vault = self.root / 'vault.json'
        self.vault.write_text(json.dumps({'data': {'data': dict(self.credentials, application_env='test configuration')}}))
        self.token = self.root / 'vault-token'
        self.token.write_text('hvs.test-token')
        self.token.chmod(0o600)
        self.fixture = self.root / 'restore-fixture'
        (self.fixture / 'install').mkdir(parents=True)
        (self.fixture / 'install/install.lock').write_text('initialized')
        (self.fixture / 'host-state').mkdir()
        (self.fixture / 'storage/framework').mkdir(parents=True)
        shutil.copytree(self.config, self.fixture / 'recovery/config')
        (self.fixture / 'recovery/release.env').write_text(self.original_env)
        # Volume mounting is a separate integration boundary; target filesystem lives in /tmp.
        (self.assets / 'volume.sh').write_text("""#!/usr/bin/env python3
import json, os, pathlib, sys
root = pathlib.Path(os.environ['RUNTIME_ROOT'])
data = pathlib.Path(os.environ['DATA_ROOT'])
operation = sys.argv[1]
volume = json.loads(pathlib.Path(os.environ['HOST_CONFIG']).read_text())['data_volume_id']
if operation == 'mount' and volume == '5678':
    if str(data) == os.environ['ACTIVE_DATA_ROOT']:
        data.rename(root / 'original-data')
        pathlib.Path((root / 'stage-path').read_text()).rename(data)
    else:
        (root / 'stage-path').write_text(str(data))
        if os.environ.get('FAULT') == 'nonempty-target': (data / 'keep-existing-data').write_text('keep')
data.mkdir(exist_ok=True)
""")
        for command in ['curl', 'restic', 'docker', 'mountpoint', 'findmnt', 'chown', 'systemctl', 'sync']:
            executable = self.bin / command
            executable.write_text(PORTABLE_MOCK)
            executable.chmod(0o755)
        self.environment.update(STATE_ROOT=str(self.state), VAULT_TOKEN_FILE=str(self.token),
                                VAULT_RESPONSE=str(self.vault), RUNTIME_ROOT=str(self.root),
                                RESTORE_ROOT=str(self.root), RESTORE_FIXTURE=str(self.fixture),
                                ACTIVE_DATA_ROOT=str(self.data))

    def tearDown(self):
        self.temp.cleanup()

    def test_both_providers_release_without_aws_cli(self):
        for provider in ['hetzner', 'ovh']:
            with self.subTest(provider=provider):
                (self.state / 'current.env').write_text(self.original_env)
                host = json.loads(self.host.read_text())
                host['provider'] = provider
                self.host.write_text(json.dumps(host))
                result = self.run_deploy()
                self.assertEqual(0, result.returncode, result.stderr)
                self.assertIn(DIGEST, (self.state / 'current.env').read_text())
                self.assertEqual(self.original_env, (self.state / 'previous.env').read_text())
                self.assertFalse((self.data / 'storage/framework/down').exists())
        self.assertFalse(any(event[0] == 'aws' for event in self.events()))

    def test_failed_migration_preserves_state_and_stopped_writers(self):
        result = self.run_deploy(fault='migration')
        self.assertNotEqual(0, result.returncode)
        self.assertTrue((self.data / 'storage/framework/down').exists())
        self.assertEqual(self.original_env, (self.state / 'current.env').read_text())
        self.assertFalse(any('migrate:rollback' in event for event in self.events()))

    def test_failed_readiness_after_migration_records_new_image_and_keeps_maintenance(self):
        result = self.run_deploy(fault='readiness')
        self.assertNotEqual(0, result.returncode)
        self.assertIn(DIGEST, (self.state / 'current.env').read_text())
        self.assertTrue((self.data / 'storage/framework/down').exists())
        self.assertTrue(any('stop' in event and 'indexer' in event for event in self.events()))

    def test_missing_or_insecure_token_fails_before_docker(self):
        for mode in ['missing', 'insecure', 'multiline']:
            with self.subTest(mode=mode):
                if mode == 'missing':
                    self.token.unlink()
                else:
                    self.token.write_text('hvs.test-token' if mode == 'insecure' else 'hvs.token\nInjected: header')
                    self.token.chmod(0o644 if mode == 'insecure' else 0o600)
                result = self.run_deploy()
                self.assertNotEqual(0, result.returncode)
                self.assertFalse(any(event[0] == 'docker' for event in self.events()))

    def test_failed_vault_request_preserves_running_services(self):
        result = self.run_deploy(fault='secret')
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any('stop' in event or 'down' in event for event in self.events()))
        self.assertNotIn('hvs.test-token', self.trace.read_text())
        self.assertFalse(list(self.root.glob('nntmux-vault.*')))

    def test_missing_backup_credentials_fail_before_downtime(self):
        contents = json.loads(self.vault.read_text())
        del contents['data']['data']['restic_password']
        self.vault.write_text(json.dumps(contents))
        result = self.run_deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any('stop' in event or 'down' in event for event in self.events()))

    def test_snapshot_failure_restarts_services_without_retention_changes(self):
        result = self.run_deploy('backup', fault='snapshot')
        self.assertNotEqual(0, result.returncode)
        events = self.events()
        backup = next(i for i, event in enumerate(events) if event[:2] == ['restic', 'backup'])
        self.assertTrue(any(event[0] == 'docker' and 'up' in event and 'indexer' in event for event in events[backup:]))
        self.assertFalse(any(event[:2] in (['restic', 'forget'], ['restic', 'prune']) for event in events))

    def test_daily_retention_keeps_seven_and_prunes_after_restart(self):
        result = self.run_deploy('backup')
        self.assertEqual(0, result.returncode, result.stderr)
        events = self.events()
        forget = next(event for event in events if event[:2] == ['restic', 'forget'])
        self.assertEqual('7', forget[forget.index('--keep-last') + 1])
        self.assertEqual('nntmux-test,daily', forget[forget.index('--tag') + 1])
        prune = next(i for i, event in enumerate(events) if event[:2] == ['restic', 'prune'])
        self.assertTrue(any(event[0] == 'docker' and 'up' in event for event in events[:prune]))

    def test_missing_mount_and_concurrent_operations_fail_before_external_calls(self):
        result = self.run_deploy(fault='mount')
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any(event[0] in ('docker', 'curl', 'restic') for event in self.events()))
        self.trace.unlink()
        with self.lock.open('w') as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            result = self.run_deploy()
        self.assertNotEqual(0, result.returncode)
        self.assertEqual([], self.events())

    def test_restore_rejects_other_deployment_before_stopping_services(self):
        result = self.run_deploy('restore', fault='restore-owner', extra=['a' * 64, '5678'])
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any('stop' in event for event in self.events()))

    def test_staged_restore_preserves_active_volume_and_configuration(self):
        original = self.host.read_text()
        result = self.run_deploy('restore', extra=['a' * 64, '5678'])
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(original, self.host.read_text())
        self.assertEqual(self.original_env, (self.state / 'current.env').read_text())
        self.assertTrue((next(self.root.glob('nntmux-restore.*')) / 'install/install.lock').exists())
        self.assertFalse(any('stop' in event for event in self.events()))

    def test_restore_missing_metadata_never_switches_volume(self):
        result = self.run_deploy('restore', fault='restore-metadata', extra=['a' * 64, '5678', '--activate'])
        self.assertNotEqual(0, result.returncode)
        self.assertEqual('1234', json.loads(self.host.read_text())['data_volume_id'])
        self.assertFalse(any('stop' in event for event in self.events()))

    def test_activation_retains_original_data_and_selects_recovered_volume(self):
        result = self.run_deploy('restore', extra=['a' * 64, '5678', '--activate'])
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual('5678', json.loads(self.host.read_text())['data_volume_id'])
        self.assertTrue((self.root / 'original-data/install/install.lock').exists())
        self.assertTrue((self.data / 'install/install.lock').exists())
        self.assertFalse((self.data / 'storage/framework/down').exists())

    def test_inspected_staged_restore_can_activate_without_rewriting_files(self):
        self.assertEqual(0, self.run_deploy('restore', extra=['a' * 64, '5678']).returncode)
        result = self.run_deploy('restore', extra=['a' * 64, '5678', '--activate'])
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(1, sum(event[:2] == ['restic', 'restore'] for event in self.events()))
        self.assertEqual('5678', json.loads(self.host.read_text())['data_volume_id'])
        self.assertTrue((self.root / 'original-data/install/install.lock').exists())

    def test_nonempty_recovery_volume_is_never_overwritten(self):
        result = self.run_deploy('restore', fault='nonempty-target', extra=['a' * 64, '5678'])
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any(event[:2] == ['restic', 'restore'] for event in self.events()))
        self.assertEqual('keep', (next(self.root.glob('nntmux-restore.*')) / 'keep-existing-data').read_text())

    def test_restore_refuses_the_active_volume(self):
        result = self.run_deploy('restore', extra=['a' * 64, '1234'])
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any(event[0] == 'restic' for event in self.events()))


if __name__ == '__main__':
    unittest.main()
