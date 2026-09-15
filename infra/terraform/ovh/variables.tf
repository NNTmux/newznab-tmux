variable "environment" {
  description = "Deployment identifier used in names and backup tags."
  type        = string
  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{0,19}$", var.environment))
    error_message = "Use 1-20 lowercase letters, digits, or hyphens, beginning with a letter."
  }
}
variable "hostname" {
  description = "Public application hostname."
  type        = string
  validation {
    condition     = length(var.hostname) <= 253 && can(regex("^([a-z0-9]([a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$", var.hostname))
    error_message = "Supply a lowercase DNS hostname without a scheme or path."
  }
}
variable "repository_url" {
  description = "Existing OCI repository without a tag or digest, such as ghcr.io/owner/nntmux."
  type        = string
  validation {
    condition     = can(regex("^[a-z0-9][a-z0-9.:-]*/[a-z0-9._/-]+$", var.repository_url)) && !strcontains(var.repository_url, "..")
    error_message = "Supply an OCI registry/repository path without a scheme, tag, or digest."
  }
}
variable "vault_address" {
  description = "Existing Vault HTTPS address; Terraform never reads its secret values."
  type        = string
  validation {
    condition     = can(regex("^https://[a-zA-Z0-9.-]+(:[0-9]+)?$", var.vault_address))
    error_message = "Supply a Vault HTTPS origin without a trailing slash."
  }
}
variable "vault_secret_path" {
  description = "Vault KV v2 API path, for example secret/data/nntmux/production."
  type        = string
  validation {
    condition     = can(regex("^[a-zA-Z0-9/_-]+/data/[a-zA-Z0-9/_-]+$", var.vault_secret_path)) && !startswith(var.vault_secret_path, "/")
    error_message = "Supply a KV v2 API path containing /data/, without a leading slash."
  }
}
variable "admin_cidrs" {
  description = "Trusted IPv4 administrator CIDRs allowed to access SSH; unrestricted SSH is refused."
  type        = set(string)
  validation {
    condition     = length(var.admin_cidrs) > 0 && alltrue([for cidr in var.admin_cidrs : can(cidrnetmask(cidr)) && try(tonumber(split("/", cidr)[1]) >= 24, false)])
    error_message = "Supply at least one trusted IPv4 CIDR with prefix /24 or narrower."
  }
}
variable "ssh_public_key" {
  description = "Administrator public SSH key. Private keys are never supplied to Terraform."
  type        = string
  validation {
    condition     = can(regex("^ssh-(ed25519|rsa) [A-Za-z0-9+/=]+( .*)?$", trimspace(var.ssh_public_key)))
    error_message = "Supply an SSH Ed25519 or RSA public key."
  }
}
variable "data_volume_size" {
  description = "Persistent LUKS-encrypted data volume size in GiB."
  type        = number
  default     = 250
  validation {
    condition     = var.data_volume_size >= 100 && floor(var.data_volume_size) == var.data_volume_size
    error_message = "Data storage must be an integer of at least 100 GiB."
  }
}
variable "create_recovery_volume" {
  description = "Provision and attach an additional protected volume for a staged restore."
  type        = bool
  default     = false
}
variable "use_recovery_volume" {
  description = "Select the recovered volume after explicit restore activation; does not migrate or format data."
  type        = bool
  default     = false
  validation {
    condition     = !var.use_recovery_volume || var.create_recovery_volume
    error_message = "Selecting recovery storage requires create_recovery_volume=true."
  }
}
variable "dns_zone" {
  description = "Existing provider DNS zone containing hostname; null leaves DNS with the operator."
  type        = string
  default     = null
  validation {
    condition     = var.dns_zone == null ? true : var.hostname == var.dns_zone || endswith(var.hostname, ".${var.dns_zone}")
    error_message = "hostname must belong to the supplied DNS zone."
  }
}
variable "region" {
  description = "OVH Public Cloud OpenStack region supporting Gateway and Floating IP services."
  type        = string
}
variable "availability_zone" {
  description = "Compute and block-storage availability zone in the selected region."
  type        = string
  default     = "nova"
}
variable "flavor_name" {
  description = "x86_64 OVH flavor; default is 8 vCPUs and 32 GiB RAM."
  type        = string
  default     = "b3-32"
}
variable "image_name" {
  description = "Existing Ubuntu 24.04 x86_64 Glance image name."
  type        = string
  default     = "Ubuntu 24.04"
}
variable "volume_type" {
  description = "OVH Cinder volume type available in the selected region."
  type        = string
  default     = "high-speed-gen2"
}
variable "root_volume_size" {
  description = "Boot volume size in GiB. Provider encryption is not assumed."
  type        = number
  default     = 40
  validation {
    condition     = var.root_volume_size >= 40 && floor(var.root_volume_size) == var.root_volume_size
    error_message = "Root storage must be an integer of at least 40 GiB."
  }
}
variable "external_network_name" {
  description = "Existing OpenStack external network used by the Gateway and Floating IP."
  type        = string
  default     = "Ext-Net"
}
