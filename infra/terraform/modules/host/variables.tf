variable "data_volume_id" {
  description = "Public host deployment parameter: data_volume_id."
  type        = string
}

variable "environment" {
  description = "Public host deployment parameter: environment."
  type        = string
}

variable "hostname" {
  description = "Public host deployment parameter: hostname."
  type        = string
}

variable "cloud_provider" {
  description = "Public host deployment parameter: provider."
  type        = string
}

variable "region" {
  description = "Public host deployment parameter: region."
  type        = string
}

variable "repository_url" {
  description = "Public host deployment parameter: repository_url."
  type        = string
}

variable "vault_address" {
  description = "Public host deployment parameter: vault_address."
  type        = string
}

variable "vault_secret_path" {
  description = "Public host deployment parameter: vault_secret_path."
  type        = string
}
