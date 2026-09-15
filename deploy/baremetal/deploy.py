#!/usr/bin/env python3
"""Validate public inputs, then invoke the native Ansible playbook for one server."""

import argparse
import ipaddress
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile

REPOSITORY = Path(__file__).resolve().parents[2]
OPERATIONS = [
    "install",
    "update",
    "rollback",
    "verify",
    "backup",
    "restore",
    "processing-start",
    "processing-stop",
]


def validate_inventory(inventory, selected):
    if selected in {"all", "nntmux", "ungrouped"}:
        raise ValueError("Reserved inventory group names cannot be selected as a host.")
    try:
        hosts = inventory["all"]["children"]["nntmux"]["hosts"]
        values = hosts[selected]
        ipaddress.ip_address(values["ansible_host"])
        port = values.get("ansible_port", 22)
        if (
            isinstance(port, bool)
            or int(port) != float(port)
            or not 1 <= int(port) <= 65535
        ):
            raise ValueError("Invalid SSH port.")
        if not re.fullmatch(r"[a-z_][a-z0-9_-]{0,31}", values["ansible_user"]):
            raise ValueError("Invalid SSH user.")
        if not re.fullmatch(r"[a-z][a-z0-9_-]{0,62}", selected):
            raise ValueError("Select one exact inventory hostname.")
        if any(
            re.search(r"password|secret|token|private|vault", key, re.I)
            for key in values
        ):
            raise ValueError("Inventory must contain public connection metadata only.")
    except (KeyError, TypeError) as error:
        raise ValueError(
            "Inventory must define the selected host under all.children.nntmux.hosts."
        ) from error
    return hosts


def validate_settings(settings):
    allowed = {
        "nntmux_commit",
        "nntmux_acme_email",
        "nntmux_search_driver",
        "nntmux_backup_repository",
        "nntmux_backup_region",
        "nntmux_backup_schedule",
        "nntmux_min_memory_mb",
        "nntmux_min_disk_bytes",
        "nntmux_php_workers",
        "nntmux_elasticsearch_heap",
        "nntmux_repository",
        "nntmux_application_settings",
    }
    if not isinstance(settings, dict) or set(settings) - allowed:
        raise ValueError(
            "Settings contain unknown keys; connection metadata belongs in inventory and secrets in Vault."
        )
    application = settings.get("nntmux_application_settings", {})
    if not isinstance(application, dict):
        raise ValueError("Application settings must be a mapping.")
    if any(
        re.search(r"PASSWORD|PASS$|TOKEN|APIKEY|API_KEY|APP_KEY|SECRET|USERNAME", key)
        for key in application
    ):
        raise ValueError(
            "Credential settings must be supplied through encrypted Vault variables."
        )


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("operation", choices=OPERATIONS)
    parser.add_argument(
        "--host",
        required=True,
        help="One exact inventory hostname; patterns are not accepted.",
    )
    parser.add_argument(
        "--inventory", type=Path, help="Hand-written Ansible YAML/JSON inventory."
    )
    parser.add_argument(
        "--settings", type=Path, help="Non-secret YAML installation settings."
    )
    parser.add_argument(
        "--vault-vars",
        type=Path,
        help="Whole-file Ansible Vault encrypted per-host settings.",
    )
    parser.add_argument("--vault-password-file", type=Path)
    parser.add_argument("--commit")
    parser.add_argument("--snapshot")
    parser.add_argument("--schema-compatible", action="store_true")
    parser.add_argument("--move-data-aside", action="store_true")
    parser.add_argument(
        "--ask-become-pass",
        action="store_true",
        help="Prompt for the server's sudo password.",
    )
    parser.add_argument("--check", action="store_true")
    parser.add_argument("--diff", action="store_true")
    parser.add_argument("--syntax-check", action="store_true")
    args = parser.parse_args()
    if args.host in {"all", "nntmux", "ungrouped"} or not re.fullmatch(
        r"[a-z][a-z0-9_-]{0,62}", args.host
    ):
        parser.error(
            "Select one exact hostname; host patterns and all are not accepted."
        )
    if args.commit and not re.fullmatch(r"[a-f0-9]{40}", args.commit):
        parser.error("--commit requires a full lowercase commit SHA.")
    if args.operation == "rollback" and (not args.commit or not args.schema_compatible):
        parser.error(
            "Rollback requires --commit and --schema-compatible; restore incompatible schemas."
        )
    if args.operation == "restore" and (
        not args.snapshot or not re.fullmatch(r"[a-f0-9]{8,64}", args.snapshot)
    ):
        parser.error("Restore requires an explicit --snapshot ID.")
    if args.operation in ["install", "update", "restore"] and not (
        args.settings and args.vault_vars
    ):
        parser.error(
            "Installation, update, and restore require --settings and --vault-vars."
        )
    for path in [
        args.inventory,
        args.settings,
        args.vault_vars,
        args.vault_password_file,
    ]:
        if path and not path.is_file():
            parser.error(
                "All supplied configuration paths must be existing regular files."
            )
    if args.settings:
        import yaml

        validate_settings(yaml.safe_load(args.settings.read_text()))
    if args.vault_vars and not args.vault_vars.read_bytes().startswith(
        b"$ANSIBLE_VAULT;"
    ):
        parser.error("--vault-vars must be a whole-file encrypted Ansible Vault file.")
    if args.vault_password_file and args.vault_password_file.stat().st_mode & 0o077:
        parser.error(
            "Vault password file permissions must exclude group and other users."
        )
    ansible = REPOSITORY / "infra/ansible"
    env = os.environ.copy()
    env["ANSIBLE_CONFIG"] = str(ansible / "ansible.cfg")
    env["ANSIBLE_ROLES_PATH"] = str(ansible / "roles")
    with tempfile.TemporaryDirectory(prefix="nntmux-inventory-") as directory:
        inventory_file = (
            args.inventory.resolve()
            if args.inventory
            else Path(directory) / "hosts.json"
        )
        if not args.inventory:
            result = subprocess.run(
                [
                    "terraform",
                    "-chdir=" + str(REPOSITORY / "infra/terraform/baremetal"),
                    "output",
                    "-json",
                    "ansible_inventory",
                ],
                capture_output=True,
                text=True,
                check=True,
            )
            inventory = json.loads(result.stdout)
            validate_inventory(inventory, args.host)
            inventory_file.write_text(json.dumps(inventory))
            inventory_file.chmod(0o600)
        else:
            # Convert YAML inventory through Ansible's own parser; inspect connection fields only.
            result = subprocess.run(
                ["ansible-inventory", "-i", str(inventory_file), "--list"],
                capture_output=True,
                text=True,
                env=env,
                cwd=ansible,
                check=True,
            )
            parsed = json.loads(result.stdout)
            values = parsed.get("_meta", {}).get("hostvars", {}).get(args.host, {})
            members = parsed.get("nntmux", {}).get("hosts", [])
            if args.host in parsed:
                parser.error("Selected host conflicts with an inventory group name.")
            if args.host not in members:
                parser.error(
                    "Selected host must be a direct member of the nntmux group."
                )
            validate_inventory(
                {"all": {"children": {"nntmux": {"hosts": {args.host: values}}}}},
                args.host,
            )
        command = [
            "ansible-playbook",
            "-i",
            str(inventory_file),
            str(ansible / "playbooks" / (args.operation + ".yml")),
            "--limit",
            args.host,
        ]
        for path in [args.settings, args.vault_vars]:
            if path:
                command += ["--extra-vars", "@" + str(path.resolve())]
        public_vars = {}
        if args.commit:
            public_vars["nntmux_commit"] = args.commit
        if args.snapshot:
            public_vars["nntmux_snapshot"] = args.snapshot
        public_vars.update(
            {
                "nntmux_schema_compatible": args.schema_compatible,
                "nntmux_move_data_aside": args.move_data_aside,
            }
        )
        command += ["--extra-vars", json.dumps(public_vars)]
        if args.vault_vars:
            command += (
                ["--vault-password-file", str(args.vault_password_file.resolve())]
                if args.vault_password_file
                else ["--ask-vault-pass"]
            )
        if args.ask_become_pass:
            command.append("--ask-become-pass")
        for flag in ["check", "diff", "syntax_check"]:
            if getattr(args, flag):
                command.append("--" + flag.replace("_", "-"))
        return subprocess.call(command, cwd=ansible, env=env)


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, ValueError, subprocess.CalledProcessError):
        raise SystemExit(
            "Deployment input or inventory validation failed; no host operation was requested."
        )
