locals {
  host_config = {
    provider          = var.cloud_provider
    region            = var.region
    deployment        = "nntmux-${var.cloud_provider}-${var.environment}"
    hostname          = var.hostname
    repository        = var.repository_url
    data_volume_id    = var.data_volume_id
    vault_address     = var.vault_address
    vault_secret_path = var.vault_secret_path
    state_root        = "/srv/nntmux/host-state"
  }
}
output "host_config" {
  description = "Public host configuration; contains no application or provider credentials."
  value       = local.host_config
}
output "user_data" {
  description = "Ubuntu host prerequisites and public deployment configuration."
  value       = templatefile("${path.module}/cloud-init.yaml.tftpl", { host_config = base64encode(jsonencode(local.host_config)) })
}
