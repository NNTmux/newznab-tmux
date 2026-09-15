"""Lifecycle failure-mode tests. No root privileges or live services are used."""

import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import Mock, patch

DIRECTORY = Path(__file__).resolve().parents[1]


def module(name):
    spec = importlib.util.spec_from_file_location(name, DIRECTORY / (name + ".py"))
    loaded = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(loaded)
    return loaded


host = module("host")
deploy = module("deploy")
SHA = "a" * 40
OTHER = "b" * 40


class LifecycleTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / "srv/nntmux"
        self.config = Path(self.temp.name) / "etc/nntmux"
        self.root.mkdir(parents=True)
        self.config.mkdir(parents=True)
        self.addCleanup(patch.stopall)
        patch.object(host, "ROOT", self.root).start()
        patch.object(host, "CONFIG", self.config).start()
        patch.object(host, "CONFIG_PATHS", [str(self.config)]).start()
        patch.object(host, "DATA_PATHS", [str(Path(self.temp.name) / "data")]).start()
        factory = tempfile.TemporaryDirectory
        self.stage_factory = lambda **kwargs: factory(dir=self.temp.name)
        self.metadata = {
            "hostname": "indexer.example.com",
            "search_driver": "manticore",
            "backup_repository": "s3:https://s3.example.com/bucket",
            "backup_region": "us-east-1",
        }
        (self.config / "host.json").write_text(json.dumps(self.metadata))

    def candidate(self):
        return {
            "settings": {"MAIL_HOST": "smtp.example.com", "DB_HOST": "external"},
            "secrets": {
                "APP_KEY": "key",
                "DB_DATABASE": "nntmux",
                "DB_USERNAME": "nntmux",
                "DB_PASSWORD": "password",
                "REDIS_PASSWORD": "redis",
                "ADMIN_USER": "admin",
                "ADMIN_EMAIL": "admin@example.com",
                "ADMIN_PASS": "secret",
                "AWS_SECRET_ACCESS_KEY": "backup-secret",
                "RESTIC_PASSWORD": "restic-secret",
            },
        }

    def initialized(self):
        (self.root / "shared/_install").mkdir(parents=True)
        (self.root / "shared/_install/install.lock").touch()
        (self.root / "shared/storage/framework").mkdir(parents=True)
        (self.root / "releases" / SHA).mkdir(parents=True)
        (self.root / "current").symlink_to(self.root / "releases" / SHA)

    def test_managed_release_cannot_escape_release_root(self):
        (self.root / "current").symlink_to("/tmp")
        with self.assertRaises(host.DeploymentError):
            host.current_commit()
        for commit in ["", "../outside", "--option", SHA + "/file"]:
            with self.assertRaises(host.DeploymentError):
                host.release_path(commit)

    def test_native_endpoints_override_application_settings_and_backup_keys_are_excluded(
        self,
    ):
        values = host.environment(self.candidate(), self.metadata)
        self.assertEqual(values["DB_HOST"], "127.0.0.1")
        self.assertEqual(values["SESSION_SECURE_COOKIE"], "true")
        self.assertNotIn("RESTIC_PASSWORD", values)
        self.assertNotIn("AWS_SECRET_ACCESS_KEY", values)

    def test_identity_change_is_refused(self):
        values = host.environment(self.candidate(), self.metadata)
        (self.config / "application.json").write_text(json.dumps(values))
        changed = self.candidate()
        changed["secrets"]["APP_KEY"] = "replacement"
        with self.assertRaises(host.DeploymentError):
            host.environment(changed, self.metadata)

    def test_dotenv_rejects_multiline_and_escapes_interpolation(self):
        self.assertIn("\\$", host.dotenv({"SECRET": '$value"\\tail'}))
        with self.assertRaises(host.DeploymentError):
            host.dotenv({"SECRET": "one\ntwo"})

    def test_source_install_lock_is_not_copied_into_shared_state(self):
        release = self.root / "releases" / SHA
        (release / "_install").mkdir(parents=True)
        (release / "_install/install.lock").write_text("untrusted source marker")
        destination = self.root / "shared/_install"
        destination.mkdir(parents=True)
        host.link_shared(release, "_install", destination)
        self.assertFalse((destination / "install.lock").exists())
        self.assertTrue((release / "_install").is_symlink())

    def test_backup_failure_restarts_exact_previous_services_and_records_no_success(
        self,
    ):
        self.initialized()
        with patch.object(
            host, "active_services", return_value=["mariadb", "nginx"]
        ), patch.object(host, "stop_services") as stop, patch.object(
            host, "restart_services"
        ) as restart, patch.object(
            host, "package_versions", return_value={}
        ), patch.object(
            host, "restic", side_effect=host.DeploymentError("upload failed")
        ):
            with self.assertRaises(host.DeploymentError):
                host.backup()
        self.assertEqual(stop.call_count, 2)
        restart.assert_called_once_with(["mariadb", "nginx"])
        self.assertFalse((self.config / "last-backup.json").exists())

    def test_partial_backup_shutdown_restarts_previous_services(self):
        with patch.object(
            host, "active_services", return_value=["mariadb", "nginx"]
        ), patch.object(
            host, "stop_services", side_effect=host.DeploymentError("shutdown failed")
        ), patch.object(
            host, "restart_services"
        ) as restart:
            with self.assertRaises(host.DeploymentError):
                host.backup()
        restart.assert_called_once_with(["mariadb", "nginx"])

    def test_successful_backup_records_snapshot_and_restores_service_state(self):
        self.initialized()
        response = subprocess.CompletedProcess(
            [], 0, '{"message_type":"summary","snapshot_id":"1234abcd"}\n', ""
        )
        with patch.object(
            host, "active_services", return_value=["mariadb", "nginx"]
        ), patch.object(host, "stop_services"), patch.object(
            host, "restart_services"
        ) as restart, patch.object(
            host, "package_versions", return_value={"mariadb-server": "1:11.4.8"}
        ), patch.object(
            host, "restic", return_value=response
        ) as restic:
            host.backup()
        self.assertEqual(
            json.loads((self.config / "last-backup.json").read_text())["snapshot"],
            "1234abcd",
        )
        restart.assert_called_once_with(["mariadb", "nginx"])
        self.assertEqual(restic.call_args_list[1].args[0], "forget")

    def test_backup_restart_failure_remains_a_failure(self):
        self.initialized()
        response = subprocess.CompletedProcess(
            [], 0, '{"message_type":"summary","snapshot_id":"1234abcd"}\n', ""
        )
        with patch.object(
            host, "active_services", return_value=["nginx"]
        ), patch.object(host, "stop_services"), patch.object(
            host, "package_versions", return_value={}
        ), patch.object(
            host, "restic", return_value=response
        ), patch.object(
            host, "restart_services", side_effect=host.DeploymentError("restart failed")
        ):
            with self.assertRaises(host.DeploymentError):
                host.backup()

    def test_migration_failure_keeps_writers_stopped_without_switching_release(self):
        self.initialized()

        def artisan(commit, command, *args):
            if command == "migrate":
                raise host.DeploymentError("migration failed")

        with patch.object(host, "prepare"), patch.object(
            host, "active_services", return_value=["nginx"]
        ), patch.object(host, "artisan", side_effect=artisan), patch.object(
            host, "stop_services"
        ) as stop, patch.object(
            host, "backup"
        ), patch.object(
            host, "systemctl"
        ), patch.object(
            host, "ready"
        ), patch.object(
            host, "activate"
        ) as activate:
            with self.assertRaises(host.DeploymentError):
                host.update(OTHER)
        activate.assert_not_called()
        self.assertEqual(stop.call_count, 2)
        self.assertTrue((self.root / "shared/storage/framework/down").exists())
        self.assertEqual(host.current_commit(), SHA)

    def test_recovery_uses_saved_services_when_failed_update_left_writers_stopped(self):
        self.initialized()
        (self.root / "shared/storage/framework/down").write_text("{}")
        saved = ["mariadb", "nginx", "nntmux-horizon"]
        (self.config / "operation-state.json").write_text(
            json.dumps({"services": saved})
        )
        with patch.object(host, "active_services", return_value=["mariadb"]):
            self.assertEqual(host.recovery_services(), saved)

    def test_schema_compatible_rollback_can_recover_same_current_release(self):
        self.initialized()
        (self.root / "releases" / SHA / ".baremetal-ready.json").write_text("{}")
        (self.root / "shared/storage/framework/down").write_text("{}")
        saved = ["mariadb", "nginx", "nntmux-horizon"]
        (self.config / "operation-state.json").write_text(
            json.dumps({"services": saved})
        )
        with patch.object(host, "artisan") as artisan, patch.object(
            host, "stop_services"
        ), patch.object(host, "backup"), patch.object(host, "systemctl"), patch.object(
            host, "ready"
        ), patch.object(
            host, "cache"
        ), patch.object(
            host, "activate"
        ) as activate, patch.object(
            host, "restart_services"
        ) as restart, patch.object(
            host, "verify"
        ):
            host.update(SHA, rollback=True, compatible=True)
        activate.assert_called_once_with(SHA)
        restart.assert_called_once_with(saved)
        self.assertTrue(any(call.args[1] == "up" for call in artisan.call_args_list))
        self.assertFalse(
            any(call.args[1] == "migrate" for call in artisan.call_args_list)
        )

    def test_rollback_requires_explicit_schema_compatibility(self):
        with patch.object(host, "backup") as backup:
            with self.assertRaises(host.DeploymentError):
                host.update(SHA, rollback=True)
        backup.assert_not_called()

    def test_restore_rejects_latest_and_wrong_hostname_before_shutdown(self):
        with patch.object(host, "stop_services") as stop:
            with self.assertRaises(host.DeploymentError):
                host.restore("latest")
            metadata = {"hostname": "other.example.com", "search_driver": "manticore"}
            with patch.object(
                host.tempfile, "TemporaryDirectory", side_effect=self.stage_factory
            ), patch.object(
                host, "restic", return_value=Mock(stdout=json.dumps(metadata))
            ):
                with self.assertRaises(host.DeploymentError):
                    host.restore("1234abcd")
        stop.assert_not_called()

    def test_restore_existing_data_requires_explicit_move_aside_before_shutdown(self):
        metadata = self.metadata | {"services": [], "packages": {}}
        (self.root / "keep").touch()
        with patch.object(
            host.tempfile, "TemporaryDirectory", side_effect=self.stage_factory
        ), patch.object(
            host, "restic", return_value=Mock(stdout=json.dumps(metadata))
        ), patch.object(
            host, "stop_services"
        ) as stop:
            with self.assertRaisesRegex(host.DeploymentError, "Target contains data"):
                host.restore("1234abcd")
        stop.assert_not_called()
        self.assertTrue((self.root / "keep").exists())

    def test_restore_version_failure_leaves_original_data_and_stops_writers(self):
        metadata = self.metadata | {
            "commit": SHA,
            "services": ["nginx"],
            "packages": {"mariadb-server": "1:11.4.8"},
        }
        (self.root / "keep").touch()
        with patch.object(
            host.tempfile, "TemporaryDirectory", side_effect=self.stage_factory
        ), patch.object(
            host, "restic", return_value=Mock(stdout=json.dumps(metadata))
        ), patch.object(
            host, "stop_services"
        ) as stop, patch.object(
            host,
            "restore_packages",
            side_effect=host.DeploymentError("version unavailable"),
        ):
            with self.assertRaises(host.DeploymentError):
                host.restore("1234abcd", move_aside=True)
        self.assertTrue((self.root / "keep").exists())
        self.assertEqual(stop.call_count, 3)

    def test_restore_package_failure_occurs_before_files_are_replaced(self):
        metadata = self.metadata | {
            "commit": SHA,
            "services": ["nginx"],
            "packages": {"mariadb-server": "1:11.4.8"},
        }
        with patch.object(
            host.tempfile, "TemporaryDirectory", side_effect=self.stage_factory
        ), patch.object(
            host, "restic", return_value=Mock(stdout=json.dumps(metadata))
        ), patch.object(
            host, "stop_services"
        ), patch.object(
            host,
            "restore_packages",
            side_effect=host.DeploymentError("version unavailable"),
        ), patch.object(
            host, "replace_restored_path"
        ) as replace:
            with self.assertRaises(host.DeploymentError):
                host.restore("1234abcd", move_aside=True)
        replace.assert_not_called()

    def test_mounted_data_directory_preserves_contents_inside_mount(self):
        source = Path(self.temp.name) / "snapshot"
        target = Path(self.temp.name) / "mounted"
        source.mkdir()
        target.mkdir()
        (source / "restored").write_text("restored data")
        (target / "original").write_text("keep original")
        with patch.object(host.os.path, "ismount", return_value=True):
            host.replace_restored_path(source, target, "123")
        self.assertEqual((target / "restored").read_text(), "restored data")
        self.assertEqual(
            (target / ".before-restore-123/original").read_text(), "keep original"
        )

    def test_restore_preserves_original_data_and_activates_snapshot_identity(self):
        self.initialized()
        (self.root / "original").write_text("keep original")
        snapshot_values = host.environment(self.candidate(), self.metadata)
        snapshot_values["APP_KEY"] = "snapshot-original-key"
        metadata = self.metadata | {
            "commit": OTHER,
            "services": ["mariadb", "nginx", "nntmux-horizon"],
            "packages": {"mariadb-server": "1:11.4.8"},
            "created_at": 1,
        }

        def restic(*args, **kwargs):
            if args[0] == "dump":
                return Mock(stdout=json.dumps(metadata))
            if args[0] == "restore":
                stage = Path(args[args.index("--target") + 1])
                root = stage / str(self.root).lstrip("/")
                config = stage / str(self.config).lstrip("/")
                (root / "releases" / OTHER).mkdir(parents=True)
                (root / "releases" / OTHER / ".baremetal-ready.json").write_text("{}")
                (root / "shared/storage/framework").mkdir(parents=True)
                (config / "releases").mkdir(parents=True)
                (config / "releases" / (OTHER + ".json")).write_text(
                    json.dumps(snapshot_values)
                )
                (config / "host.json").write_text(json.dumps(self.metadata))
                (config / "backup.json").write_text("{}")
                return Mock(returncode=0)
            raise AssertionError("Unexpected restic operation")

        with patch.object(
            host.tempfile, "TemporaryDirectory", side_effect=self.stage_factory
        ), patch.object(host, "restic", side_effect=restic), patch.object(
            host, "stop_services"
        ), patch.object(
            host, "restore_packages"
        ) as versions, patch.object(
            host, "run"
        ), patch.object(
            host, "systemctl"
        ), patch.object(
            host, "ready"
        ), patch.object(
            host, "cache"
        ), patch.object(
            host, "artisan"
        ), patch.object(
            host, "restart_services"
        ) as restart, patch.object(
            host, "verify"
        ) as verify:
            host.restore("1234abcd", move_aside=True)
        versions.assert_called_once_with(metadata["packages"])
        self.assertEqual(host.current_commit(), OTHER)
        self.assertEqual(
            json.loads((self.config / "application.json").read_text())["APP_KEY"],
            "snapshot-original-key",
        )
        preserved = list(self.root.parent.glob("nntmux.before-restore-*/original"))
        self.assertEqual(len(preserved), 1)
        self.assertEqual(preserved[0].read_text(), "keep original")
        restart.assert_called_once_with(metadata["services"])
        verify.assert_called_once()

    def test_invalid_package_manifest_is_rejected_without_running_apt(self):
        with patch.object(host, "run") as run:
            with self.assertRaises(host.DeploymentError):
                host.restore_packages({"--option": "1"})
        run.assert_not_called()

    def test_subprocess_errors_do_not_expose_child_credentials(self):
        result = subprocess.CompletedProcess(
            [], 1, "password=TOPSECRET", "secret=TOPSECRET"
        )
        with patch.object(host.subprocess, "run", return_value=result):
            with self.assertRaises(host.DeploymentError) as raised:
                host.run(["php8.5", "artisan"])
        self.assertNotIn("TOPSECRET", str(raised.exception))


class InventoryTest(unittest.TestCase):
    def inventory(self):
        return {
            "all": {
                "children": {
                    "nntmux": {
                        "hosts": {
                            "indexer": {
                                "ansible_host": "192.0.2.10",
                                "ansible_user": "admin",
                            }
                        }
                    }
                }
            }
        }

    def test_valid_connection_metadata(self):
        self.assertIn("indexer", deploy.validate_inventory(self.inventory(), "indexer"))

    def test_settings_cannot_override_connection_or_supply_plaintext_credentials(self):
        deploy.validate_settings(
            {
                "nntmux_commit": SHA,
                "nntmux_application_settings": {"MAIL_HOST": "smtp.example.com"},
            }
        )
        for settings in [
            {"ansible_host": "192.0.2.20"},
            {"nntmux_secrets": {"APP_KEY": "secret"}},
            {"nntmux_application_settings": {"MAIL_PASSWORD": "secret"}},
        ]:
            with self.assertRaises(ValueError):
                deploy.validate_settings(settings)

    def test_entrypoint_passes_sudo_prompt_flag_without_executing_remote_commands(self):
        with tempfile.TemporaryDirectory() as directory:
            inventory = Path(directory) / "hosts.json"
            inventory.write_text("{}")
            parsed = {
                "_meta": {
                    "hostvars": {
                        "indexer": {
                            "ansible_host": "192.0.2.10",
                            "ansible_user": "admin",
                        }
                    }
                },
                "nntmux": {"hosts": ["indexer"]},
            }
            arguments = [
                "deploy.py",
                "verify",
                "--host",
                "indexer",
                "--inventory",
                str(inventory),
                "--ask-become-pass",
            ]
            with patch("sys.argv", arguments), patch.object(
                deploy.subprocess, "run", return_value=Mock(stdout=json.dumps(parsed))
            ), patch.object(deploy.subprocess, "call", return_value=0) as call:
                self.assertEqual(deploy.main(), 0)
            self.assertIn("--ask-become-pass", call.call_args.args[0])

    def test_reserved_group_names_are_rejected_even_when_a_host_uses_that_name(self):
        for name in ["all", "nntmux", "ungrouped"]:
            inventory = self.inventory()
            inventory["all"]["children"]["nntmux"]["hosts"][name] = inventory["all"][
                "children"
            ]["nntmux"]["hosts"]["indexer"]
            with self.assertRaises(ValueError):
                deploy.validate_inventory(inventory, name)

    def test_invalid_host_patterns_addresses_ports_and_secrets(self):
        for key, value in [
            ("ansible_host", "localhost;command"),
            ("ansible_port", 0),
            ("ansible_port", 22.5),
            ("ansible_password", "secret"),
        ]:
            inventory = self.inventory()
            inventory["all"]["children"]["nntmux"]["hosts"]["indexer"][key] = value
            with self.assertRaises(ValueError):
                deploy.validate_inventory(inventory, "indexer")
        for selected in ["all", "*", "indexer:other"]:
            with self.assertRaises(ValueError):
                deploy.validate_inventory(self.inventory(), selected)


if __name__ == "__main__":
    unittest.main()
