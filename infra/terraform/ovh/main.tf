locals {
  name             = "nntmux-ovh-${var.environment}"
  active_volume_id = var.use_recovery_volume ? openstack_blockstorage_volume_v3.recovery[0].id : openstack_blockstorage_volume_v3.data.id
}
data "openstack_networking_network_v2" "external" {
  name = var.external_network_name
}
data "openstack_images_image_v2" "ubuntu" {
  name        = var.image_name
  most_recent = true
}
data "openstack_compute_flavor_v2" "application" {
  name = var.flavor_name
}
resource "openstack_compute_keypair_v2" "administrator" {
  name       = local.name
  public_key = var.ssh_public_key
}
resource "openstack_networking_network_v2" "application" {
  name           = local.name
  admin_state_up = true
}
resource "openstack_networking_subnet_v2" "application" {
  name            = local.name
  network_id      = openstack_networking_network_v2.application.id
  cidr            = "10.42.1.0/24"
  ip_version      = 4
  enable_dhcp     = true
  dns_nameservers = ["1.1.1.1", "9.9.9.9"]
}
resource "openstack_networking_router_v2" "application" {
  name                = local.name
  admin_state_up      = true
  external_network_id = data.openstack_networking_network_v2.external.id
  enable_snat         = true
}
resource "openstack_networking_router_interface_v2" "application" {
  router_id = openstack_networking_router_v2.application.id
  subnet_id = openstack_networking_subnet_v2.application.id
}
resource "openstack_networking_secgroup_v2" "application" {
  name                 = local.name
  description          = "Public web access and SSH from trusted administrator CIDRs only"
  delete_default_rules = true
}
resource "openstack_networking_secgroup_rule_v2" "web" {
  for_each          = toset(["80", "443"])
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = tonumber(each.value)
  port_range_max    = tonumber(each.value)
  remote_ip_prefix  = "0.0.0.0/0"
  security_group_id = openstack_networking_secgroup_v2.application.id
}
resource "openstack_networking_secgroup_rule_v2" "administration" {
  for_each          = var.admin_cidrs
  direction         = "ingress"
  ethertype         = "IPv4"
  protocol          = "tcp"
  port_range_min    = 22
  port_range_max    = 22
  remote_ip_prefix  = each.value
  security_group_id = openstack_networking_secgroup_v2.application.id
}
resource "openstack_networking_secgroup_rule_v2" "outbound" {
  direction         = "egress"
  ethertype         = "IPv4"
  remote_ip_prefix  = "0.0.0.0/0"
  security_group_id = openstack_networking_secgroup_v2.application.id
}
resource "openstack_networking_port_v2" "application" {
  name               = local.name
  network_id         = openstack_networking_network_v2.application.id
  admin_state_up     = true
  security_group_ids = [openstack_networking_secgroup_v2.application.id]
  fixed_ip {
    subnet_id = openstack_networking_subnet_v2.application.id
  }
}
resource "openstack_networking_floatingip_v2" "application" {
  pool       = var.external_network_name
  port_id    = openstack_networking_port_v2.application.id
  depends_on = [openstack_networking_router_interface_v2.application]
}
resource "openstack_blockstorage_volume_v3" "data" {
  name              = "${local.name}-data"
  size              = var.data_volume_size
  volume_type       = var.volume_type
  availability_zone = var.availability_zone
  metadata = { deployment = local.name
  }
  lifecycle {
    prevent_destroy = true
  }
}
resource "openstack_blockstorage_volume_v3" "recovery" {
  count             = var.create_recovery_volume ? 1 : 0
  name              = "${local.name}-recovery"
  size              = var.data_volume_size
  volume_type       = var.volume_type
  availability_zone = var.availability_zone
  metadata = { deployment = local.name
  }
  lifecycle {
    prevent_destroy = true
  }
}
module "host" {
  source            = "../modules/host"
  cloud_provider    = "ovh"
  environment       = var.environment
  hostname          = var.hostname
  region            = var.region
  repository_url    = var.repository_url
  vault_address     = var.vault_address
  vault_secret_path = var.vault_secret_path
  data_volume_id    = local.active_volume_id
}
resource "openstack_compute_instance_v2" "application" {
  name              = local.name
  flavor_id         = data.openstack_compute_flavor_v2.application.id
  key_pair          = openstack_compute_keypair_v2.administrator.name
  availability_zone = var.availability_zone
  config_drive      = true
  user_data         = module.host.user_data
  metadata = { deployment = local.name
  }
  block_device {
    uuid                  = data.openstack_images_image_v2.ubuntu.id
    source_type           = "image"
    destination_type      = "volume"
    volume_size           = var.root_volume_size
    boot_index            = 0
    delete_on_termination = true
  }
  network {
    port = openstack_networking_port_v2.application.id
  }
  lifecycle {
    ignore_changes = [user_data, block_device[0].uuid]
  }
  depends_on = [openstack_networking_router_interface_v2.application]
}
resource "openstack_compute_volume_attach_v2" "data" {
  instance_id = openstack_compute_instance_v2.application.id
  volume_id   = openstack_blockstorage_volume_v3.data.id
}
resource "openstack_compute_volume_attach_v2" "recovery" {
  count       = var.create_recovery_volume ? 1 : 0
  instance_id = openstack_compute_instance_v2.application.id
  volume_id   = openstack_blockstorage_volume_v3.recovery[0].id
}
resource "ovh_domain_zone_record" "application" {
  count     = var.dns_zone == null ? 0 : 1
  zone      = var.dns_zone
  subdomain = var.hostname == var.dns_zone ? "" : trimsuffix(var.hostname, ".${var.dns_zone}")
  fieldtype = "A"
  ttl       = 300
  target    = openstack_networking_floatingip_v2.application.address
}
