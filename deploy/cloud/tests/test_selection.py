"""Check provider selection, isolated state roots, SSH quoting, and SSM failure reporting."""
import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile
import unittest

SELECTOR = Path(__file__).resolve().parents[2] / 'select.sh'
MOCK = r'''#!/usr/bin/env python3
import json, os, pathlib, sys
name = pathlib.Path(sys.argv[0]).name
args = sys.argv[1:]
with open(os.environ['TRACE'], 'a') as trace: trace.write(json.dumps([name] + args) + '\n')
if name == 'terraform' and 'output' in args:
    print(json.dumps({'host_config': {'value': {'provider': os.environ['STATE_PROVIDER'], 'region': 'eu-central-1'}}, 'instance_id': {'value': 'i-test'}, 'static_ip': {'value': '203.0.113.20'}, 'ssh_user': {'value': 'ubuntu'}}))
if name == 'aws':
    if 'send-command' in args: print('test-command')
    if 'get-command-invocation' in args:
        print(json.dumps({'Status': os.environ.get('SSM_STATUS', 'Success'), 'StandardOutputContent': 'operation output', 'StandardErrorContent': 'operation failure'}))
'''


class SelectionTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='nntmux-selector-')
        self.root = Path(self.temp.name)
        self.trace = self.root / 'trace'
        for name in ['terraform', 'ssh', 'aws']:
            executable = self.root / name
            executable.write_text(MOCK)
            executable.chmod(0o755)

    def tearDown(self):
        self.temp.cleanup()

    def run_select(self, provider, operation, *arguments, state=None, status='Success'):
        return subprocess.run(['bash', str(SELECTOR), provider, operation, *arguments],
                              env=dict(os.environ, PATH=str(self.root) + ':' + os.environ['PATH'],
                                       TRACE=str(self.trace), STATE_PROVIDER=state or provider, SSM_STATUS=status),
                              capture_output=True, text=True, timeout=15)

    def events(self):
        return [json.loads(line) for line in self.trace.read_text().splitlines()] if self.trace.exists() else []

    def test_each_provider_selects_its_own_terraform_root(self):
        for provider in ['aws', 'hetzner', 'ovh']:
            result = self.run_select(provider, 'terraform', 'validate')
            self.assertEqual(0, result.returncode)
            self.assertTrue(self.events()[-1][1].endswith('/infra/terraform/' + provider))
            self.assertEqual('validate', self.events()[-1][-1])

    def test_unknown_provider_or_operation_never_runs_tools(self):
        self.assertNotEqual(0, self.run_select('other', 'release').returncode)
        self.assertNotEqual(0, self.run_select('ovh', 'other').returncode)
        self.assertEqual([], self.events())

    def test_mismatched_state_is_rejected_before_remote_commands(self):
        result = self.run_select('hetzner', 'backup', state='aws')
        self.assertNotEqual(0, result.returncode)
        self.assertFalse(any(event[0] in ('aws', 'ssh') for event in self.events()))

    def test_ssh_preserves_arguments_and_requires_known_host_keys(self):
        payload = 'sha256:invalid; touch /tmp/should-not-execute $(id)'
        result = self.run_select('ovh', 'release', payload)
        self.assertEqual(0, result.returncode, result.stderr)
        event = next(event for event in self.events() if event[0] == 'ssh')
        self.assertIn('StrictHostKeyChecking=yes', event)
        remote = shlex.split(event[-1])
        self.assertEqual(['sudo', '-n', 'bash', '-c'], remote[:4])
        self.assertEqual(['/opt/nntmux/deploy.sh', 'release', payload], shlex.split(remote[4]))

    def test_aws_uses_ssm_and_reports_remote_failure(self):
        result = self.run_select('aws', 'backup', status='Failed')
        self.assertNotEqual(0, result.returncode)
        self.assertIn('operation failure', result.stderr)
        self.assertFalse(any(event[0] == 'ssh' for event in self.events()))
        send = next(event for event in self.events() if 'send-command' in event)
        parameters = json.loads(send[send.index('--parameters') + 1])
        self.assertEqual('/opt/nntmux/deploy.sh backup ', parameters['commands'][0])


if __name__ == '__main__':
    unittest.main()
