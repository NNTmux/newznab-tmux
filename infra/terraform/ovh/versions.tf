terraform {
  required_version = ">= 1.10, < 2.0"
  required_providers {
    openstack = { source = "terraform-provider-openstack/openstack", version = "~> 3.0"
    }
    ovh = { source = "ovh/ovh", version = "~> 2.0"
    }

  }
  backend "s3" {
    encrypt      = true
    use_lockfile = true
  }
}
provider "openstack" {
  region = var.region
}
provider "ovh" {}
