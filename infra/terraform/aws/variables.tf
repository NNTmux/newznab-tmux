variable "aws_region" {
  description = "AWS region for all deployment resources."
  type        = string
  validation {
    condition     = can(regex("^[a-z]{2}(-[a-z]+)+-[0-9]+$", var.aws_region))
    error_message = "Supply a valid AWS region name."
  }
}
variable "availability_zone" {
  description = "Availability zone shared by the instance and persistent volume."
  type        = string
  validation {
    condition     = startswith(var.availability_zone, var.aws_region) && can(regex("[a-z]$", var.availability_zone))
    error_message = "The availability zone must belong to aws_region."
  }
}
variable "environment" {
  description = "Unique deployment identifier used in names and tags."
  type        = string
  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{0,19}$", var.environment))
    error_message = "Use 1-20 lowercase letters, digits, or hyphens, beginning with a letter."
  }
}
variable "hostname" {
  description = "Public application hostname in the existing Route 53 zone."
  type        = string
  validation {
    condition     = length(var.hostname) <= 253 && can(regex("^([a-z0-9]([a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}$", var.hostname))
    error_message = "Supply a lowercase DNS hostname, without a scheme or path."
  }
}
variable "route53_zone_id" {
  description = "ID of an existing public Route 53 hosted zone."
  type        = string
  validation {
    condition     = can(regex("^Z[A-Z0-9]+$", var.route53_zone_id))
    error_message = "Supply an existing Route 53 hosted zone ID."
  }
}
variable "instance_type" {
  description = "x86_64 EC2 instance type; the default has 8 vCPUs and 32 GiB RAM."
  type        = string
  default     = "m7i.2xlarge"
  validation {
    condition     = can(regex("^[a-z][a-z0-9-]*\\.[a-z0-9]+$", var.instance_type))
    error_message = "Supply an EC2 instance type. Use an x86_64 type compatible with the image."
  }
}
variable "root_volume_size" {
  description = "Encrypted gp3 root volume size in GiB."
  type        = number
  default     = 40
  validation {
    condition     = var.root_volume_size >= 40 && floor(var.root_volume_size) == var.root_volume_size
    error_message = "Root storage must be an integer of at least 40 GiB."
  }
}
variable "data_volume_size" {
  description = "Encrypted gp3 persistent data volume size in GiB."
  type        = number
  default     = 250
  validation {
    condition     = var.data_volume_size >= 100 && floor(var.data_volume_size) == var.data_volume_size
    error_message = "Data storage must be an integer of at least 100 GiB."
  }
}
variable "vpc_cidr" {
  description = "IPv4 CIDR for the dedicated VPC."
  type        = string
  default     = "10.42.0.0/16"
  validation {
    condition     = can(cidrsubnet(var.vpc_cidr, 8, 0)) && can(regex("/16$", var.vpc_cidr))
    error_message = "Supply an IPv4 /16 CIDR."
  }
}
variable "tags" {
  description = "Additional resource tags; deployment identity tags take precedence."
  type        = map(string)
  default     = {}
}
