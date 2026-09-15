output "instance_id" {
  description = "Hetzner server ID."
  value       = hcloud_server.application.id
}
output "static_ip" {
  description = "Retained primary IPv4 address."
  value       = hcloud_primary_ip.application.ip_address
}
output "hostname" {
  description = "Application hostname."
  value       = var.hostname
}
output "data_volume_id" {
  description = "Selected active persistent volume ID."
  value       = local.active_volume_id
}
output "primary_volume_id" {
  description = "Original protected volume, retained after recovery."
  value       = hcloud_volume.data.id
}
output "recovery_volume_id" {
  description = "Separate recovery target, when requested."
  value       = try(hcloud_volume.recovery[0].id, null)
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
  value       = "root"
}
