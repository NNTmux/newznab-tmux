terraform {
  required_version = ">= 1.10, < 2.0"
  required_providers {
    hcloud = { source = "hetznercloud/hcloud", version = "~> 1.66"
    }

  }
  backend "s3" {
    encrypt      = true
    use_lockfile = true
  }
}
provider "hcloud" {}
