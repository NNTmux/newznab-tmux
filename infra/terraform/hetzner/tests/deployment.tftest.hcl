mock_provider "hcloud" {
  mock_data "hcloud_zone" { defaults = { name = "example.com", mode = "primary" } }
}
variables {
  environment       = "test"
  hostname          = "indexer.example.com"
  repository_url    = "ghcr.io/example/nntmux"
  vault_address     = "https://vault.example.com"
  vault_secret_path = "secret/data/nntmux/test"
  admin_cidrs       = ["203.0.113.10/32"]
  ssh_public_key    = "ssh-ed25519 AAAATESTKEY"
  dns_zone          = "example.com"
}
run "deployment_contract" {
  command = plan
  assert {
    condition     = toset([for rule in hcloud_firewall.application.rule : rule.port if contains(rule.source_ips, "0.0.0.0/0")]) == toset(["80", "443"])
    error_message = "Only HTTP and HTTPS may be public; SSH must use the administrator allowlist."
  }
  assert {
    condition     = alltrue([for rule in hcloud_firewall.application.rule : rule.port != "22" || toset(rule.source_ips) == var.admin_cidrs])
    error_message = "SSH must be restricted to administrator CIDRs."
  }
  assert {
    condition     = hcloud_volume.data.size == 250 && hcloud_volume.data.delete_protection && hcloud_volume.data.format == null && !hcloud_volume_attachment.data.automount && hcloud_volume.data.location == hcloud_server.application.location
    error_message = "Data storage must be protected, colocated, and never automatically formatted or mounted."
  }
  assert {
    condition     = !hcloud_primary_ip.application.auto_delete && !one(hcloud_server.application.public_net).ipv6_enabled && hcloud_server.application.image == "ubuntu-24.04"
    error_message = "Retain the public IPv4 and use the selected Ubuntu image without unfiltered IPv6."
  }
  assert {
    condition     = module.host.host_config.provider == "hetzner" && module.host.host_config.state_root == "/srv/nntmux/host-state" && hcloud_zone_rrset.application[0].type == "A" && hcloud_zone_rrset.application[0].name == "indexer"
    error_message = "The host and DNS record must select the Hetzner deployment and encrypted data state."
  }
}
run "recovery_volume" {
  command = plan
  variables {
    create_recovery_volume = true
    use_recovery_volume    = true
  }
  assert {
    condition     = length(hcloud_volume.recovery) == 1 && hcloud_volume.recovery[0].delete_protection && !hcloud_volume_attachment.recovery[0].automount && hcloud_volume.recovery[0].location == hcloud_volume.data.location
    error_message = "Recovery requires a separate protected volume in the same location."
  }
}
run "invalid_environment" {
  command = plan
  variables { environment = "Bad Environment" }
  expect_failures = [var.environment]
}
run "unrestricted_administration" {
  command = plan
  variables { admin_cidrs = ["0.0.0.0/0"] }
  expect_failures = [var.admin_cidrs]
}
run "invalid_storage" {
  command = plan
  variables { data_volume_size = 20 }
  expect_failures = [var.data_volume_size]
}
run "plaintext_vault_endpoint" {
  command = plan
  variables { vault_address = "http://vault.example.com" }
  expect_failures = [var.vault_address]
}
run "hostname_outside_zone" {
  command = plan
  variables { dns_zone = "other.example" }
  expect_failures = [var.dns_zone]
}
run "mutable_repository" {
  command = plan
  variables { repository_url = "ghcr.io/example/nntmux:latest" }
  expect_failures = [var.repository_url]
}
