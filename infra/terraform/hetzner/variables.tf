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
variable "location" {
  description = "Hetzner location shared by the server and volumes."
  type        = string
  default     = "fsn1"
}
variable "network_zone" {
  description = "Hetzner network zone containing the selected location."
  type        = string
  default     = "eu-central"
}
variable "server_type" {
  description = "x86_64 server type; the default provides 8 dedicated vCPUs and 32 GiB RAM."
  type        = string
  default     = "ccx33"
  validation {
    condition     = can(regex("^(ccx|cpx|cx)[0-9]+$", var.server_type))
    error_message = "Supply an x86_64 Hetzner server type; ARM types are unsupported."
  }
}
