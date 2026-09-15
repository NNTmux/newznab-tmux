mock_provider "openstack" {
  mock_data "openstack_networking_network_v2" { defaults = { id = "11111111-1111-1111-1111-111111111111" } }
  mock_data "openstack_images_image_v2" { defaults = { id = "22222222-2222-2222-2222-222222222222" } }
  mock_data "openstack_compute_flavor_v2" { defaults = { id = "33333333-3333-3333-3333-333333333333" } }
}
mock_provider "ovh" {}
variables {
  region            = "GRA11"
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
    condition     = toset([for rule in openstack_networking_secgroup_rule_v2.web : rule.port_range_min]) == toset([80, 443]) && alltrue([for rule in openstack_networking_secgroup_rule_v2.administration : rule.port_range_min == 22 && contains(var.admin_cidrs, rule.remote_ip_prefix)])
    error_message = "Public ports must be HTTP/HTTPS, with SSH restricted to trusted CIDRs."
  }
  assert {
    condition     = openstack_networking_secgroup_v2.application.delete_default_rules && openstack_networking_secgroup_rule_v2.outbound.direction == "egress" && openstack_networking_subnet_v2.application.enable_dhcp
    error_message = "Use explicit security rules and a usable private subnet."
  }
  assert {
    condition     = openstack_blockstorage_volume_v3.data.size == 250 && openstack_blockstorage_volume_v3.data.availability_zone == openstack_compute_instance_v2.application.availability_zone && openstack_compute_instance_v2.application.block_device[0].volume_size == 40 && openstack_compute_instance_v2.application.config_drive
    error_message = "Volumes must be colocated and use the selected sizes, with config-drive bootstrap."
  }
  assert {
    condition     = openstack_networking_router_v2.application.enable_snat && openstack_networking_floatingip_v2.application.pool == var.external_network_name && ovh_domain_zone_record.application[0].fieldtype == "A" && ovh_domain_zone_record.application[0].subdomain == "indexer"
    error_message = "The private instance needs Gateway connectivity, a retained Floating IP, and matching DNS."
  }
  assert {
    condition     = module.host.host_config.provider == "ovh" && module.host.host_config.state_root == "/srv/nntmux/host-state"
    error_message = "OVH must use the shared host workflow with its state on encrypted data storage."
  }
}
run "recovery_volume" {
  command = plan
  variables {
    create_recovery_volume = true
    use_recovery_volume    = true
  }
  assert {
    condition     = length(openstack_blockstorage_volume_v3.recovery) == 1 && openstack_blockstorage_volume_v3.recovery[0].availability_zone == openstack_blockstorage_volume_v3.data.availability_zone && length(openstack_compute_volume_attach_v2.recovery) == 1
    error_message = "Recovery requires a separate attached volume in the same availability zone."
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
