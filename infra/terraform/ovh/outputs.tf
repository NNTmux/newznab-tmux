output "instance_id" {
  description = "OVH Public Cloud instance UUID."
  value       = openstack_compute_instance_v2.application.id
}
output "static_ip" {
  description = "Retained Floating IPv4 address."
  value       = openstack_networking_floatingip_v2.application.address
}
output "hostname" {
  description = "Application hostname."
  value       = var.hostname
}
output "data_volume_id" {
  description = "Selected active persistent volume UUID."
  value       = local.active_volume_id
}
output "primary_volume_id" {
  description = "Original protected volume, retained after recovery."
  value       = openstack_blockstorage_volume_v3.data.id
}
output "recovery_volume_id" {
  description = "Separate recovery target when requested."
  value       = try(openstack_blockstorage_volume_v3.recovery[0].id, null)
}
output "repository_url" {
  description = "Existing digest-based OCI image repository."
  value       = var.repository_url
}
output "host_config" {
  description = "Public host configuration for bootstrap."
  value       = module.host.host_config
}
output "ssh_user" {
  description = "Ubuntu image administrator username."
  value       = "ubuntu"
}
