locals {
  name = "nntmux-hetzner-${var.environment}"
  labels = { deployment = local.name
  }
  active_volume_id = var.use_recovery_volume ? hcloud_volume.recovery[0].id : hcloud_volume.data.id
}
resource "hcloud_ssh_key" "administrator" {
  name       = local.name
  public_key = var.ssh_public_key
  labels     = local.labels
}
resource "hcloud_network" "application" {
  name     = local.name
  ip_range = "10.42.0.0/16"
  labels   = local.labels
}
resource "hcloud_network_subnet" "application" {
  network_id   = hcloud_network.application.id
  type         = "cloud"
  network_zone = var.network_zone
  ip_range     = "10.42.1.0/24"
}
resource "hcloud_primary_ip" "application" {
  name        = local.name
  location    = var.location
  type        = "ipv4"
  auto_delete = false
  labels      = local.labels
}
resource "hcloud_firewall" "application" {
  name   = local.name
  labels = local.labels
  dynamic "rule" {
    for_each = toset(["80", "443"])
    content {
      direction  = "in"
      protocol   = "tcp"
      port       = rule.value
      source_ips = ["0.0.0.0/0"]

    }

  }
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "22"
    source_ips = var.admin_cidrs
  }
  # No outbound rules means outbound NNTP, DNS, metadata, and registry access is allowed.
}
resource "hcloud_volume" "data" {
  name              = "${local.name}-data"
  location          = var.location
  size              = var.data_volume_size
  labels            = local.labels
  delete_protection = true
  lifecycle {
    prevent_destroy = true
  }
  # Never use format or automount: initialization is an explicit guarded command.
}
resource "hcloud_volume" "recovery" {
  count             = var.create_recovery_volume ? 1 : 0
  name              = "${local.name}-recovery"
  location          = var.location
  size              = var.data_volume_size
  labels            = local.labels
  delete_protection = true
  lifecycle {
    prevent_destroy = true
  }
}
module "host" {
  source            = "../modules/host"
  cloud_provider    = "hetzner"
  environment       = var.environment
  hostname          = var.hostname
  region            = var.location
  repository_url    = var.repository_url
  vault_address     = var.vault_address
  vault_secret_path = var.vault_secret_path
  data_volume_id    = tostring(local.active_volume_id)
}
resource "hcloud_server" "application" {
  name         = local.name
  image        = "ubuntu-24.04"
  server_type  = var.server_type
  location     = var.location
  ssh_keys     = [hcloud_ssh_key.administrator.id]
  firewall_ids = [hcloud_firewall.application.id]
  labels       = local.labels
  user_data    = module.host.user_data
  public_net {
    ipv4_enabled = true
    ipv4         = hcloud_primary_ip.application.id
    ipv6_enabled = false
  }
  lifecycle {
    ignore_changes = [image, user_data]
  }
}
resource "hcloud_server_network" "application" {
  server_id  = hcloud_server.application.id
  network_id = hcloud_network.application.id
  ip         = "10.42.1.10"
  depends_on = [hcloud_network_subnet.application]
}
resource "hcloud_volume_attachment" "data" {
  volume_id = hcloud_volume.data.id
  server_id = hcloud_server.application.id
  automount = false
}
resource "hcloud_volume_attachment" "recovery" {
  count     = var.create_recovery_volume ? 1 : 0
  volume_id = hcloud_volume.recovery[0].id
  server_id = hcloud_server.application.id
  automount = false
}
data "hcloud_zone" "application" {
  count = var.dns_zone == null ? 0 : 1
  name  = var.dns_zone
}
resource "hcloud_zone_rrset" "application" {
  count   = var.dns_zone == null ? 0 : 1
  zone    = data.hcloud_zone.application[0].name
  name    = var.hostname == var.dns_zone ? "@" : trimsuffix(var.hostname, ".${var.dns_zone}")
  type    = "A"
  ttl     = 300
  records = [{ value = hcloud_primary_ip.application.ip_address }]
}
