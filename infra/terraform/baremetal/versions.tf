terraform {
  required_version = ">= 1.10, < 2.0"
  backend "s3" {
    encrypt      = true
    use_lockfile = true
  }
}
