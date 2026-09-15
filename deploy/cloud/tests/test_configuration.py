"""Validate secret parsing using the production PHP configuration validator."""
import base64
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

VALIDATOR = Path(__file__).resolve().parents[1] / 'configure.php'


class ConfigurationTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='nntmux-config-test-')
        self.directory = Path(self.temp.name)
        self.values = {
            'APP_KEY': 'base64:' + base64.b64encode(b'k' * 32).decode(),
            'APP_ENV': 'production', 'APP_DEBUG': 'false', 'APP_URL': 'https://indexer.example.com',
            'DB_CONNECTION': 'mariadb', 'DB_HOST': 'mariadb', 'DB_PORT': '3306',
            'DB_DATABASE': 'nntmux', 'DB_USERNAME': 'nntmux', 'DB_PASSWORD': 'a$literal#password',
            'DB_ROOTPASSWORD': 'separate-root-password', 'REDIS_HOST': 'redis',
            'REDIS_PORT': '6379', 'REDIS_PASSWORD': 'null', 'CACHE_STORE': 'redis',
            'SESSION_DRIVER': 'redis', 'QUEUE_CONNECTION': 'redis', 'SEARCH_DRIVER': 'manticore',
            'MANTICORESEARCH_HOST': 'manticore', 'MANTICORESEARCH_PORT': '9308',
            'TRUSTED_PROXIES': '172.30.42.0/24', 'COVERS_PATH': '/app/storage/covers',
            'PATH_TO_NZBS': '/app/storage/nzb', 'TEMP_UNRAR_PATH': '/app/storage/tmp/unrar',
            'TEMP_UNZIP_PATH': '/app/storage/tmp/unzip', 'ADMIN_USER': 'admin',
            'ADMIN_PASS': 'a-long-admin-password', 'ADMIN_EMAIL': 'admin@example.com',
            'NNTP_SERVER': 'news.example.com', 'NNTP_USERNAME': 'test-user',
            'NNTP_PASSWORD': 'secret-nntp-password', 'NNTP_PORT': '563',
        }

    def tearDown(self):
        self.temp.cleanup()

    def validate(self):
        contents = ''.join(f"{key}='{value}'\n" for key, value in self.values.items())
        (self.directory / 'application.env').write_text(contents)
        return subprocess.run(['php', str(VALIDATOR), 'indexer.example.com', str(self.directory)],
                              capture_output=True, text=True, timeout=10)

    def test_password_punctuation_is_preserved_without_shell_expansion(self):
        result = self.validate()
        self.assertEqual(0, result.returncode, result.stderr)
        database = (self.directory / 'database.env').read_text()
        self.assertIn('MYSQL_PASSWORD=a$literal#password\n', database)
        self.assertIn('MYSQL_ROOT_PASSWORD=separate-root-password\n', database)
        self.assertEqual(0o600, (self.directory / 'database.env').stat().st_mode & 0o777)
        self.assertEqual('', result.stdout)
        identity = json.loads((self.directory / 'identity.json').read_text())
        self.assertEqual(self.values['APP_KEY'], identity['APP_KEY'])

    def test_reordered_secret_fields_preserve_identity_comparison(self):
        self.assertEqual(0, self.validate().returncode)
        original_identity = (self.directory / 'identity.json').read_bytes()
        self.values = dict(reversed(list(self.values.items())))
        self.assertEqual(0, self.validate().returncode)
        self.assertEqual(original_identity, (self.directory / 'identity.json').read_bytes())

    def test_missing_nntp_credentials_are_rejected_without_disclosing_values(self):
        del self.values['NNTP_PASSWORD']
        result = self.validate()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('NNTP_PASSWORD', result.stderr)
        self.assertNotIn(self.values['DB_PASSWORD'], result.stderr)
        self.assertFalse((self.directory / 'database.env').exists())

    def test_multiline_database_password_is_rejected(self):
        self.values['DB_PASSWORD'] = 'first line\nsecond line'
        result = self.validate()
        self.assertNotEqual(0, result.returncode)
        self.assertNotIn('first line', result.stderr)
        self.assertFalse((self.directory / 'database.env').exists())

    def test_invalid_key_is_rejected(self):
        self.values['APP_KEY'] = 'base64:invalid'
        result = self.validate()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Invalid application key', result.stderr)
        self.assertFalse((self.directory / 'identity.json').exists())

    def test_hostname_must_match_https_application_url(self):
        self.values['APP_URL'] = 'http://different.example.com'
        result = self.validate()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('APP_URL', result.stderr)

    def test_weak_administrator_password_is_rejected(self):
        self.values['ADMIN_PASS'] = 'admin'
        self.assertNotEqual(0, self.validate().returncode)

    def test_remote_search_nodes_are_rejected(self):
        self.values['MANTICORESEARCH_HOSTS'] = 'http://external.example.com:9308'
        result = self.validate()
        self.assertNotEqual(0, result.returncode)
        self.assertIn('local Manticore', result.stderr)


if __name__ == '__main__':
    unittest.main()
